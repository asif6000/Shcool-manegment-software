/*
 * DRMS production — the two additive columns the teacher-only homework and
 * classwork screens need, plus the permission revocation the rule depends on.
 *
 * The rule: the office assigns periods; only the teacher who holds the period
 * writes the homework and the classwork. That rule already lives in
 *     RolePermissionSeeder          (school_admin excluded from six permissions)
 *     HomeworkPolicy
 *     StudyMaterialPolicy
 *     SaveHomeworkRequest / SaveStudyMaterialRequest
 * so the app enforces it with or without this file. This file brings the
 * database in line:
 *
 *     1. homework.instructions                     — the separate how-to text
 *     2. study_materials.status                     — draft | published
 *     3. study_materials.published_at               — when the class may see it
 *     4. study_materials.teacher_id                 — who set the classwork
 *     5. study_materials.lesson_id                  — the chapter it belongs to
 *     6. one composite index for the draft list
 *     7. school_admin loses the six learning permissions, cache cleared
 *
 * No table is created, dropped or renamed. No row is deleted, and the only
 * UPDATE is the ADD COLUMN defaults, which mark every existing study material
 * 'published' — exactly what the class could already see before this run.
 *
 * Re-runnable: columns and the index use IF NOT EXISTS, the permission DELETE
 * matches nothing on a second run, and the cache DELETE only clears cache rows
 * the app rebuilds on the next request.
 *
 * Run in phpMyAdmin -> SQL tab with irvrzogs_dreamroots selected, or through
 * Import. Every table is schema-qualified, so the left-hand node that happens
 * to be clicked cannot send the change into another database.
 *
 * If the host rejects the irvrzogs_dreamroots. prefix, select that database
 * first and remove the prefix — nothing else changes.
 */

/* -------------------------------------------------------------------------- */
/* Step 1 — read only. What is on the live tables before anything changes?      */
/*                                                                             */
/* Expect: instructions_missing = 1 on a database that has not run this yet.    */
/* Expect: status_missing = 1 on a database that has not run this yet.          */
/* -------------------------------------------------------------------------- */

SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'irvrzogs_dreamroots' AND TABLE_NAME = 'homework'
      AND COLUMN_NAME = 'instructions') AS instructions_present,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'irvrzogs_dreamroots' AND TABLE_NAME = 'study_materials'
      AND COLUMN_NAME = 'status') AS status_present;

/* -------------------------------------------------------------------------- */
/* Step 2 — homework gets its separate Instructions text.                      */
/*                                                                             */
/* description keeps the task itself; instructions carries the how-to          */
/* (materials needed, where to hand it in). Both are nullable, so no row is     */
/* rewritten and the student page simply hides an empty block.                  */
/* -------------------------------------------------------------------------- */

ALTER TABLE irvrzogs_dreamroots.`homework`
  ADD COLUMN IF NOT EXISTS `instructions` TEXT COLLATE utf8mb4_unicode_ci NULL AFTER `description`;

/* -------------------------------------------------------------------------- */
/* Step 3 — study_materials learns draft/published and who set the classwork.  */
/*                                                                             */
/* `status` defaults to 'published', which is the truthful state of every row    */
/* already on the portal. The app writes 'draft' or 'published' explicitly on    */
/* each save, so the default only protects rows that predate this file.          */
/*                                                                             */
/* teacher_id and lesson_id are plain columns here; the matching keys are the    */
/* optional block at the end. The app joins by id, so the screen works without   */
/* them, and a host that refuses the key block cannot abort the install.         */
/* -------------------------------------------------------------------------- */

