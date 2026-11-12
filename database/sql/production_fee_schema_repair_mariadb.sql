/*
 * EduNest production fee-schema repair — MariaDB-safe, one paste, re-runnable.
 *
 * Covers the four fee migrations that the live site has not run yet:
 *   2026_10_29_130000  fee_invoices active-billing uniqueness
 *   2026_10_30_100000  settlement waiver columns
 *   2026_10_30_140000  fee configuration categories, dates and student assignments
 *   2026_10_30_150000  payment gateway transaction columns
 *
 * MariaDB accepts IF NOT EXISTS on columns, on CREATE INDEX and on CREATE TABLE,
 * so this whole body can be pasted and run repeatedly. FOREIGN KEY additions and
 * the two UNIQUE indexes are the only statements that cannot be guarded: they sit
 * at the very end, after the duplicate checks, so a duplicate-key failure there
 * never stops the parts above it.
 *
 * Nothing here drops or rewrites financial data. Block comments only, so a paste
 * that loses its newlines still parses.
 */

CREATE TABLE IF NOT EXISTS `fee_payment_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `fee_payment_id` bigint(20) unsigned NOT NULL,
  `fee_invoice_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `waived_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fee_payment_allocations_unique` (`fee_payment_id`,`fee_invoice_id`),
  KEY `fee_payment_allocations_fee_invoice_id_foreign` (`fee_invoice_id`),
  KEY `fee_allocations_invoice_index` (`school_id`,`fee_invoice_id`),
  CONSTRAINT `fee_payment_allocations_fee_invoice_id_foreign` FOREIGN KEY (`fee_invoice_id`) REFERENCES `fee_invoices` (`id`),
  CONSTRAINT `fee_payment_allocations_fee_payment_id_foreign` FOREIGN KEY (`fee_payment_id`) REFERENCES `fee_payments` (`id`),
  CONSTRAINT `fee_payment_allocations_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fee_student_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `fee_type_id` bigint(20) unsigned NOT NULL,
  `academic_session_id` bigint(20) unsigned NOT NULL,
  `student_id` bigint(20) unsigned NOT NULL,
  `enrollment_id` bigint(20) unsigned DEFAULT NULL,
  `class_id` bigint(20) unsigned DEFAULT NULL,
  `section_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(14,2) DEFAULT NULL,
  `starts_on` date DEFAULT NULL,
  `ends_on` date DEFAULT NULL,
  `batch` varchar(36) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `active_assignment_key` varchar(96) CHARACTER SET ascii COLLATE ascii_general_ci GENERATED ALWAYS AS (case when `is_active` = 1 then concat(`school_id`,':',`student_id`,':',`fee_type_id`,':',`academic_session_id`) end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fee_assignments_active_unique` (`active_assignment_key`),
  KEY `fee_student_assignments_fee_type_id_foreign` (`fee_type_id`),
  KEY `fee_student_assignments_academic_session_id_foreign` (`academic_session_id`),
  KEY `fee_student_assignments_student_id_foreign` (`student_id`),
  KEY `fee_student_assignments_enrollment_id_foreign` (`enrollment_id`),
  KEY `fee_student_assignments_class_id_foreign` (`class_id`),
  KEY `fee_student_assignments_section_id_foreign` (`section_id`),
  KEY `fee_student_assignments_created_by_foreign` (`created_by`),
  KEY `fee_assignments_student_index` (`school_id`,`academic_session_id`,`student_id`),
  KEY `fee_assignments_batch_index` (`school_id`,`batch`),
  CONSTRAINT `fee_student_assignments_academic_session_id_foreign` FOREIGN KEY (`academic_session_id`) REFERENCES `academic_sessions` (`id`),
  CONSTRAINT `fee_student_assignments_class_id_foreign` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_student_assignments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_student_assignments_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_student_assignments_fee_type_id_foreign` FOREIGN KEY (`fee_type_id`) REFERENCES `fee_types` (`id`),
  CONSTRAINT `fee_student_assignments_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`),
  CONSTRAINT `fee_student_assignments_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fee_student_assignments_student_id_foreign` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_gateway_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `payment_key` varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `student_id` bigint(20) unsigned DEFAULT NULL,
  `fee_invoice_id` bigint(20) unsigned DEFAULT NULL,
  `fee_payment_id` bigint(20) unsigned DEFAULT NULL,
  `gateway` varchar(40) NOT NULL,
  `gateway_transaction_id` varchar(120) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `currency` char(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'BDT',
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `verified_at` timestamp NULL DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gateway_transactions_unique` (`gateway`,`gateway_transaction_id`),
  UNIQUE KEY `gateway_transactions_payment_key_unique` (`school_id`,`payment_key`),
  KEY `payment_gateway_transactions_fee_invoice_id_foreign` (`fee_invoice_id`),
  KEY `payment_gateway_transactions_fee_payment_id_foreign` (`fee_payment_id`),
  KEY `gateway_transactions_status_index` (`school_id`,`status`),
  KEY `gateway_transactions_student_fk` (`student_id`),
  CONSTRAINT `payment_gateway_transactions_fee_invoice_id_foreign` FOREIGN KEY (`fee_invoice_id`) REFERENCES `fee_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_gateway_transactions_fee_payment_id_foreign` FOREIGN KEY (`fee_payment_id`) REFERENCES `fee_payments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payment_gateway_transactions_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `fee_invoices`
  ADD COLUMN IF NOT EXISTS `waived_total` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `due_total`;

ALTER TABLE `fee_payment_allocations`
  ADD COLUMN IF NOT EXISTS `waived_amount` decimal(14,2) NOT NULL DEFAULT 0.00 AFTER `amount`;

ALTER TABLE `fee_types`
  ADD COLUMN IF NOT EXISTS `category` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `description`,
  ADD COLUMN IF NOT EXISTS `starts_on` date DEFAULT NULL AFTER `frequency`,
  ADD COLUMN IF NOT EXISTS `ends_on` date DEFAULT NULL AFTER `starts_on`;

ALTER TABLE `fee_structures`
  ADD COLUMN IF NOT EXISTS `starts_on` date DEFAULT NULL AFTER `due_day`,
  ADD COLUMN IF NOT EXISTS `ends_on` date DEFAULT NULL AFTER `starts_on`;

ALTER TABLE `fee_invoices`
  ADD COLUMN IF NOT EXISTS `active_billing_key` varchar(96) CHARACTER SET ascii COLLATE ascii_general_ci
    GENERATED ALWAYS AS (case when `status` <> 'cancelled' and `billing_period` is not null
      then concat(`school_id`,':',`student_id`,':',`academic_session_id`,':',`billing_period`) end) STORED;

ALTER TABLE `fee_student_assignments`
  ADD COLUMN IF NOT EXISTS `active_assignment_key` varchar(96) CHARACTER SET ascii COLLATE ascii_general_ci
    GENERATED ALWAYS AS (case when `is_active` = 1
      then concat(`school_id`,':',`student_id`,':',`fee_type_id`,':',`academic_session_id`) end) STORED;

ALTER TABLE `payment_gateway_transactions`
  ADD COLUMN IF NOT EXISTS `payment_key` varchar(64) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL AFTER `school_id`,
  ADD COLUMN IF NOT EXISTS `student_id` bigint(20) unsigned DEFAULT NULL AFTER `payment_key`,
  ADD COLUMN IF NOT EXISTS `currency` char(3) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL DEFAULT 'BDT' AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `verified_at` timestamp NULL DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `failure_reason` text DEFAULT NULL AFTER `verified_at`;

ALTER TABLE `payment_gateway_transactions`
  MODIFY COLUMN `gateway_transaction_id` varchar(120) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL;

CREATE INDEX IF NOT EXISTS `gateway_transactions_status_index` ON `payment_gateway_transactions` (`school_id`,`status`);
CREATE INDEX IF NOT EXISTS `gateway_transactions_student_fk` ON `payment_gateway_transactions` (`student_id`);
CREATE INDEX IF NOT EXISTS `fee_assignments_student_index` ON `fee_student_assignments` (`school_id`,`academic_session_id`,`student_id`);
CREATE INDEX IF NOT EXISTS `fee_assignments_batch_index` ON `fee_student_assignments` (`school_id`,`batch`);

/*
 * Duplicate checks. Read-only, they cannot fail. Both must return ZERO rows
 * before the two unique indexes at the bottom are added, otherwise that index
 * statement stops with #1062 Duplicate entry.
 */

SELECT school_id, student_id, academic_session_id, billing_period, COUNT(*) AS invoices
  FROM `fee_invoices`
 WHERE `status` <> 'cancelled' AND `billing_period` IS NOT NULL
 GROUP BY school_id, student_id, academic_session_id, billing_period
HAVING COUNT(*) > 1;

SELECT school_id, student_id, fee_type_id, academic_session_id, COUNT(*) AS assignments
  FROM `fee_student_assignments`
 WHERE `is_active` = 1
 GROUP BY school_id, student_id, fee_type_id, academic_session_id
HAVING COUNT(*) > 1;

SELECT COUNT(*) AS cash_lines FROM `cash_transactions`;
SELECT COUNT(*) AS payments FROM `fee_payments`;

/*
 * LAST, AND ONLY IF BOTH CHECKS ABOVE RETURNED ZERO ROWS.
 * MariaDB has no "ADD UNIQUE INDEX IF NOT EXISTS" that skips an existing key
 * without also validating the data, so these two can raise #1061 (already there)
 * or #1062 (duplicates). An #1061 here means the index already exists: ignore it.
 */

CREATE UNIQUE INDEX IF NOT EXISTS `fee_invoices_active_billing_unique` ON `fee_invoices` (`active_billing_key`);
CREATE UNIQUE INDEX IF NOT EXISTS `fee_assignments_active_unique` ON `fee_student_assignments` (`active_assignment_key`);
CREATE UNIQUE INDEX IF NOT EXISTS `gateway_transactions_payment_key_unique` ON `payment_gateway_transactions` (`school_id`,`payment_key`);

/*
 * OPTIONAL FOREIGN KEY, NOT PART OF THE PASTE.
 * gateway_transactions_student_fk is only needed if payment_gateway_transactions
 * was created from scratch by this script; CREATE TABLE above left it out because
 * MariaDB refuses IF NOT EXISTS on constraints. Run it alone and treat a duplicate
 * error as "already present":
 *
 * ALTER TABLE `payment_gateway_transactions` ADD CONSTRAINT `gateway_transactions_student_fk` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);
 */
