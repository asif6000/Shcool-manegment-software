/*
 * DRMS production — Homework / Lessons / Study materials become teacher screens.
 *
 * The division of work the school decided on:
 *     the office assigns subjects and builds the routine;
 *     the teacher sets the homework, the classwork and the lesson images.
 * So the school admin must not see the "Homework & Lessons" group at all, and the
 * URLs must answer 403 rather than show a form the admin should never fill.
 *
 * This file only removes six permission grants from ONE role:
 *     homework.view     homework.manage
 *     lessons.view      lessons.manage
 *     materials.view    materials.manage
 * The principal keeps the lesson permissions for oversight; its homework and
 * classwork grants are removed by the follow-up file
 * production_teacher_only_learning_principal_fix_mariadb.sql, because those two
 * modules are the teacher's own work. The teacher role is not touched. No
 * homework row, lesson row, material row, class-video row or file is deleted or
 * altered by anything here.
 *
 * The same six names are excluded from school_admin in
 * database/seeders/RolePermissionSeeder.php, so a future php artisan db:seed
 * keeps this arrangement instead of silently handing the section back.
 *
 * Re-runnable. A DELETE that matches nothing affects 0 rows, which is the correct
 * answer if the section was already taken away.
 *
 * Run it in phpMyAdmin -> the SQL tab, with irvrzogs_dreamroots selected, or
 * through Import. Every name is schema-qualified, so the clicked left-hand node
 * cannot send the rows into another database.
 *
 * If your host rejects the irvrzogs_dreamroots. prefixes, select that database
 * first and remove them — nothing else changes.
 */

/* -------------------------------------------------------------------------- */
/* Step 1 — read only. Who holds the six permissions right now?                 */
/*                                                                             */
/* Expect school_admin 6, principal 6, teacher 6 before this file runs.          */
/* If school_admin already shows nothing, the section is already teacher-only.    */
/* -------------------------------------------------------------------------- */

SELECT r.name AS role_name, COUNT(*) AS learning_grants
FROM irvrzogs_dreamroots.role_has_permissions rp
JOIN irvrzogs_dreamroots.permissions p ON p.id = rp.permission_id
JOIN irvrzogs_dreamroots.roles r       ON r.id = rp.role_id
WHERE p.name IN ('homework.view', 'homework.manage', 'lessons.view', 'lessons.manage',
                 'materials.view', 'materials.manage')
GROUP BY r.name
ORDER BY r.name;

/* -------------------------------------------------------------------------- */
/* Step 2 — take the six away from the school_admin role only.                  */
/*                                                                             */
/* The subquery form is deliberate: it names no id, so nothing has to match the    */
/* local copy, and only these six permission rows for this one role can be hit.    */
/* Expect "6 rows affected".                                                       */
/* -------------------------------------------------------------------------- */

DELETE FROM irvrzogs_dreamroots.role_has_permissions
WHERE role_id = (
  SELECT id FROM irvrzogs_dreamroots.roles
  WHERE name = 'school_admin' AND guard_name = 'web'
)
AND permission_id IN (
  SELECT id FROM irvrzogs_dreamroots.permissions
  WHERE name IN ('homework.view', 'homework.manage', 'lessons.view', 'lessons.manage',
                 'materials.view', 'materials.manage')
);

/* -------------------------------------------------------------------------- */
/* Step 3 — drop the stale Spatie permission cache.                             */
/*                                                                             */
/* Without this the app keeps serving the old permission list and the admin       */
/* still sees the group until the cache expires on its own. Only cache entries     */
/* are deleted; the app rebuilds the list on the next request.                     */
/*                                                                             */
/* If it reports 0 affected rows the app is not using the database cache; run      */
/* php artisan cache:clear from the cPanel terminal instead.                       */
/* -------------------------------------------------------------------------- */

DELETE FROM irvrzogs_dreamroots.`cache`
WHERE `key` LIKE '%spatie.permission.cache%';

/* -------------------------------------------------------------------------- */
/* Step 4 — read only. Verify, then stop.                                       */
/*                                                                             */
/* Expect two rows now: principal 6 and teacher 6, with school_admin gone from     */
/* the list. Then sign out, sign back in as the admin and confirm the sidebar      */
/* has no "Homework & Lessons" group, and that /admin/homework answers 403.        */
/* -------------------------------------------------------------------------- */

SELECT r.name AS role_name, COUNT(*) AS learning_grants
FROM irvrzogs_dreamroots.role_has_permissions rp
JOIN irvrzogs_dreamroots.permissions p ON p.id = rp.permission_id
JOIN irvrzogs_dreamroots.roles r       ON r.id = rp.role_id
WHERE p.name IN ('homework.view', 'homework.manage', 'lessons.view', 'lessons.manage',
                 'materials.view', 'materials.manage')
GROUP BY r.name
ORDER BY r.name;

/*
 * ---------------------------------------------------------------------------
 * Rollback — give the section back to the school admin.
 * This is also exactly what the Admin panel -> Roles and permissions screen
 * does when the boxes are ticked again, so if the code on the server already
 * lists these six names, that screen is the easier way to undo this.
 * ---------------------------------------------------------------------------
 *
 * INSERT IGNORE INTO irvrzogs_dreamroots.role_has_permissions (permission_id, role_id)
 * SELECT p.id, r.id
 * FROM irvrzogs_dreamroots.permissions p
 * CROSS JOIN irvrzogs_dreamroots.roles r
 * WHERE p.name IN ('homework.view', 'homework.manage', 'lessons.view', 'lessons.manage',
 *                  'materials.view', 'materials.manage')
 *   AND p.guard_name = 'web'
 *   AND r.name = 'school_admin'
 *   AND r.guard_name = 'web';
 *
 * Then delete the cache row again:
 *
 * DELETE FROM irvrzogs_dreamroots.`cache`
 * WHERE `key` LIKE '%spatie.permission.cache%';
 */