ALTER TABLE irvrzogs_dreamroots.`study_materials`
  ADD COLUMN IF NOT EXISTS `status` VARCHAR(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published' AFTER `subject_id`,
  ADD COLUMN IF NOT EXISTS `teacher_id` BIGINT(20) UNSIGNED NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `lesson_id` BIGINT(20) UNSIGNED NULL AFTER `teacher_id`,
  ADD COLUMN IF NOT EXISTS `published_at` TIMESTAMP NULL DEFAULT NULL AFTER `work_date`;

ALTER TABLE irvrzogs_dreamroots.`study_materials`
  ADD INDEX IF NOT EXISTS `study_materials_status_period_index` (`school_id`, `status`, `class_id`);

/* -------------------------------------------------------------------------- */
/* Step 4 — the office assignment rule: take the six away from school_admin.   */
/*                                                                             */
/* Before this file: school_admin 6, principal 6, teacher 6.                    */
/* After:            principal 6, teacher 6 — school_admin is not in the list.   */
/*                                                                             */
/* The subquery form names no id, so nothing has to match the local copy, and   */
/* only these six grants for this one role can be hit. Expect "6 rows affected" */
/* on the first run and "0 rows affected" afterwards.                           */
/* -------------------------------------------------------------------------- */

DELETE FROM irvrzogs_dreamroots.`role_has_permissions`
WHERE `role_id` = (
  SELECT `id` FROM irvrzogs_dreamroots.`roles`
  WHERE `name` = 'school_admin' AND `guard_name` = 'web'
)
AND `permission_id` IN (
  SELECT `id` FROM irvrzogs_dreamroots.`permissions`
  WHERE `name` IN ('homework.view', 'homework.manage', 'lessons.view', 'lessons.manage',
                   'materials.view', 'materials.manage')
);

/* The app caches the permission list in the database cache. Without this it     */
/* keeps serving the old list until the entry expires on its own.               */
DELETE FROM irvrzogs_dreamroots.`cache`
WHERE `key` LIKE '%spatie.permission.cache%';

/* -------------------------------------------------------------------------- */
/* Step 5 — read only. Verify, then stop.                                       */
/*                                                                             */
/* Expect instructions_present = 1, status_present = 1, status_draft = 0,        */
/* status_published = every study material row. The permission list must show   */
/* principal and teacher only.                                                  */
/*                                                                             */
/* Then sign out and back in as the admin: /admin/homework and /admin/materials  */
/* must answer 403, and the sidebar must not offer the learning group.           */
/* -------------------------------------------------------------------------- */

SELECT
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'irvrzogs_dreamroots' AND TABLE_NAME = 'homework'
      AND COLUMN_NAME = 'instructions') AS instructions_present,
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'irvrzogs_dreamroots' AND TABLE_NAME = 'study_materials'
      AND COLUMN_NAME = 'status') AS status_present,
  (SELECT COUNT(*) FROM irvrzogs_dreamroots.`study_materials` WHERE `status` = 'draft') AS status_draft,
  (SELECT COUNT(*) FROM irvrzogs_dreamroots.`study_materials` WHERE `status` = 'published') AS status_published;

SELECT r.name AS role_name, COUNT(*) AS learning_grants
FROM irvrzogs_dreamroots.`role_has_permissions` rp
JOIN irvrzogs_dreamroots.`permissions` p ON p.id = rp.permission_id
JOIN irvrzogs_dreamroots.`roles` r       ON r.id = rp.role_id
WHERE p.name IN ('homework.view', 'homework.manage', 'lessons.view', 'lessons.manage',
                 'materials.view', 'materials.manage')
GROUP BY r.name
ORDER BY r.name;

/* -------------------------------------------------------------------------- */
/* Optional: the database-level links the migrated schema carries.              */
/*                                                                             */
/* Apply one statement at a time, only after Step 3 created the columns. If the */
/* host complains about a missing key or a type mismatch, leave this block out: */
/* the screens do not depend on these constraints.                              */
/* -------------------------------------------------------------------------- */

/*
ALTER TABLE irvrzogs_dreamroots.`study_materials`
  ADD CONSTRAINT `study_materials_teacher_id_foreign` FOREIGN KEY (`teacher_id`)
    REFERENCES irvrzogs_dreamroots.`teachers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `study_materials_lesson_id_foreign` FOREIGN KEY (`lesson_id`)
    REFERENCES irvrzogs_dreamroots.`lessons` (`id`) ON DELETE SET NULL;
*/

/*
 * ---------------------------------------------------------------------------
 * Rollback — give the six permissions back to the school admin.
 * This is exactly what Admin panel -> Roles and permissions does when the
 * boxes are ticked again, so if the new code is already on the server that
 * screen is the easier way to undo Step 4.
 *
 * INSERT IGNORE INTO irvrzogs_dreamroots.`role_has_permissions` (`permission_id`, `role_id`)
 * SELECT p.id, r.id
 * FROM irvrzogs_dreamroots.`permissions` p
 * CROSS JOIN irvrzogs_dreamroots.`roles` r
 * WHERE p.name IN ('homework.view', 'homework.manage', 'lessons.view', 'lessons.manage',
 *                  'materials.view', 'materials.manage')
 *   AND p.guard_name = 'web'
 *   AND r.name = 'school_admin'
 *   AND r.guard_name = 'web';
 *
 * DELETE FROM irvrzogs_dreamroots.`cache`
 * WHERE `key` LIKE '%spatie.permission.cache%';
 *
 * The columns themselves can stay: the app ignores unknown columns, and the
 * older code never writes status, published_at, teacher_id, lesson_id or
 * instructions.
 * ---------------------------------------------------------------------------
 */
