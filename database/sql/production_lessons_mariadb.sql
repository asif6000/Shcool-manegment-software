/*
 * DRMS production lessons tables — MariaDB-safe, one paste, re-runnable.
 *
 * The Lessons module (a teacher writes a lesson for one period of their own
 * timetable; students read the published ones on /student) reads and writes two
 * tables that do not exist on the live database yet. Until this runs,
 * GET /student fails with:
 *   Base table or view not found: 1146  Table 'irvrzogs_dreamroots.lessons' doesn't exist
 * and GET /admin/lessons fails the same way.
 *
 * Two tables only: lessons, lesson_materials   (migrations 2026_11_01_120000
 * and 2026_11_01_121000). No existing table is touched. Nothing here drops,
 * alters, deletes or rewrites any row of any live table.
 *
 * FOREIGN KEY clauses are deliberately left out of the CREATE TABLE: hand-run
 * key clauses are the statements that fail most often on cPanel (#150/#1452)
 * and the app joins by id, not by database constraints, so an oddly ordered or
 * partly empty live database cannot abort the install. Optional foreign keys
 * are parked in the block at the very end.
 *
 * MariaDB accepts IF NOT EXISTS on CREATE TABLE, so the whole body can be
 * pasted again after a partial run without stopping. Block comments only, so a
 * paste that loses its newlines still parses. Every table name is qualified
 * with the live schema so the paste works whichever node was clicked.
 *
 * If your host rejects the `irvrzogs_dreamroots.` prefixes, select that
 * database in the left pane first and remove them — nothing else changes.
 */

/* -------------------------------------------------------------------------- */
/* Step 0 — read-only. Run this first and note the result.                     */
/* Both rows missing  -> paste the whole file below.                           */
/* One row present    -> paste anyway; IF NOT EXISTS skips what already exists. */
/* Both rows present  -> the error came from another database; do not run this. */
/* -------------------------------------------------------------------------- */

SELECT TABLE_SCHEMA, TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'irvrzogs_dreamroots'
  AND TABLE_NAME IN ('lessons', 'lesson_materials');

/* -------------------------------------------------------------------------- */
/* 1. lessons — one row per lesson a teacher writes for a period.              */
/* section_id NULL means "the whole class". status is 'draft' or 'published'.  */
/* deleted_at is the soft delete: a deleted lesson keeps its row.              */
/* -------------------------------------------------------------------------- */

