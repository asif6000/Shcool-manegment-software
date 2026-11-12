-- ============================================================================
-- Applicant portal + homework/leave schema upgrade (production)
-- School MS / Dream Roots Millenium School
--
-- Fixes the live 500 on /admissions:
--   SQLSTATE[42S22]: Unknown column 'admissions.user_id'
--
-- What this does (additive only):
--   1. Adds `user_id`, `photo_path`, `rejection_reason` to `admissions`
--      (plus the applicant lookup indexes and the users foreign key)
--   2. Creates `homework_submissions`
--   3. Creates `student_leaves`
--   4. Records the three migrations in Laravel's `migrations` table
--
-- cPanel note:
--   Avoids `information_schema` and PREPARE/EXECUTE (cPanel users often get
--   "#1044 Access denied ... information_schema"). Pre-flight uses SHOW only.
--
-- Safety:
--   * Take a full database backup BEFORE importing (phpMyAdmin -> Export).
--   * No DROP, DELETE, TRUNCATE or data rewrite anywhere in this file.
--   * Step 1 is the only non-repeatable part. If it reports
--     "#1060 Duplicate column name" or "#1061 Duplicate key name", the columns
--     already exist. phpMyAdmin stops the whole import at that error, so the
--     remaining steps never run: import
--     `applicant_portal_migrations_continue.sql` instead (it finishes steps
--     2-4 and verifies the result).
--   * Steps 2-4 are safe to re-run.
--   * Do NOT run `migrate:fresh`, `db:seed` or any reset command in production.
--
-- This file is equivalent to running:
--   php artisan migrate --force
-- for these three migrations only.
-- ============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 0. Pre-flight (read-only). Empty results = objects do not exist yet.
-- ---------------------------------------------------------------------------
SHOW COLUMNS FROM `admissions`
    WHERE `Field` IN ('user_id', 'photo_path', 'rejection_reason');

SHOW TABLES LIKE 'homework_submissions';
SHOW TABLES LIKE 'student_leaves';

SELECT `migration`, `batch`
FROM `migrations`
WHERE `migration` IN (
    '2026_10_28_000100_add_applicant_fields_to_admissions_table',
    '2026_10_28_120000_create_homework_submissions_table',
    '2026_10_28_120100_create_student_leaves_table'
);

-- ---------------------------------------------------------------------------
-- 1. admissions: link the submitting portal account + applicant fields
--    SKIP THIS STEP if step 0 already returned the three columns.
-- ---------------------------------------------------------------------------
ALTER TABLE `admissions`
    ADD COLUMN `user_id` bigint(20) unsigned DEFAULT NULL AFTER `school_id`,
    ADD COLUMN `photo_path` varchar(255) DEFAULT NULL AFTER `previous_school`,
    ADD COLUMN `rejection_reason` text DEFAULT NULL AFTER `notes`,
    ADD KEY `admissions_user_id_foreign` (`user_id`),
    ADD KEY `admissions_school_user_index` (`school_id`, `user_id`),
    ADD KEY `admissions_applicant_session_index`
        (`school_id`, `user_id`, `academic_session_id`),
    ADD CONSTRAINT `admissions_user_id_foreign`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- 2. homework_submissions: student submissions + staff review
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
-- 3. student_leaves: portal leave applications + staff decisions
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
-- 4. Migration bookkeeping (safe to re-run)
--    Marks the three migrations as ran so a later `artisan migrate` is a no-op.
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
-- 5. Verification (read-only). Expected: 3 columns, 2 tables, 3 migrations.
-- ---------------------------------------------------------------------------
SHOW COLUMNS FROM `admissions`
    WHERE `Field` IN ('user_id', 'photo_path', 'rejection_reason');

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
