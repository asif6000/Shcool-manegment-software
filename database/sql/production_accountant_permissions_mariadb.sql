/*
 * DRMS production — Accountant Management permissions (MariaDB / cPanel safe).
 *
 * The new module (Admin panel -> Accountant Management) gates its screens on two
 * permission names that do not exist in the live database yet:
 *     accountants.view     who may open the roster, the daily and the monthly
 *                          collection screens and any collector's detail page
 *     accountants.manage   who may activate or deactivate an accountant account
 * Without these rows the sidebar group stays hidden for everybody, including the
 * school admin, and ticking the boxes on Admin panel -> Roles and permissions
 * fails, because that screen can only grant permissions that already exist.
 *
 * Two rows in irvrzogs_dreamroots.permissions and two rows in
 * irvrzogs_dreamroots.role_has_permissions. That is all. No table is created,
 * dropped or altered. No payment, invoice, receipt, user or role row is touched.
 * The accountant role deliberately gets nothing: an accountant must not see the
 * roster, only their own collections.
 *
 * Re-runnable. INSERT IGNORE leans on the two unique keys that already exist
 * (permissions_name_guard_name_unique and the role_has_permissions primary key),
 * so pasting the whole file twice leaves exactly the same four rows.
 *
 * Run it in phpMyAdmin -> Import, or paste the statements in the SQL tab while
 * the irvrzogs_dreamroots database is selected. Every name is schema-qualified,
 * so the clicked left-hand node cannot send the rows into another database.
 *
 * If your host rejects the irvrzogs_dreamroots. prefixes, select that database
 * first and remove them — nothing else changes.
 */

/* -------------------------------------------------------------------------- */
/* Step 0 — read only. Run these four first and note what comes back.          */
/*                                                                             */
/* 0a returns 3 rows  -> the Spatie tables are where they should be; continue.  */
/* 0b returns 0 rows  -> the permissions are missing; this file is needed.      */
/* 0b returns 2 rows  -> already installed; pasting again is harmless.          */
/* 0c returns 1 row   -> the school_admin role exists; the grant will land.     */
/* 0d returns 0 rows  -> no accountant screens are unlocked for the admin yet.  */
/* -------------------------------------------------------------------------- */

SELECT TABLE_SCHEMA, TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'irvrzogs_dreamroots'
  AND TABLE_NAME IN ('permissions', 'roles', 'role_has_permissions');

SELECT id, name, guard_name
FROM irvrzogs_dreamroots.permissions
WHERE name IN ('accountants.view', 'accountants.manage')
ORDER BY name;

SELECT id, name, guard_name
FROM irvrzogs_dreamroots.roles
WHERE name = 'school_admin';

SELECT p.name AS granted
FROM irvrzogs_dreamroots.role_has_permissions rp
JOIN irvrzogs_dreamroots.permissions p ON p.id = rp.permission_id
JOIN irvrzogs_dreamroots.roles r       ON r.id = rp.role_id
WHERE r.name = 'school_admin'
  AND p.name IN ('accountants.view', 'accountants.manage')
ORDER BY p.name;

/* -------------------------------------------------------------------------- */
/* Step 1 — create the two permissions.                                         */
/* guard_name is 'web', the same guard every other permission in this app uses. */
/* -------------------------------------------------------------------------- */

INSERT IGNORE INTO irvrzogs_dreamroots.permissions (name, guard_name, created_at, updated_at)
VALUES ('accountants.view', 'web', NOW(), NOW());

INSERT IGNORE INTO irvrzogs_dreamroots.permissions (name, guard_name, created_at, updated_at)
VALUES ('accountants.manage', 'web', NOW(), NOW());

/* -------------------------------------------------------------------------- */
/* Step 2 — grant both to the school_admin role, by role name.                 */
/* No id is typed by hand: the SELECT resolves role and permission ids from     */
/* this database, so the ids do not have to match the local copy.               */
/* -------------------------------------------------------------------------- */

