/*
 * EduNest production schema repair — MariaDB-safe, one paste, cannot error on re-run.
 *
 * The live host (dreamrootsmilleniumschool.com) runs MariaDB 10.x, so every DDL
 * statement here uses IF NOT EXISTS: MariaDB accepts that for columns and for
 * CREATE INDEX, which is why this file can be pasted and run again and again.
 * FOREIGN KEY constraints are the one thing MariaDB will not guard, so they are
 * left out of the runnable body and parked in the final block comment.
 *
 * Comments use block form only: a line comment would be swallowed if the SQL box
 * pastes the file without its newlines.
 *
 * Nothing here drops a column, row or table. Take a backup first.
 * Preferred fix remains "php artisan migrate --force", which does the same work.
 */

CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bank_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `branch` varchar(120) COLLATE utf8mb4_unicode_ci NULL,
  `opening_balance` decimal(14,2) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_accounts_school_account_unique` (`school_id`,`account_no`),
  CONSTRAINT `bank_accounts_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vouchers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `school_id` bigint(20) unsigned NOT NULL,
  `voucher_no` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `voucher_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL,
  `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash',
  `bank_account_id` bigint(20) unsigned NULL,
  `category` varchar(80) COLLATE utf8mb4_unicode_ci NULL,
  `payee` varchar(120) COLLATE utf8mb4_unicode_ci NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_no` varchar(100) COLLATE utf8mb4_unicode_ci NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'posted',
  `cash_transaction_id` bigint(20) unsigned NULL,
  `created_by` bigint(20) unsigned NULL,
  `cancelled_by` bigint(20) unsigned NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancel_reason` text COLLATE utf8mb4_unicode_ci NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vouchers_school_no_unique` (`school_id`,`voucher_no`),
  KEY `vouchers_date_type_index` (`school_id`,`voucher_date`,`type`),
  KEY `vouchers_bank_account_id_foreign` (`bank_account_id`),
  KEY `vouchers_cash_transaction_id_foreign` (`cash_transaction_id`),
  KEY `vouchers_created_by_foreign` (`created_by`),
  KEY `vouchers_cancelled_by_foreign` (`cancelled_by`),
  CONSTRAINT `vouchers_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vouchers_cash_transaction_id_foreign` FOREIGN KEY (`cash_transaction_id`) REFERENCES `cash_transactions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vouchers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vouchers_cancelled_by_foreign` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vouchers_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `schools` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `cash_transactions`
  ADD COLUMN IF NOT EXISTS `channel` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash' AFTER `type`,
  ADD COLUMN IF NOT EXISTS `bank_account_id` bigint(20) unsigned NULL AFTER `channel`;

ALTER TABLE `fee_payments`
  ADD COLUMN IF NOT EXISTS `verification_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_required' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `verified_by` bigint(20) unsigned NULL AFTER `verification_status`,
  ADD COLUMN IF NOT EXISTS `verified_at` timestamp NULL AFTER `verified_by`,
  ADD COLUMN IF NOT EXISTS `rejection_reason` text COLLATE utf8mb4_unicode_ci NULL AFTER `verified_at`,
  ADD COLUMN IF NOT EXISTS `bank_account_id` bigint(20) unsigned NULL AFTER `rejection_reason`;

ALTER TABLE `expenses`
  ADD COLUMN IF NOT EXISTS `bank_account_id` bigint(20) unsigned NULL AFTER `payment_method`,
  ADD COLUMN IF NOT EXISTS `rejection_reason` text COLLATE utf8mb4_unicode_ci NULL AFTER `cancel_reason`;

