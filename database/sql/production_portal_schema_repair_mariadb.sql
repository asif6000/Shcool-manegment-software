/*
 * EduNest production student-portal repair — MariaDB-safe, one paste, re-runnable.
 *
 * The live site threw, on GET /student:
 *   Unknown column 'status' in 'WHERE'  ...  select * from `homework` where `status` = published
 * The deployed code is newer than the live schema, so the portal dashboard asks
 * for columns and tables that were never created there. This script covers the
 * whole dashboard path, not only the statement that broke first:
 *
 *   homework            + status, published_at, status/due index   (2026_10_29_230000)
 *   homework_submissions                                     table (2026_10_28_120000)
 *   student_leaves                                           table (2026_10_28_120100)
 *   study_materials          + kind column and its index     tables (2026_10_29_220000/230100)
 *
 * MariaDB accepts IF NOT EXISTS on CREATE TABLE, on ADD COLUMN and on CREATE
 * INDEX, so the whole body can be pasted again after any partial run without
 * stopping. FOREIGN KEY clauses are deliberately left out of the new tables:
 * they are the statements that fail most often by hand (#150/#1452) and the app
 * does not rely on them, so an empty or oddly ordered live database cannot
 * abort the repair. Optional foreign keys are parked at the very end.
 *
 * Block comments only, so a paste that loses its newlines still parses.
 * Nothing here drops, deletes or rewrites any row.
 */

/* -------------------------------------------------------------------------- */
/* homework: a draft/published flag the portal filters on                      */
/* -------------------------------------------------------------------------- */

ALTER TABLE `homework`
  ADD COLUMN IF NOT EXISTS `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published' AFTER `due_date`,
  ADD COLUMN IF NOT EXISTS `published_at` timestamp NULL DEFAULT NULL AFTER `status`;

CREATE INDEX IF NOT EXISTS `homework_status_due_index` ON `homework` (`school_id`, `status`, `due_date`);

/* -------------------------------------------------------------------------- */
/* homework_submissions: one row per student per homework                      */
/* -------------------------------------------------------------------------- */

CREATE TABLE IF NOT EXISTS `homework_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `homework_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `enrollment_id` bigint(20) unsigned NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `text_answer` text COLLATE utf8mb4_unicode_ci NULL,
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
  `attachment_name` varchar(160) COLLATE utf8mb4_unicode_ci NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `resubmitted_at` timestamp NULL DEFAULT NULL,
  `marks` decimal(6,2) NULL,
  `feedback` text COLLATE utf8mb4_unicode_ci NULL,
  `reviewed_by` bigint(20) unsigned NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `homework_submissions_student_unique` (`homework_id`,`student_id`),
  KEY `homework_submissions_student_id_foreign` (`student_id`),
  KEY `homework_submissions_enrollment_id_foreign` (`enrollment_id`),
  KEY `homework_submissions_reviewed_by_foreign` (`reviewed_by`),
  KEY `homework_submissions_student_index` (`school_id`,`student_id`,`submitted_at`),
  KEY `homework_submissions_status_index` (`homework_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -------------------------------------------------------------------------- */
/* student_leaves: the leave list and the pending count on the dashboard        */
/* -------------------------------------------------------------------------- */

CREATE TABLE IF NOT EXISTS `student_leaves` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `leave_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'casual',
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_phone` varchar(30) COLLATE utf8mb4_unicode_ci NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `decision_notes` text COLLATE utf8mb4_unicode_ci NULL,
  `decided_by` bigint(20) unsigned NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_leaves_student_id_foreign` (`student_id`),
  KEY `student_leaves_academic_session_id_foreign` (`academic_session_id`),
  KEY `student_leaves_decided_by_foreign` (`decided_by`),
  KEY `student_leaves_created_by_foreign` (`created_by`),
  KEY `student_leaves_student_date_index` (`school_id`,`student_id`,`from_date`),
  KEY `student_leaves_status_index` (`school_id`,`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -------------------------------------------------------------------------- */
/* study_materials: recent class materials, and the kind flag that splits       */
/* materials from notes/syllabus copies                                        */
/* -------------------------------------------------------------------------- */

CREATE TABLE IF NOT EXISTS `study_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `class_id` bigint(20) unsigned NOT NULL,
  `section_id` bigint(20) unsigned NULL,
  `subject_id` bigint(20) unsigned NOT NULL,
  `kind` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'material',
  `work_date` date NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NULL,
  `file_name` varchar(160) COLLATE utf8mb4_unicode_ci NULL,
  `file_size` int(10) unsigned NULL,
  `created_by` bigint(20) unsigned NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `study_materials_academic_session_id_foreign` (`academic_session_id`),
  KEY `study_materials_class_id_foreign` (`class_id`),
  KEY `study_materials_section_id_foreign` (`section_id`),
  KEY `study_materials_subject_id_foreign` (`subject_id`),
  KEY `study_materials_created_by_foreign` (`created_by`),
  KEY `study_materials_period_index` (`school_id`,`class_id`,`section_id`,`subject_id`),
  KEY `study_materials_recent_index` (`school_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `study_materials`
  ADD COLUMN IF NOT EXISTS `kind` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'material' AFTER `subject_id`;

CREATE INDEX IF NOT EXISTS `study_materials_kind_period_index` ON `study_materials` (`school_id`, `kind`, `class_id`);

/* -------------------------------------------------------------------------- */
/* Read-only checks: run these last and compare against what you expect.        */
/* -------------------------------------------------------------------------- */

SHOW COLUMNS FROM `homework` LIKE 'status';

SHOW COLUMNS FROM `homework` LIKE 'published_at';

SHOW INDEX FROM `homework` WHERE Key_name = 'homework_status_due_index';

SHOW TABLES LIKE 'homework_submissions';

SHOW TABLES LIKE 'student_leaves';

SHOW TABLES LIKE 'study_materials';

SELECT
  (SELECT COUNT(*) FROM `homework`) AS homework_rows,
  (SELECT COUNT(*) FROM `homework` WHERE `status` = 'published') AS published_rows,
  (SELECT COUNT(*) FROM `homework_submissions`) AS submission_rows,
  (SELECT COUNT(*) FROM `student_leaves`) AS leave_rows,
  (SELECT COUNT(*) FROM `study_materials`) AS material_rows;

/* -------------------------------------------------------------------------- */
/* Optional: the database-level links the migrated schema carries. Apply these  */
/* only after every table above exists, one statement at a time. A failure here */
/* is cosmetic, not a broken screen, so leave them out if they complain.        */
/*
ALTER TABLE `homework_submissions`
  ADD CONSTRAINT `homework_submissions_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  ADD CONSTRAINT `homework_submissions_homework_id_foreign` FOREIGN KEY (`homework_id`) REFERENCES `homework` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `homework_submissions_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `homework_submissions_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `homework_submissions_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `student_leaves`
  ADD CONSTRAINT `student_leaves_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  ADD CONSTRAINT `student_leaves_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_leaves_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_leaves_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `student_leaves_decided_by_foreign` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `study_materials`
  ADD CONSTRAINT `study_materials_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  ADD CONSTRAINT `study_materials_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  ADD CONSTRAINT `study_materials_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `study_materials_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  ADD CONSTRAINT `study_materials_subject_id_foreign` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `study_materials_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
*/
