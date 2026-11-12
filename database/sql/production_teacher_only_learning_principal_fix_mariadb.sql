/*
 * DRMS production — Homework / Classwork belong to the teacher role only.
 *
 * The code that refuses the office is already deployed with the policy layer:
 * HomeworkPolicy and StudyMaterialPolicy admit only the teacher role, so
 * /admin/homework and /admin/materials answer 403 for the school admin AND the
 * principal even when a permission row still exists. The principal keeps the
 * lesson permissions for oversight — lessons are not touched here.
 *
 * A leftover grant only makes the sidebar advertise a screen that then refuses
 * the click. This file removes those stale grants so the menu matches reality.
 * It removes at most four permission rows from ONE role:
 *     homework.view   homework.manage
 *     materials.view  materials.manage
 * No homework, material, lesson or file row is touched. lessons.view and
 * lessons.manage stay with the principal and the teacher.
 *
 * Re-runnable: a DELETE that matches nothing affects 0 rows, which is the
 * correct answer when the grants were already removed.
 *
 * Run it in phpMyAdmin -> SQL tab with irvrzogs_dreamroots selected, or through
 * Import. Every name is schema-qualified so the clicked left-hand node cannot
 * send the rows into another database.
 *
 * If your host rejects the irvrzogs_dreamroots. prefixes, select that database
 * first and remove them — nothing else changes.
 */

/* -------------------------------------------------------------------------- */
/* Step 1 — read only. Who holds homework/classwork permissions right now?      */
/*                                                                             */
/* Expect teacher 4. If school_admin or principal still appear, this file has   */
/* something to remove; if only teacher appears, it is already correct.        */
/* -------------------------------------------------------------------------- */

SELECT r.name AS role_name, COUNT(*) AS homework_classwork_grants
FROM irvrzogs_dreamroots.role_has_permissions rp
JOIN irvrzogs_dreamroots.permissions p ON p.id = rp.permission_id
JOIN irvrzogs_dreamroots.roles r       ON r.id = rp.role_id
WHERE p.name IN ('homework.view', 'homework.manage', 'materials.view', 'materials.manage')
GROUP BY r.name
ORDER BY r.name;

/* -------------------------------------------------------------------------- */
/* Step 2 — remove the four grants from every role except teacher.             */
/*                                                                             */
/* The subquery names roles by name, never by id, so nothing has to match the  */
/* local copy. Expect a small "rows affected" number (0–8).                    */
/* -------------------------------------------------------------------------- */

DELETE FROM irvrzogs_dreamroots.role_has_permissions
WHERE role_id IN (
  SELECT id FROM irvrzogs_dreamroots.roles
  WHERE name <> 'teacher' AND guard_name = 'web'
)
AND permission_id IN (
  SELECT id FROM irvrzogs_dreamroots.permissions
  WHERE name IN ('homework.view', 'homework.manage', 'materials.view', 'materials.manage')
    AND guard_name = 'web'
);

/* -------------------------------------------------------------------------- */
/* Step 3 — drop the stale Spatie permission cache.                            */
/*                                                                             */
/* Without this the app keeps serving the old permission list until the cache  */
/* expires. Only cache entries are deleted; the app rebuilds them next request. */
/* If 0 rows are affected the app is not using the database cache — run         */
/* php artisan cache:clear from the cPanel terminal instead.                    */
/* -------------------------------------------------------------------------- */

DELETE FROM irvrzogs_dreamroots.`cache`
WHERE `key` LIKE '%spatie.permission.cache%';

/* -------------------------------------------------------------------------- */
/* Step 4 — read only. Verify, then stop.                                      */
/*                                                                             */
/* Expect one row: teacher 4. Sign back in as the principal: the "Homework &    */
/* Lessons" group no longer advertises Homework or Classwork, and              */
/* /admin/homework and /admin/materials answer 403. Lesson pages still open.   */
/* -------------------------------------------------------------------------- */

SELECT r.name AS role_name, COUNT(*) AS homework_classwork_grants
FROM irvrzogs_dreamroots.role_has_permissions rp
JOIN irvrzogs_dreamroots.permissions p ON p.id = rp.permission_id
JOIN irvrzogs_dreamroots.roles r       ON r.id = rp.role_id
WHERE p.name IN ('homework.view', 'homework.manage', 'materials.view', 'materials.manage')
GROUP BY r.name
ORDER BY r.name;

/*
 * ---------------------------------------------------------------------------
 * Rollback — give homework/classwork back to a role if the school changes its
 * mind. This is also exactly what Admin panel -> Roles and permissions does,
 * except that screen now disables these boxes for every role except teacher.
 * ---------------------------------------------------------------------------
 *
 * INSERT IGNORE INTO irvrzogs_dreamroots.role_has_permissions (permission_id, role_id)
 * SELECT p.id, r.id
 * FROM irvrzogs_dreamroots.permissions p
 * CROSS JOIN irvrzogs_dreamroots.roles r
 * WHERE p.name IN ('homework.view', 'homework.manage', 'materials.view', 'materials.manage')
 *   AND p.guard_name = 'web'
 *   AND r.name = 'principal'
 *   AND r.guard_name = 'web';
 *
 * Then delete the cache row again:
 *
 * DELETE FROM irvrzogs_dreamroots.`cache`
 * WHERE `key` LIKE '%spatie.permission.cache%';
 */
