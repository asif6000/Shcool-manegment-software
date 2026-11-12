-- ============================================================================
-- Fee invoices — one active invoice per student / session / billing period
-- School MS / Dream Roots Millenium School
--
-- Why:
--   `FeeInvoiceService::generate()` already refuses to bill the same period
--   twice, but that check runs before the insert, so two concurrent requests
--   (or a manual insert through phpMyAdmin) can still create duplicate
--   invoices for the same student and month. This upgrade makes the rule a
--   database constraint.
--
-- What this does (additive only):
--   1. Adds the generated column `fee_invoices.active_billing_key`
--      = "school:student:session:period" while the invoice is active,
--      NULL when the invoice is cancelled or has no billing period.
--   2. Adds the unique key `fee_invoices_active_billing_unique` on that column.
--   3. Records the migration in Laravel's `migrations` table.
--
--   Cancelled invoices and period-less (one-off) invoices get NULL and are
--   NOT constrained, because MySQL/MariaDB never compare NULLs. Cancelling and
--   re-billing the same month therefore keeps working.
--
-- cPanel notes:
--   * No `information_schema`, no PREPARE/EXECUTE: both ALTERs use
--     `IF NOT EXISTS`, so the whole file is safe to import twice.
--   * phpMyAdmin stops at the first SQL error. The only error this file can
--     raise is the "#1062 Duplicate entry" from step 3, and it means step 1
--     found real duplicate invoices — cancel them in the fee screen and
--     re-import. Nothing is half-applied in that case: the column from step 2
--     is harmless and step 2 is repeatable.
--
-- Safety:
--   * Take a full database backup BEFORE importing (phpMyAdmin -> Export).
--   * No DROP, DELETE, TRUNCATE or data rewrite anywhere in the runnable part.
--   * Do NOT run `migrate:fresh`, `db:seed` or any reset command in production.
--   * Use either this file OR `php artisan migrate --force`; running both is
--     harmless because steps 2-4 are idempotent.
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 0. Pre-flight (read-only). Note what is already there.
--    Empty result for `active_billing_key` = first run.
-- ---------------------------------------------------------------------------
SHOW COLUMNS FROM `fee_invoices`
    WHERE `Field` IN ('academic_session_id', 'billing_period', 'status',
                      'active_billing_key');

SHOW INDEX FROM `fee_invoices` WHERE `Key_name` = 'fee_invoices_active_billing_unique';

-- ---------------------------------------------------------------------------
-- 1. Duplicate check (read-only). EXPECTED RESULT: empty.
--    If rows come back, do NOT continue: cancel those invoices first
--    (Fees -> the invoice -> Cancel), then import this file again.
-- ---------------------------------------------------------------------------
SELECT CONCAT(`school_id`, ':', `student_id`, ':', `academic_session_id`, ':', `billing_period`) AS active_key,
       COUNT(*) AS invoices
FROM `fee_invoices`
WHERE `status` <> 'cancelled'
  AND `billing_period` IS NOT NULL
GROUP BY active_key
HAVING COUNT(*) > 1
ORDER BY invoices DESC;

-- Detail rows for the groups above (read-only), so the exact invoices can be cancelled.
SELECT i.`id`, i.`invoice_no`, i.`school_id`, i.`student_id`,
       i.`academic_session_id`, i.`billing_period`, i.`status`,
       i.`total`, i.`paid_total`, i.`created_at`
FROM `fee_invoices` i
WHERE i.`status` <> 'cancelled'
  AND i.`billing_period` IS NOT NULL
  AND EXISTS (
      SELECT 1 FROM `fee_invoices` d
      WHERE d.`status` <> 'cancelled'
        AND d.`billing_period` = i.`billing_period`
        AND d.`school_id` = i.`school_id`
        AND d.`student_id` = i.`student_id`
        AND d.`academic_session_id` = i.`academic_session_id`
        AND d.`id` <> i.`id`
  )
ORDER BY i.`student_id`, i.`billing_period`, i.`id`;

-- ---------------------------------------------------------------------------
-- 2. Add the generated key column. Repeatable.
-- ---------------------------------------------------------------------------
ALTER TABLE `fee_invoices`
    ADD COLUMN IF NOT EXISTS `active_billing_key` VARCHAR(96) CHARACTER SET ascii
        GENERATED ALWAYS AS (
            CASE
                WHEN `status` <> 'cancelled' AND `billing_period` IS NOT NULL
                THEN CONCAT(`school_id`, ':', `student_id`, ':', `academic_session_id`, ':', `billing_period`)
            END
        ) STORED;

-- ---------------------------------------------------------------------------
-- 3. Enforce uniqueness. Repeatable.
--    "#1062 Duplicate entry" here = step 1 had rows; cancel them and re-run.
-- ---------------------------------------------------------------------------
ALTER TABLE `fee_invoices`
    ADD UNIQUE KEY IF NOT EXISTS `fee_invoices_active_billing_unique` (`active_billing_key`);

-- ---------------------------------------------------------------------------
-- 4. Record the migration (guarded, safe on a repeat import).
-- ---------------------------------------------------------------------------
SET @batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_29_130000_add_fee_invoice_active_billing_unique_index', @batch
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_10_29_130000_add_fee_invoice_active_billing_unique_index');

-- ---------------------------------------------------------------------------
-- 5. Verification (read-only).
--    Expected: `active_billing_key` present (generated, nullable),
--              one row for `fee_invoices_active_billing_unique` with Non_unique = 0,
--              and the migration row listed.
-- ---------------------------------------------------------------------------
SHOW FULL COLUMNS FROM `fee_invoices` LIKE 'active_billing_key';
SHOW INDEX FROM `fee_invoices` WHERE `Key_name` = 'fee_invoices_active_billing_unique';

SELECT `migration`, `batch`
FROM `migrations`
WHERE `migration` = '2026_10_29_130000_add_fee_invoice_active_billing_unique_index';

-- ============================================================================
-- ROLLBACK (only if you must undo). Select this block and run it manually;
-- it does not run as part of a normal import.
-- Dropping the index never touches invoice rows.
--
-- ALTER TABLE `fee_invoices` DROP INDEX IF EXISTS `fee_invoices_active_billing_unique`;
-- ALTER TABLE `fee_invoices` DROP COLUMN IF EXISTS `active_billing_key`;
-- DELETE FROM `migrations`
-- WHERE `migration` = '2026_10_29_130000_add_fee_invoice_active_billing_unique_index';
-- ============================================================================
