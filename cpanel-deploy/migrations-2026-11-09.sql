-- =============================================================================
-- Migration SQL for Recent Features (SEO + Face Recognition Attendance Rules)
-- Dream Roots Millennium School
-- Generated: 2026-11-09
-- Run this in phpMyAdmin on cPanel if you do not run `php artisan migrate` via SSH
-- =============================================================================

-- 1. Create SEO Settings Table
CREATE TABLE IF NOT EXISTS `seo_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(255) NOT NULL,
  `value` TEXT NULL,
  `type` ENUM('string', 'integer', 'boolean', 'json') NOT NULL DEFAULT 'string',
  `group_name` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seo_settings_key_unique` (`key`),
  KEY `seo_settings_group_name_index` (`group_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create Attendance Rules Table
CREATE TABLE IF NOT EXISTS `attendance_rules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(255) NOT NULL DEFAULT 'Default Attendance Rules',
  `start_time` TIME NOT NULL DEFAULT '08:30:00',
  `end_time` TIME NOT NULL DEFAULT '15:00:00',
  `late_threshold_minutes` INT NOT NULL DEFAULT 15,
  `very_late_threshold_minutes` INT NOT NULL DEFAULT 60,
  `window_hours` DECIMAL(4,2) NOT NULL DEFAULT 12.00,
  `duplicate_interval_seconds` INT NOT NULL DEFAULT 300,
  `allow_holiday_attendance` TINYINT(1) NOT NULL DEFAULT 0,
  `skip_weekends` TINYINT(1) NOT NULL DEFAULT 1,
  `working_days` JSON NULL,
  `send_sms_on_present` TINYINT(1) NOT NULL DEFAULT 1,
  `send_sms_on_late` TINYINT(1) NOT NULL DEFAULT 0,
  `send_sms_to_guardian` TINYINT(1) NOT NULL DEFAULT 1,
  `max_checks_per_day` INT NOT NULL DEFAULT 10,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `priority` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `attendance_rules_school_id_is_active_index` (`school_id`, `is_active`),
  UNIQUE KEY `attendance_rules_school_unique` (`school_id`),
  CONSTRAINT `attendance_rules_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
