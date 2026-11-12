/*
 * DRMS production (irvrzogs_drms2026) — Teacher Assignment module.
 *
 * What this adds:
 *   teacher_assignments, one row per "this teacher may teach this class, this
 *   section and this subject in this session". The assignment screen writes it
 *   and TeachingScope reads it beside class_routines.
 *
 * Why a new table rather than an existing one:
 *   class_routines is the timetable: a row needs a day_of_week and a start_time
 *   and is unique per section/day/start. An office assignment has no period, so
 *   it cannot be a routine row. class_teachers only stores a class, so it cannot
 *   express section or subject either. Nothing else on the live schema does.
 *
 * Safety:
 *   - CREATE TABLE IF NOT EXISTS only. No existing table is altered, renamed or
 *     dropped, and no row of another table is touched.
 *   - Re-runnable: run it twice and the second run is a set of harmless SELECTs.
 *   - Foreign keys are RESTRICT on delete so a teacher, class or subject cannot
 *     be deleted out from under an assignment by accident.
 *   - Every statement names irvrzogs_drms2026 explicitly, so phpMyAdmin's
 *     currently clicked database does not matter.
 *
 * Run it in cPanel -> phpMyAdmin -> SQL tab, paste the whole file, press Go.
 * No DELIMITER and no stored procedure are used.
 */

/* -------------------------------------------------------------------------- */
/* 1. Before: read only. On a database that has not been migrated expect       */
/*    table_present = 0 and rows = 0.                                          */
/* -------------------------------------------------------------------------- */

SELECT
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'irvrzogs_drms2026'
      AND TABLE_NAME = 'teacher_assignments') AS table_present,
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'irvrzogs_drms2026'
      AND TABLE_NAME IN ('teachers', 'classes', 'sections', 'subjects', 'academic_sessions', 'schools')) AS prerequisite_tables;

/* -------------------------------------------------------------------------- */
/* 2. The assignment record. Same columns and keys as                       */
/*    2026_11_06_090000_create_teacher_assignments_table.php.                */
/* -------------------------------------------------------------------------- */

CREATE TABLE IF NOT EXISTS `irvrzogs_drms2026`.`teacher_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` BIGINT UNSIGNED NOT NULL,
  `academic_session_id` BIGINT UNSIGNED NOT NULL,
  `teacher_id` BIGINT UNSIGNED NOT NULL,
  `class_id` BIGINT UNSIGNED NOT NULL,
  `section_id` BIGINT UNSIGNED NOT NULL,
  `subject_id` BIGINT UNSIGNED NOT NULL,
  `assigned_on` DATE NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'active',
  `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_assignments_unique`
    (`school_id`, `academic_session_id`, `teacher_id`, `class_id`, `section_id`, `subject_id`),
  KEY `teacher_assignments_teacher_index`
    (`school_id`, `academic_session_id`, `teacher_id`),
  KEY `teacher_assignments_period_index`
    (`school_id`, `academic_session_id`, `class_id`, `section_id`, `subject_id`),
  CONSTRAINT `fk_teacher_assignments_school`
    FOREIGN KEY (`school_id`) REFERENCES `irvrzogs_drms2026`.`schools` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_teacher_assignments_session`
    FOREIGN KEY (`academic_session_id`) REFERENCES `irvrzogs_drms2026`.`academic_sessions` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_teacher_assignments_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `irvrzogs_drms2026`.`teachers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_teacher_assignments_class`
    FOREIGN KEY (`class_id`) REFERENCES `irvrzogs_drms2026`.`classes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_teacher_assignments_section`
    FOREIGN KEY (`section_id`) REFERENCES `irvrzogs_drms2026`.`sections` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_teacher_assignments_subject`
    FOREIGN KEY (`subject_id`) REFERENCES `irvrzogs_drms2026`.`subjects` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_teacher_assignments_creator`
    FOREIGN KEY (`created_by`) REFERENCES `irvrzogs_drms2026`.`users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* -------------------------------------------------------------------------- */
/* 3. After: read only. Expect table_present = 1, index_count = 10 (primary,  */
/*    three named keys and the six foreign-key keys), foreign_key_count = 7,  */
/*    assignment_rows = 0 on a fresh install.                                 */
/* -------------------------------------------------------------------------- */

SELECT
  (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'irvrzogs_drms2026'
      AND TABLE_NAME = 'teacher_assignments') AS table_present,
  (SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'irvrzogs_drms2026'
      AND TABLE_NAME = 'teacher_assignments') AS index_count,
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = 'irvrzogs_drms2026'
      AND TABLE_NAME = 'teacher_assignments'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY') AS foreign_key_count,
  (SELECT COUNT(*) FROM irvrzogs_drms2026.`teacher_assignments`) AS assignment_rows;

/*
 * Rollback — only if the code is taken back as well. Removing the table removes
 * only assignment records; homework, classwork and lessons already saved stay
 * exactly where they are, because none of them points at this table.
 *
 * DROP TABLE IF EXISTS irvrzogs_drms2026.`teacher_assignments`;
 */