INSERT IGNORE INTO irvrzogs_dreamroots.role_has_permissions (permission_id, role_id)
SELECT p.id, r.id
FROM irvrzogs_dreamroots.permissions p
CROSS JOIN irvrzogs_dreamroots.roles r
WHERE p.name IN ('accountants.view', 'accountants.manage')
  AND p.guard_name = 'web'
  AND r.name = 'school_admin'
  AND r.guard_name = 'web';

/* -------------------------------------------------------------------------- */
/* Step 3 — read only. Verify, then stop.                                       */
/*                                                                             */
/* Expect exactly two rows, one per permission, both attached to school_admin.   */
/* If this returns nothing, Step 1 or Step 2 did not run against this schema.    */
/* -------------------------------------------------------------------------- */

SELECT r.name AS role_name, p.name AS permission_name, p.guard_name
FROM irvrzogs_dreamroots.role_has_permissions rp
JOIN irvrzogs_dreamroots.permissions p ON p.id = rp.permission_id
JOIN irvrzogs_dreamroots.roles r       ON r.id = rp.role_id
WHERE p.name IN ('accountants.view', 'accountants.manage')
ORDER BY r.name, p.name;

SELECT COUNT(*) AS accountant_permissions_now_on_school_admin
FROM irvrzogs_dreamroots.role_has_permissions rp
JOIN irvrzogs_dreamroots.permissions p ON p.id = rp.permission_id
JOIN irvrzogs_dreamroots.roles r       ON r.id = rp.role_id
WHERE r.name = 'school_admin'
  AND p.name IN ('accountants.view', 'accountants.manage');

/* -------------------------------------------------------------------------- */
/* Step 4 — read only. The tables these screens read.                           */
/*                                                                             */
/* Expect 13 rows. A table missing here is a 500 waiting on the screen that      */
/* reads it — tell which one is missing instead of opening the module. The        */
/* money tables are the ones the cashbook already uses, so if the cashbook        */
/* works these are normally present.                                            */
/* -------------------------------------------------------------------------- */

SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'irvrzogs_dreamroots'
  AND TABLE_NAME IN (
    'users', 'roles', 'model_has_roles', 'permissions', 'role_has_permissions',
    'employees', 'fee_payments', 'fee_payment_allocations', 'fee_invoices',
    'enrollments', 'students', 'classes', 'audit_logs'
  )
ORDER BY TABLE_NAME;

/*
 * ---------------------------------------------------------------------------
 * Optional — only if you also want the principal to open these screens.
 * ---------------------------------------------------------------------------
 * Do not paste this block as it stands. Remove the two leading asterisks from
 * each line, or type the statement yourself, then run it once.
 *
 * The same result is available without SQL: Admin panel -> Roles and
 * permissions, tick Accountant Management for the principal, Save.
 *
 * INSERT IGNORE INTO irvrzogs_dreamroots.role_has_permissions (permission_id, role_id)
 * SELECT p.id, r.id
 * FROM irvrzogs_dreamroots.permissions p
 * CROSS JOIN irvrzogs_dreamroots.roles r
 * WHERE p.name IN ('accountants.view', 'accountants.manage')
 *   AND p.guard_name = 'web'
 *   AND r.name = 'principal'
 *   AND r.guard_name = 'web';
 *
 * ---------------------------------------------------------------------------
 * Rollback — take the module away again. Removes only the two grants and the
 * two permission rows this file created; no other permission is touched.
 * The subquery form is deliberate: a multi-table DELETE with an alias needs a
 * default database selected, which a paste in the SQL tab may not have.
 * ---------------------------------------------------------------------------
 *
 * DELETE FROM irvrzogs_dreamroots.role_has_permissions
 * WHERE permission_id IN (
 *   SELECT id FROM irvrzogs_dreamroots.permissions
 *   WHERE name IN ('accountants.view', 'accountants.manage')
 * );
 *
 * DELETE FROM irvrzogs_dreamroots.permissions
 * WHERE name IN ('accountants.view', 'accountants.manage')
 *   AND guard_name = 'web';
 */