CREATE TABLE IF NOT EXISTS `irvrzogs_dreamroots`.`lessons` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `teacher_id` bigint(20) unsigned NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chapter_number` varchar(20) COLLATE utf8mb4_unicode_ci NULL,
  `chapter_name` varchar(160) COLLATE utf8mb4_unicode_ci NULL,
  `lesson_number` varchar(20) COLLATE utf8mb4_unicode_ci NULL,
  `description` text COLLATE utf8mb4_unicode_ci NULL,
  `objectives` text COLLATE utf8mb4_unicode_ci NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NULL,
  `video_url` varchar(300) COLLATE utf8mb4_unicode_ci NULL,
  `youtube_id` varchar(16) COLLATE utf8mb4_unicode_ci NULL,
  `reference_links` text COLLATE utf8mb4_unicode_ci NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lessons_academic_session_id_foreign` (`academic_session_id`),
  KEY `lessons_class_id_foreign` (`class_id`),
  KEY `lessons_section_id_foreign` (`section_id`),
  KEY `lessons_subject_id_foreign` (`subject_id`),
  KEY `lessons_teacher_id_foreign` (`teacher_id`),
  KEY `lessons_created_by_foreign` (`created_by`),
  KEY `lessons_period_status_index` (`school_id`,`status`,`class_id`,`section_id`),
  KEY `lessons_subject_index` (`school_id`,`subject_id`),
  KEY `lessons_teacher_index` (`school_id`,`teacher_id`),
  KEY `lessons_recent_index` (`school_id`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -------------------------------------------------------------------------- */
/* 2. lesson_materials — the files attached to a lesson.                       */
/* lesson_id cascades on delete in the migrated schema; the app never relies    */
/* on it, so the constraint is optional (see the block at the end).            */
/* -------------------------------------------------------------------------- */

CREATE TABLE IF NOT EXISTS `irvrzogs_dreamroots`.`lesson_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `lesson_id` bigint(20) unsigned NOT NULL,
  `kind` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'document',
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(160) COLLATE utf8mb4_unicode_ci NULL,
  `file_size` int(10) unsigned NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lesson_materials_lesson_kind_index` (`lesson_id`,`kind`),
  KEY `lesson_materials_school_index` (`school_id`,`lesson_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -------------------------------------------------------------------------- */
/* 3. Read-only checks: run these last and compare against what you expect.    */
/* lessons must show 23 columns and the ten keys listed above.                  */
/* lesson_materials must show 10 columns and the three keys listed above.       */
/* Both row counts are 0 on a fresh install — that is correct, the tables are   */
/* empty until a teacher writes a lesson.                                       */
/* -------------------------------------------------------------------------- */

SHOW COLUMNS FROM `irvrzogs_dreamroots`.`lessons`;

SHOW INDEX FROM `irvrzogs_dreamroots`.`lessons`;

SHOW COLUMNS FROM `irvrzogs_dreamroots`.`lesson_materials`;

SHOW INDEX FROM `irvrzogs_dreamroots`.`lesson_materials`;

SELECT
  (SELECT COUNT(*) FROM `irvrzogs_dreamroots`.`lessons`) AS lesson_rows,
  (SELECT COUNT(*) FROM `irvrzogs_dreamroots`.`lessons` WHERE `status` = 'published') AS published_rows,
  (SELECT COUNT(*) FROM `irvrzogs_dreamroots`.`lesson_materials`) AS material_rows;

/* -------------------------------------------------------------------------- */
/* Optional A — record the two migrations so a later `php artisan migrate`      */
/* on the live server is a no-op instead of a duplicate-table error. Run it      */
/* only if you normally keep the `migrations` table up to date by hand. The       */
/* WHERE NOT EXISTS guard makes it safe to paste twice.                         */
/*
SET @batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `irvrzogs_dreamroots`.`migrations`);

INSERT INTO `irvrzogs_dreamroots`.`migrations` (`migration`, `batch`)
SELECT '2026_11_01_120000_create_lessons_table', @batch
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `irvrzogs_dreamroots`.`migrations`
    WHERE `migration` = '2026_11_01_120000_create_lessons_table');

SET @batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `irvrzogs_dreamroots`.`migrations`);

INSERT INTO `irvrzogs_dreamroots`.`migrations` (`migration`, `batch`)
SELECT '2026_11_01_121000_create_lesson_materials_table', @batch
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `irvrzogs_dreamroots`.`migrations`
    WHERE `migration` = '2026_11_01_121000_create_lesson_materials_table');

SELECT `migration`, `batch` FROM `irvrzogs_dreamroots`.`migrations`
WHERE `migration` LIKE '%lesson%';
*/

/* -------------------------------------------------------------------------- */
/* Optional B — the database-level links the migrated schema carries. The       */
/* module works without them. Apply one statement at a time, only after both     */
/* tables above exist; if the host complains (#1452 means an existing row has     */
/* an id that is not in the parent table), leave them out.                       */
/*
ALTER TABLE `irvrzogs_dreamroots`.`lessons`
  ADD CONSTRAINT `lessons_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `irvrzogs_dreamroots`.`schools` (`id`),
  ADD CONSTRAINT `lessons_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `irvrzogs_dreamroots`.`academic_sessions` (`id`),
  ADD CONSTRAINT `lessons_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `irvrzogs_dreamroots`.`classes` (`id`),
  ADD CONSTRAINT `lessons_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `irvrzogs_dreamroots`.`sections` (`id`),
  ADD CONSTRAINT `lessons_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `irvrzogs_dreamroots`.`subjects` (`id`),
  ADD CONSTRAINT `lessons_teacher_id_foreign` FOREIGN KEY (`teacher_id`) REFERENCES `irvrzogs_dreamroots`.`teachers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `lessons_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `irvrzogs_dreamroots`.`users` (`id`) ON DELETE SET NULL;

ALTER TABLE `irvrzogs_dreamroots`.`lesson_materials`
  ADD CONSTRAINT `lesson_materials_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `irvrzogs_dreamroots`.`schools` (`id`),
  ADD CONSTRAINT `lesson_materials_lesson_id_foreign` FOREIGN KEY (`lesson_id`) REFERENCES `irvrzogs_dreamroots`.`lessons` (`id`) ON DELETE CASCADE;
*/

/* -------------------------------------------------------------------------- */
/* Optional C — the two permissions the Lessons screens check. Students do NOT   */
/* need one: /student only ever shows lessons with status = 'published' for       */
/* their own class (and section, or a lesson with no section) in the current       */
/* academic session, and that is enforced in the query, not by permission.        */
/*                                                                             */
/* Roles are matched by NAME, never by id, so this cannot grant the right thing  */
/* to the wrong account on a live server. Paste the read-only check first:       */
/* if it already lists lessons.view and lessons.manage, skip the inserts.        */
/*
SELECT `name` FROM `irvrzogs_dreamroots`.`permissions`
WHERE `name` IN ('lessons.view', 'lessons.manage');

SELECT r.`name` AS role, p.`name` AS permission
FROM `irvrzogs_dreamroots`.`role_has_permissions` rp
JOIN `irvrzogs_dreamroots`.`roles` r ON r.`id` = rp.`role_id`
JOIN `irvrzogs_dreamroots`.`permissions` p ON p.`id` = rp.`permission_id`
WHERE p.`name` IN ('lessons.view', 'lessons.manage');

INSERT INTO `irvrzogs_dreamroots`.`permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'lessons.view', 'web', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `irvrzogs_dreamroots`.`permissions` WHERE `name` = 'lessons.view');

INSERT INTO `irvrzogs_dreamroots`.`permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'lessons.manage', 'web', NOW(), NOW() FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `irvrzogs_dreamroots`.`permissions` WHERE `name` = 'lessons.manage');

-- school_admin, principal and teacher write and read lessons; accountant does not.
INSERT INTO `irvrzogs_dreamroots`.`role_has_permissions` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `irvrzogs_dreamroots`.`permissions` p
JOIN `irvrzogs_dreamroots`.`roles` r ON r.`name` IN ('school_admin', 'principal', 'teacher')
WHERE p.`name` IN ('lessons.view', 'lessons.manage')
  AND NOT EXISTS (
    SELECT 1 FROM `irvrzogs_dreamroots`.`role_has_permissions` x
    WHERE x.`permission_id` = p.`id` AND x.`role_id` = r.`id`
  );

SELECT r.`name` AS role, p.`name` AS permission
FROM `irvrzogs_dreamroots`.`role_has_permissions` rp
JOIN `irvrzogs_dreamroots`.`roles` r ON r.`id` = rp.`role_id`
JOIN `irvrzogs_dreamroots`.`permissions` p ON p.`id` = rp.`permission_id`
WHERE p.`name` IN ('lessons.view', 'lessons.manage');
*/
