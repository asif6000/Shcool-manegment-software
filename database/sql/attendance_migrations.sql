-- ============================================================================
-- Smart Attendance System — production schema upgrade
-- School MS / Dream Roots Millenium School
--
-- What this does (additive only):
--   1. Adds method/correction columns to `student_attendances`
--   2. Creates `attendance_devices`
--   3. Creates `attendance_events`
--   4. Creates `student_attendance_cards`
--   5. Creates `attendance_device_users`
--   6. Adds the `attendance.qr.manage` and `attendance.devices.manage` permissions
--   7. Records the five migrations in Laravel's `migrations` table
--
-- cPanel note:
--   This file deliberately avoids `information_schema` and PREPARE/EXECUTE,
--   because some cPanel MySQL users get "#1044 Access denied ... information_schema".
--   Existence checks therefore use SHOW statements (read-only output).
--
-- Safety:
--   * Take a full database backup BEFORE importing (phpMyAdmin -> Export).
--   * No DROP, DELETE, TRUNCATE or data rewrite anywhere in this file.
--   * Run once. Step 1 is the only non-repeatable part:
--       - First run: if `student_attendances` has no `source` column, run step 1.
--       - If step 1 reports "#1060 Duplicate column name 'source'", the upgrade
--         was already applied: skip step 1 and continue with step 2 onward.
--   * Do NOT run `migrate:fresh`, `db:seed` or any reset command in production.
--   * Do NOT skip the backup even if the database "looks empty".
--
-- Use either this file OR `php artisan migrate --force` (not both required,
-- but running both is harmless because the rows are recorded at the end).
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 0. Pre-flight (read-only). Note what is already there.
--    Empty result = object does not exist yet.
-- ---------------------------------------------------------------------------
SHOW COLUMNS FROM `student_attendances` LIKE 'source';
SHOW TABLES LIKE 'attendance_devices';
SHOW TABLES LIKE 'attendance_events';
SHOW TABLES LIKE 'student_attendance_cards';
SHOW TABLES LIKE 'attendance_device_users';

-- ---------------------------------------------------------------------------
-- 1. student_attendances: method source + correction workflow
--    SKIP THIS STEP if step 0 already returned the `source` column.
-- ---------------------------------------------------------------------------
ALTER TABLE `student_attendances`
    ADD COLUMN `source` varchar(20) NOT NULL DEFAULT 'manual' AFTER `status`,
    ADD COLUMN `source_ref` varchar(255) DEFAULT NULL AFTER `source`,
    ADD COLUMN `locked_at` timestamp NULL DEFAULT NULL AFTER `remarks`,
    ADD COLUMN `correction_reason` varchar(255) DEFAULT NULL AFTER `locked_at`,
    ADD COLUMN `corrected_by` bigint(20) unsigned DEFAULT NULL AFTER `correction_reason`,
    ADD COLUMN `corrected_at` timestamp NULL DEFAULT NULL AFTER `corrected_by`,
    ADD KEY `student_attendance_source_date_index` (`school_id`, `source`, `attendance_date`),
    ADD KEY `student_attendances_corrected_by_foreign` (`corrected_by`),
    ADD CONSTRAINT `student_attendances_corrected_by_foreign`
        FOREIGN KEY (`corrected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- 2. attendance_devices: registered terminals / adapters
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendance_devices` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `school_id` bigint(20) unsigned NOT NULL,
    `name` varchar(255) NOT NULL,
    `kind` varchar(20) NOT NULL,
    `driver` varchar(255) DEFAULT NULL,
    `serial` varchar(255) DEFAULT NULL,
    `location` varchar(255) DEFAULT NULL,
    `secret_hash` varchar(64) DEFAULT NULL,
    `status` varchar(20) NOT NULL DEFAULT 'not_configured',
    `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `last_seen_at` timestamp NULL DEFAULT NULL,
    `last_synced_at` timestamp NULL DEFAULT NULL,
    `last_error` varchar(255) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `attendance_devices_kind_status_index` (`school_id`, `kind`, `status`),
    KEY `attendance_devices_serial_index` (`school_id`, `serial`),
    CONSTRAINT `attendance_devices_school_id_foreign`
        FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. attendance_events: idempotent device/QR event log
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendance_events` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `school_id` bigint(20) unsigned NOT NULL,
    `attendance_device_id` bigint(20) unsigned DEFAULT NULL,
    `student_id` bigint(20) unsigned DEFAULT NULL,
    `student_attendance_id` bigint(20) unsigned DEFAULT NULL,
    `source` varchar(20) NOT NULL,
    `external_ref` varchar(191) NOT NULL,
    `device_user_id` varchar(255) DEFAULT NULL,
    `occurred_at` datetime NOT NULL,
    `received_at` datetime NOT NULL,
    `status` varchar(20) NOT NULL,
    `reason` varchar(255) DEFAULT NULL,
    `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `attendance_events_external_unique` (`school_id`, `source`, `external_ref`),
    KEY `attendance_events_attendance_device_id_foreign` (`attendance_device_id`),
    KEY `attendance_events_student_id_foreign` (`student_id`),
    KEY `attendance_events_student_attendance_id_foreign` (`student_attendance_id`),
    KEY `attendance_events_status_time_index` (`school_id`, `status`, `occurred_at`),
    KEY `attendance_events_student_time_index` (`school_id`, `student_id`, `occurred_at`),
    CONSTRAINT `attendance_events_attendance_device_id_foreign`
        FOREIGN KEY (`attendance_device_id`) REFERENCES `attendance_devices` (`id`) ON DELETE SET NULL,
    CONSTRAINT `attendance_events_school_id_foreign`
        FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
    CONSTRAINT `attendance_events_student_attendance_id_foreign`
        FOREIGN KEY (`student_attendance_id`) REFERENCES `student_attendances` (`id`) ON DELETE SET NULL,
    CONSTRAINT `attendance_events_student_id_foreign`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. student_attendance_cards: QR card tokens (SHA-256 hash only, never raw)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_attendance_cards` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `school_id` bigint(20) unsigned NOT NULL,
    `student_id` bigint(20) unsigned NOT NULL,
    `token_hash` varchar(64) NOT NULL,
    `issued_by` bigint(20) unsigned DEFAULT NULL,
    `issued_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `revoked_by` bigint(20) unsigned DEFAULT NULL,
    `revoked_at` timestamp NULL DEFAULT NULL,
    `revoke_reason` varchar(255) DEFAULT NULL,
    `last_scanned_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `student_attendance_cards_token_hash_unique` (`token_hash`),
    KEY `student_attendance_cards_student_id_foreign` (`student_id`),
    KEY `student_attendance_cards_issued_by_foreign` (`issued_by`),
    KEY `student_attendance_cards_revoked_by_foreign` (`revoked_by`),
    KEY `attendance_cards_student_active_index` (`school_id`, `student_id`, `revoked_at`),
    CONSTRAINT `student_attendance_cards_issued_by_foreign`
        FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `student_attendance_cards_revoked_by_foreign`
        FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `student_attendance_cards_school_id_foreign`
        FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `student_attendance_cards_student_id_foreign`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5. attendance_device_users: device user ID -> student mapping
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendance_device_users` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `school_id` bigint(20) unsigned NOT NULL,
    `attendance_device_id` bigint(20) unsigned NOT NULL,
    `student_id` bigint(20) unsigned NOT NULL,
    `device_user_id` varchar(191) NOT NULL,
    `created_by` bigint(20) unsigned DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `attendance_device_users_user_unique` (`attendance_device_id`, `device_user_id`),
    UNIQUE KEY `attendance_device_users_student_unique` (`attendance_device_id`, `student_id`),
    KEY `attendance_device_users_school_id_foreign` (`school_id`),
    KEY `attendance_device_users_student_id_foreign` (`student_id`),
    KEY `attendance_device_users_created_by_foreign` (`created_by`),
    CONSTRAINT `attendance_device_users_attendance_device_id_foreign`
        FOREIGN KEY (`attendance_device_id`) REFERENCES `attendance_devices` (`id`) ON DELETE CASCADE,
    CONSTRAINT `attendance_device_users_created_by_foreign`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `attendance_device_users_school_id_foreign`
        FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `attendance_device_users_student_id_foreign`
        FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 6. Permissions (safe to re-run)
--    attendance.qr.manage      -> school_admin, principal
--    attendance.devices.manage -> school_admin
-- ---------------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'attendance.qr.manage', 'web', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions`
    WHERE `name` = 'attendance.qr.manage' AND `guard_name` = 'web'
);

INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`)
SELECT 'attendance.devices.manage', 'web', NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `permissions`
    WHERE `name` = 'attendance.devices.manage' AND `guard_name` = 'web'
);

INSERT IGNORE INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
CROSS JOIN `roles` r
WHERE p.`name` = 'attendance.qr.manage'
  AND p.`guard_name` = 'web'
  AND r.`name` IN ('school_admin', 'principal')
  AND r.`guard_name` = 'web';

INSERT IGNORE INTO `role_has_permissions` (`permission_id`, `role_id`)
SELECT p.`id`, r.`id`
FROM `permissions` p
CROSS JOIN `roles` r
WHERE p.`name` = 'attendance.devices.manage'
  AND p.`guard_name` = 'web'
  AND r.`name` = 'school_admin'
  AND r.`guard_name` = 'web';

-- ---------------------------------------------------------------------------
-- 7. Migration bookkeeping
--    Marks the five migrations as ran so a later `artisan migrate` is a no-op.
-- ---------------------------------------------------------------------------
SET @batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_28_130000_add_method_columns_to_student_attendances', @batch
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_10_28_130000_add_method_columns_to_student_attendances');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_28_130100_create_attendance_devices_table', @batch
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_10_28_130100_create_attendance_devices_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_28_130200_create_attendance_events_table', @batch
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_10_28_130200_create_attendance_events_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_28_130300_create_student_attendance_cards_table', @batch
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_10_28_130300_create_student_attendance_cards_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_28_130400_create_attendance_device_users_table', @batch
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_10_28_130400_create_attendance_device_users_table');

-- ---------------------------------------------------------------------------
-- 8. Verification (read-only). Expected: 6 columns, 4 tables, 2 permissions,
--    3 role assignments, 5 migrations.
-- ---------------------------------------------------------------------------
SHOW COLUMNS FROM `student_attendances`
    WHERE `Field` IN ('source', 'source_ref', 'locked_at', 'correction_reason',
                      'corrected_by', 'corrected_at');

SHOW TABLES LIKE 'attendance_devices';
SHOW TABLES LIKE 'attendance_events';
SHOW TABLES LIKE 'student_attendance_cards';
SHOW TABLES LIKE 'attendance_device_users';

SELECT `name`, `guard_name`
FROM `permissions`
WHERE `name` IN ('attendance.qr.manage', 'attendance.devices.manage');

SELECT r.`name` AS role_name, p.`name` AS permission_name
FROM `role_has_permissions` rp
JOIN `roles` r ON r.`id` = rp.`role_id`
JOIN `permissions` p ON p.`id` = rp.`permission_id`
WHERE p.`name` IN ('attendance.qr.manage', 'attendance.devices.manage')
ORDER BY p.`name`, r.`name`;

SELECT `migration`, `batch`
FROM `migrations`
WHERE `migration` LIKE '2026_10_28_1300%'
ORDER BY `migration`;