ALTER TABLE `fee_invoice_discounts`
  ADD COLUMN IF NOT EXISTS `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'applied' AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `requested_by` bigint(20) unsigned NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `approved_by` bigint(20) unsigned NULL AFTER `requested_by`,
  ADD COLUMN IF NOT EXISTS `approved_at` timestamp NULL AFTER `approved_by`,
  ADD COLUMN IF NOT EXISTS `rejection_reason` text COLLATE utf8mb4_unicode_ci NULL AFTER `approved_at`;

CREATE INDEX IF NOT EXISTS `cash_transactions_bank_account_id_foreign` ON `cash_transactions` (`bank_account_id`);
CREATE INDEX IF NOT EXISTS `cash_transactions_channel_date_index` ON `cash_transactions` (`school_id`,`channel`,`transaction_date`);
CREATE INDEX IF NOT EXISTS `fee_payments_verified_by_foreign` ON `fee_payments` (`verified_by`);
CREATE INDEX IF NOT EXISTS `fee_payments_bank_account_id_foreign` ON `fee_payments` (`bank_account_id`);
CREATE INDEX IF NOT EXISTS `fee_payments_verification_index` ON `fee_payments` (`school_id`,`verification_status`);
CREATE INDEX IF NOT EXISTS `expenses_bank_account_id_foreign` ON `expenses` (`bank_account_id`);
CREATE INDEX IF NOT EXISTS `fee_invoice_discounts_requested_by_foreign` ON `fee_invoice_discounts` (`requested_by`);
CREATE INDEX IF NOT EXISTS `fee_invoice_discounts_approved_by_foreign` ON `fee_invoice_discounts` (`approved_by`);
CREATE INDEX IF NOT EXISTS `fee_invoice_discounts_status_index` ON `fee_invoice_discounts` (`school_id`,`status`);

/*
 * Every cash line inherits its channel from the source record's payment method,
 * so the balances are real rather than a blanket 'cash'. Matched by suffix
 * because source_type stores a namespaced class name. Re-runnable: it only ever
 * overwrites a derived label.
 */

UPDATE `cash_transactions` ct
  JOIN `fee_payments` fp ON fp.id = ct.source_id
   SET ct.channel = CASE
     WHEN fp.method IN ('bank','card','cheque') THEN 'bank'
     WHEN fp.method IN ('bkash','nagad','rocket') THEN 'mobile'
     ELSE 'cash' END
 WHERE ct.source_type LIKE '%FeePayment';

UPDATE `cash_transactions` ct
  JOIN `expenses` e ON e.id = ct.source_id
   SET ct.channel = CASE
     WHEN e.payment_method IN ('bank','card','cheque') THEN 'bank'
     WHEN e.payment_method IN ('bkash','nagad','rocket') THEN 'mobile'
     ELSE 'cash' END
 WHERE ct.source_type LIKE '%Expense';

UPDATE `cash_transactions` ct
  JOIN `salary_payments` sp ON sp.id = ct.source_id
   SET ct.channel = CASE
     WHEN sp.payment_method IN ('bank','card','cheque') THEN 'bank'
     WHEN sp.payment_method IN ('bkash','nagad','rocket') THEN 'mobile'
     ELSE 'cash' END
 WHERE ct.source_type LIKE '%SalaryPayment';

UPDATE `fee_payments` SET `verification_status` = 'verified'
 WHERE `method` <> 'cash' AND `verification_status` = 'not_required';

/*
 * Confirm. These are the shapes the dashboard and cashbook query. The aliases
 * avoid reserved words, and there are no line comments anywhere above: paste the
 * whole body at once and it runs to the end.
 */

SELECT channel, COUNT(*) AS entry_count, COALESCE(SUM(amount), 0) AS balance FROM `cash_transactions` GROUP BY channel;

SELECT method, COUNT(*) AS payments FROM `fee_payments` GROUP BY method;

SELECT COUNT(*) AS bank_accounts FROM `bank_accounts`;

SELECT verification_status, COUNT(*) AS payments FROM `fee_payments` GROUP BY verification_status;

/*
 * OPTIONAL, DO NOT PASTE WITH THE BLOCK ABOVE.
 *
 * MariaDB has no "ADD CONSTRAINT IF NOT EXISTS", so these will fail with #1826 or
 * #1215 if the keys already exist, and phpMyAdmin would stop the batch at that
 * point. The application works without them; they only add database-level
 * integrity. Run them one at a time, only if you want the constraints, and treat
 * a duplicate-constraint error as "already present".
 *
 * ALTER TABLE `cash_transactions` ADD CONSTRAINT `cash_transactions_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL;
 * ALTER TABLE `fee_payments` ADD CONSTRAINT `fee_payments_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
 * ALTER TABLE `fee_payments` ADD CONSTRAINT `fee_payments_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL;
 * ALTER TABLE `expenses` ADD CONSTRAINT `expenses_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL;
 * ALTER TABLE `fee_invoice_discounts` ADD CONSTRAINT `fee_invoice_discounts_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
 * ALTER TABLE `fee_invoice_discounts` ADD CONSTRAINT `fee_invoice_discounts_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
 */
