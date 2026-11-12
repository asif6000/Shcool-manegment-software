/*
 * DRMS production — "403 This action is unauthorized" when creating an accountant.
 *
 * You open Admin panel -> Accountant Management -> Add accountant, fill the form,
 * press Save and the page comes back as 403 on /admin/accountants. Two different
 * permission gaps produce that same screen, so this file starts with a read-only
 * check that tells them apart, then fixes only the one you actually have.
 *
 * The create button is gated on "Create users" (users.create). The screen it
 * redirects to after saving is gated on "Accountant Management" (accountants.view).
 *     missing users.create       the POST itself is refused, you stay on the form
 *     missing accountants.view   the accountant IS created, then the redirect 403s
 *
 * Nothing here creates, drops or alters a table. It inserts rows into
 * irvrzogs_dreamroots.role_has_permissions, inserts the two permission rows if
 * they are missing, and deletes the stale Spatie cache row so the grants take
 * effect without waiting for the cache to expire.
 *
 * Re-runnable. INSERT IGNORE leans on the permissions unique key
 * (permissions_name_guard_name_unique) and on the role_has_permissions primary
 * key, so pasting the whole file twice leaves exactly the same rows.
 *
 * Run it in phpMyAdmin -> the SQL tab, with irvrzogs_dreamroots selected, or
 * through Import. Every name is schema-qualified, so the clicked left-hand node
 * cannot send the rows into another database.
 *
 * If your host rejects the irvrzogs_dreamroots. prefixes, select that database
 * first and remove them — nothing else changes.
 */

/* -------------------------------------------------------------------------- */
/* Step 1 — read only. Which role is your login, and what does it hold?        */
/*                                                                             */
/* Run this first and read the three flags for the row that is your own login.  */
/*                                                                             */
/*   has_users_create = 0      -> the accountant form itself is being refused.  */
/*   has_accountants_view = 0  -> the accountant list is being refused; the     */
/*                                accountant may already exist in the users     */
/*                                table even though you never saw it. Run the    */
/*                                read-only query in Step 2 to check.           */
/*   both = 1 and still 403    -> the Spatie cache is stale; skip to Step 5.    */
/*   every flag 0 for your login -> that role was never given any of these;      */
/*                                below level 60 a role may not create an        */
/*                                accountant at all (RoleHierarchy), so use the   */
/*                                school_admin login for that one.               */
/* -------------------------------------------------------------------------- */

SELECT
    u.id,
    u.name,
    u.email,
    r.name AS role_name,
    COALESCE(RoleCheck.users_create, 0)       AS has_users_create,
    COALESCE(RoleCheck.accountants_view, 0)   AS has_accountants_view,
    COALESCE(RoleCheck.accountants_manage, 0) AS has_accountants_manage
FROM irvrzogs_dreamroots.users u
JOIN irvrzogs_dreamroots.model_has_roles mhr ON mhr.model_id = u.id
JOIN irvrzogs_dreamroots.roles r             ON r.id = mhr.role_id
LEFT JOIN (
    SELECT rp.role_id,
           MAX(p.name = 'users.create')        AS users_create,
           MAX(p.name = 'accountants.view')    AS accountants_view,
           MAX(p.name = 'accountants.manage')  AS accountants_manage
    FROM irvrzogs_dreamroots.role_has_permissions rp
    JOIN irvrzogs_dreamroots.permissions p ON p.id = rp.permission_id
    WHERE p.name IN ('users.create', 'accountants.view', 'accountants.manage')
    GROUP BY rp.role_id
) RoleCheck ON RoleCheck.role_id = r.id
ORDER BY u.id, r.name;

/* Step 2 — read only. Did a partially-saved accountant already get created?    */
/* If Step 1 said has_accountants_view = 0, check before you create another.     */
/* Compare against the roster you can see in Admin panel -> Users.               */

SELECT u.id, u.name, u.email, u.is_active, u.created_at
FROM irvrzogs_dreamroots.users u
JOIN irvrzogs_dreamroots.model_has_roles mhr ON mhr.model_id = u.id
JOIN irvrzogs_dreamroots.roles r             ON r.id = mhr.role_id
WHERE r.name = 'accountant'
ORDER BY u.id;

/* -------------------------------------------------------------------------- */
/* Step 3 — read only. Do the two accountant permissions exist at all?          */
/*                                                                             */
/* 0 rows -> they were never installed; Step 4 creates them.                     */
/* 2 rows -> they exist; you only need the grants in Step 4's second statement.  */
/* -------------------------------------------------------------------------- */

SELECT id, name, guard_name
FROM irvrzogs_dreamroots.permissions
WHERE name IN ('accountants.view', 'accountants.manage', 'users.create')
ORDER BY name;

