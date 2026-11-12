-- ============================================================================
-- Applicant portal schema upgrade — CONTINUATION (production)
-- School MS / Dream Roots Millenium School
--
-- Use this file when applicant_portal_migrations.sql stops at:
--   #1060 Duplicate column name 'user_id'
-- (phpMyAdmin aborts an import at the first error, so steps 2-4 never ran.)
--
-- The three applicant columns already exist, so this file only finishes the
-- remaining work:
--   1. Creates `homework_submissions`
--   2. Creates `student_leaves`
--   3. Records the three migrations in Laravel's `migrations` table
--   4. Verifies columns, indexes, tables and migration rows
--
-- Additive only: no DROP, DELETE, TRUNCATE or data rewrite.
-- Safe to re-run. Set NAMES avoids collation errors when comparing literals.
-- ============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 0. Pre-flight (read-only). Expect all three columns to be listed.
--    If `photo_path` or `rejection_reason` is missing, run the matching
--    ALTER from section 5 before continuing.
-- ---------------------------------------------------------------------------
SHOW COLUMNS FROM `admissions`
    WHERE `Field` IN ('user_id', 'photo_path', 'rejection_reason');

SHOW INDEX FROM `admissions`
    WHERE `Key_name` IN ('admissions_user_id_foreign',
                         'admissions_school_user_index',
                         'admissions_applicant_session_index');

SHOW TABLES LIKE 'homework_submissions';
SHOW TABLES LIKE 'student_leaves';

-- ---------------------------------------------------------------------------
-- 1. homework_submissions: student submissions + staff review
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `homework_submissions` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `school_id` bigint(20) unsigned NOT NULL,
    `homework_id` bigint(20) unsigned NOT NULL,
    `student_id` bigint(20) unsigned NOT NULL,
    `enrollment_id` bigint(20) unsigned DEFAULT NULL,
    `status` varchar(20) NOT NULL DEFAULT 'submitted',
    `text_answer` text DEFAULT NULL,
    `attachment_path` varchar(255) DEFAULT NULL,
    `attachment_name` varchar(160) DEFAULT NULL,
    `submitted_at` timestamp NULL DEFAULT NULL,
    `resubmitted_at` timestamp NULL DEFAULT NULL,
    `marks` decimal(6,2) DEFAULT NULL,
    `feedback` text DEFAULT NULL,
    `reviewed_by` bigint(20) unsigned DEFAULT NULL,
    `reviewed_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `homework_submissions_student_unique` (`homework_id`, `student_id`),
    KEY `homework_submissions_student_id_foreign` (`student_id`),
    KEY `homework_submissions_enrollment_id_foreign` (`enrollment_id`),
    KEY `homework_submissions_reviewed_by_foreign` (`reviewed_by`),
    KEY `homework_submissions_student_index`
        (`school_id`, `student_id`, `submitted_at`),
    KEY `homework_submissions_status_index` (`homework_id`, `status`),
    CONSTRAINT `homework_submissions_enrollment_id_foreign`
        FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE SET NULL,
    CONSTRAINT `homework_submissions_homework_id_foreign`
        FOREIGN KEY (`homework_id`) REFERENCES `homework` (`id`) ON DELETE CASCADE,
    CONSTRAINT `homework_submissions_reviewed_by_foreign`
        FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `homework_submissions_school_id_foreign`
        FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `homework_submissions_student_id_foreign`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. student_leaves: portal leave applications + staff decisions
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_leaves` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `school_id` bigint(20) unsigned NOT NULL,
    `student_id` bigint(20) unsigned NOT NULL,
    `academic_session_id` bigint(20) unsigned DEFAULT NULL,
    `from_date` date NOT NULL,
    `to_date` date NOT NULL,
    `leave_type` varchar(30) NOT NULL DEFAULT 'casual',
    `reason` text NOT NULL,
    `contact_phone` varchar(30) DEFAULT NULL,
    `status` varchar(20) NOT NULL DEFAULT 'pending',
    `decision_notes` text DEFAULT NULL,
    `decided_by` bigint(20) unsigned DEFAULT NULL,
    `decided_at` timestamp NULL DEFAULT NULL,
    `created_by` bigint(20) unsigned DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `student_leaves_student_id_foreign` (`student_id`),
    KEY `student_leaves_academic_session_id_foreign` (`academic_session_id`),
    KEY `student_leaves_decided_by_foreign` (`decided_by`),
    KEY `student_leaves_created_by_foreign` (`created_by`),
    KEY `student_leaves_student_date_index` (`school_id`, `student_id`, `from_date`),
    KEY `student_leaves_status_index` (`school_id`, `status`, `created_at`),
    CONSTRAINT `student_leaves_academic_session_id_foreign`
        FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`) ON DELETE SET NULL,
    CONSTRAINT `student_leaves_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `student_leaves_decided_by_foreign`
        FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `student_leaves_school_id_foreign`
        FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `student_leaves_student_id_foreign`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. Migration bookkeeping (safe to re-run)
-- ---------------------------------------------------------------------------
SET @batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_28_000100_add_applicant_fields_to_admissions_table', @batch
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_10_28_000100_add_applicant_fields_to_admissions_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_28_120000_create_homework_submissions_table', @batch
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_10_28_120000_create_homework_submissions_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_28_120100_create_student_leaves_table', @batch
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_10_28_120100_create_student_leaves_table');

-- ---------------------------------------------------------------------------
-- 4. Verification (read-only). Expected: 3 columns, 3 indexes, 2 tables,
--    3 migrations.
-- ---------------------------------------------------------------------------
SHOW COLUMNS FROM `admissions`
    WHERE `Field` IN ('user_id', 'photo_path', 'rejection_reason');

SHOW INDEX FROM `admissions`
    WHERE `Key_name` IN ('admissions_user_id_foreign',
                         'admissions_school_user_index',
                         'admissions_applicant_session_index');

SHOW TABLES LIKE 'homework_submissions';
SHOW TABLES LIKE 'student_leaves';

SELECT `migration`, `batch`
FROM `migrations`
WHERE `migration` IN (
    '2026_10_28_000100_add_applicant_fields_to_admissions_table',
    '2026_10_28_120000_create_homework_submissions_table',
    '2026_10_28_120100_create_student_leaves_table'
)
ORDER BY `migration`;

-- ---------------------------------------------------------------------------
-- 5. Only if section 0/4 shows a missing column or index.
--    Run JUST the matching statement. Skip statements that already exist,
--    otherwise MySQL reports #1060 (duplicate column) or #1061 (duplicate key).
-- ---------------------------------------------------------------------------
-- ALTER TABLE `admissions` ADD COLUMN `photo_path` varchar(255) DEFAULT NULL AFTER `previous_school`;
-- ALTER TABLE `admissions` ADD COLUMN `rejection_reason` text DEFAULT NULL AFTER `notes`;
-- ALTER TABLE `admissions` ADD KEY `admissions_user_id_foreign` (`user_id`);
-- ALTER TABLE `admissions` ADD KEY `admissions_school_user_index` (`school_id`, `user_id`);
-- ALTER TABLE `admissions` ADD KEY `admissions_applicant_session_index` (`school_id`, `user_id`, `academic_session_id`);
-- ALTER TABLE `admissions` ADD CONSTRAINT `admissions_user_id_foreign`
--     FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