/* -------------------------------------------------------------------------- */
/* Step 4 — create the two accountant permissions, then grant what is missing.  */
/*                                                                             */
/* Change 'school_admin' in BOTH statements to the role name from Step 1 — the   */
/* one your own login carries (school_admin, or principal if you work from that   */
/* account). Do not grant to accountant: an accountant must not see the roster.   */
/*                                                                             */
/* users.create is a real permission row in this app already, so the two           */
/* INSERT IGNORE statements above only create the accountant permissions; the       */
/* grant below adds all three to the role, and skips the ones it already holds.     */
/* -------------------------------------------------------------------------- */

INSERT IGNORE INTO irvrzogs_dreamroots.permissions (name, guard_name, created_at, updated_at)
VALUES ('accountants.view', 'web', NOW(), NOW());

INSERT IGNORE INTO irvrzogs_dreamroots.permissions (name, guard_name, created_at, updated_at)
VALUES ('accountants.manage', 'web', NOW(), NOW());

/* 4b — the grant. Same statement for all three permissions; the SELECT resolves */
/* the ids from this database, so nothing is typed by hand.                       */

INSERT IGNORE INTO irvrzogs_dreamroots.role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM irvrzogs_dreamroots.permissions p
CROSS JOIN irvrzogs_dreamroots.roles r
WHERE p.name IN ('accountants.view', 'accountants.manage', 'users.create')
  AND p.guard_name = 'web'
  AND r.name = 'school_admin'
  AND r.guard_name = 'web';

/* -------------------------------------------------------------------------- */
/* Step 5 — drop the stale Spatie permission cache.                             */
/*                                                                             */
/* Rows inserted straight into the database skip the model events that would    */
/* otherwise refresh the cache, so the app keeps using the old permission list   */
/* until this row goes away. The app rebuilds it on the next request.            */
/* This deletes cache entries only — no permission, role or user row is touched. */
/*                                                                             */
/* If the statement reports 0 affected rows the app is not using the database    */
/* cache; run php artisan cache:clear from the cPanel terminal instead.          */
/* -------------------------------------------------------------------------- */

DELETE FROM irvrzogs_dreamroots.`cache`
WHERE `key` LIKE '%spatie.permission.cache%';

/* -------------------------------------------------------------------------- */
/* Step 6 — read only. Verify, then stop.                                       */
/*                                                                             */
/* Expect three rows for the role you granted (users.create, accountants.view,   */
/* accountants.manage). Then sign out, sign back in and try the form again.       */
/* -------------------------------------------------------------------------- */

SELECT r.name AS role_name, p.name AS permission_name, p.guard_name
FROM irvrzogs_dreamroots.role_has_permissions rp
JOIN irvrzogs_dreamroots.permissions p ON p.id = rp.permission_id
JOIN irvrzogs_dreamroots.roles r       ON r.id = rp.role_id
WHERE p.name IN ('users.create', 'accountants.view', 'accountants.manage')
ORDER BY r.name, p.name;

/*
 * ---------------------------------------------------------------------------
 * The same thing without SQL — do this if you can reach Admin panel -> Roles
 * and permissions with an account that already has it.
 * ---------------------------------------------------------------------------
 * Tick, for the role your login carries:
 *     User management -> Create users        (unlocks the accountant form)
 *     Accounting      -> Accountants View    (unlocks the roster it returns to)
 *     Accounting      -> Accountants Manage  (activate / deactivate)
 * Save. Saving through that screen refreshes the Spatie cache by itself, so
 * Step 5 is not needed on this path.
 *
 * The two Accounting boxes only appear on that screen if the code on the server
 * already lists them in config/school.php. If they are absent there, run Step 4
 * and Step 5 instead — the permissions have to exist before the screen can
 * offer them.
 *
 * ---------------------------------------------------------------------------
 * Rollback — take back exactly what Step 4 granted, and nothing else.
 * The subquery form is deliberate: a multi-table DELETE with an alias needs a
 * default database selected, which a paste in the SQL tab may not have.
 * Change 'school_admin' to the role you granted, run the first statement, then
 * delete the cache row again (Step 5).
 *
 * The second statement also removes the two accountant permission rows, which
 * hides the whole module for every role. Leave it out if you only meant to
 * widen or narrow one role.
 * ---------------------------------------------------------------------------
 *
 * DELETE FROM irvrzogs_dreamroots.role_has_permissions
 * WHERE role_id = (
 *   SELECT id FROM irvrzogs_dreamroots.roles
 *   WHERE name = 'school_admin' AND guard_name = 'web'
 * )
 * AND permission_id IN (
 *   SELECT id FROM irvrzogs_dreamroots.permissions
 *   WHERE name IN ('users.create', 'accountants.view', 'accountants.manage')
 * );
 *
 * DELETE FROM irvrzogs_dreamroots.permissions
 * WHERE name IN ('accountants.view', 'accountants.manage')
 *   AND guard_name = 'web';
 */
