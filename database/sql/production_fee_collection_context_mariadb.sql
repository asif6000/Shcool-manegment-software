/*
 * Production schema check, 2026-11-06: the cPanel account has irvrzogs_drms and
 * irvrzogs_drms2026, and the live app connects to irvrzogs_drms2026 (proved by the
 * storage/logs entry written while the teacher portal was being used). There is no
 * irvrzogs_dreamroots database, which is why an earlier copy of this file did nothing.
 *
 * DRMS production — collection context on every fee payment (MariaDB / cPanel safe).
 *
 * The accountant's "Collect Student Fee" desk now records, on the payment row
 * itself, the academic session, the invoice the money was taken against and the
 * amount that fee was scheduled for. Those three columns are what the printed
 * collection register and the fee-type-wise summary read, so a payment without
 * them shows up as an empty cell.
 *
 * This file is exactly what these two migrations would have done, and artisan
 * cannot run on this host:
 *   2026_11_04_150000  stop fee_payments.paid_at moving on its own
 *   2026_11_05_100000  academic_session_id, fee_invoice_id, scheduled_amount
 *
 * It also takes fees.reverse away from the accountant role, which is what the
 * updated role matrix in database/seeders/RolePermissionSeeder.php says. The
 * application refuses an accountant reversal by role even without this, so
 * step 5 is tidy-up, not the safety measure itself.
 *
 * Nothing here drops a table, deletes a payment, or changes an amount. Every
 * column is nullable and every backfill keeps the value already stored.
 *
 * Re-runnable: MariaDB accepts IF NOT EXISTS on columns and the backfill only
 * fills blanks. The two FOREIGN KEY lines are the only statements that cannot
 * be guarded, so they sit after the columns and before nothing else matters -
 * if phpMyAdmin reports they already exist, everything the app needs is present.
 *
 * Run it in phpMyAdmin -> Import, or paste the blocks in the SQL tab with the
 * irvrzogs_drms2026 database selected. Every name is schema-qualified, so the
 * left-hand node you clicked cannot send a change into another database.
 *
 * If your host rejects the irvrzogs_drms2026. prefixes, select that database
 * first and remove them - nothing else changes.
 */

/* -------------------------------------------------------------------------- */
/* Step 0 — read only. Run these first and note what comes back.               */
/*                                                                             */
/* 0a returns 1 row (paid_at)         -> the three columns are missing; this     */
/*                                       file is needed.                         */
/*    returns 4 rows                  -> already applied; pasting again is safe. */
/* 0b paid_at EXTRA = 'on update CURRENT_TIMESTAMP' -> step 2 is needed; empty   */
/*    means a previous paste already fixed it.                                   */
/* 0c returns 6 rows                  -> every table this file touches exists.   */
/* 0d returns 1+ rows for accountant  -> step 5 has work to do; 0 rows means the  */
/*    cash desk can already no longer reverse.                                   */
/* -------------------------------------------------------------------------- */

/* 0a — the four columns the new desk writes. */
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, EXTRA
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'irvrzogs_drms2026'
  AND TABLE_NAME = 'fee_payments'
  AND COLUMN_NAME IN ('academic_session_id', 'fee_invoice_id', 'scheduled_amount', 'paid_at')
ORDER BY ORDINAL_POSITION;

/* 0b — does the collection date still jump forward when a row is edited? */
SELECT COLUMN_NAME, COLUMN_DEFAULT, EXTRA
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'irvrzogs_drms2026'
  AND TABLE_NAME = 'fee_payments'
  AND COLUMN_NAME = 'paid_at';

/* 0c — the tables referenced by the new keys and the backfill. */
SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = 'irvrzogs_drms2026'
  AND TABLE_NAME IN ('fee_payments', 'fee_payment_allocations', 'fee_invoices',
                     'academic_sessions', 'fee_types', 'role_has_permissions')
ORDER BY TABLE_NAME;

/* 0d — who can undo a receipted payment today. */
SELECT r.name AS role_name, p.name AS permission_name
FROM irvrzogs_drms2026.role_has_permissions rp
JOIN irvrzogs_drms2026.permissions p ON p.id = rp.permission_id
JOIN irvrzogs_drms2026.roles r       ON r.id = rp.role_id
WHERE p.name = 'fees.reverse'
ORDER BY r.name;

/* 0e — how much money is already collected, so step 4 can be compared after. */
SELECT COUNT(*)                        AS payments_total,
       COALESCE(SUM(fp.`amount`), 0)   AS payments_sum,
       COALESCE(SUM(fp.`amount`), 0)   AS expected_after_backfill
FROM irvrzogs_drms2026.fee_payments fp
WHERE fp.`status` = 'posted';

/* -------------------------------------------------------------------------- */
/* Step 1 — the three context columns.                                         */
/* Nullable, so every existing payment row keeps its current data untouched.    */
/* -------------------------------------------------------------------------- */

ALTER TABLE irvrzogs_drms2026.`fee_payments`
  ADD COLUMN IF NOT EXISTS `academic_session_id` bigint(20) unsigned DEFAULT NULL AFTER `student_id`,
  ADD COLUMN IF NOT EXISTS `fee_invoice_id` bigint(20) unsigned DEFAULT NULL AFTER `invoice_no`,
  ADD COLUMN IF NOT EXISTS `scheduled_amount` decimal(14,2) DEFAULT NULL AFTER `fee_invoice_id`;

/* -------------------------------------------------------------------------- */
/* Step 2 — the collection date must stay the date the money was taken.         */
/* MariaDB gave fee_payments.paid_at an implicit ON UPDATE CURRENT_TIMESTAMP     */
/* because it is the first TIMESTAMP column on the table. Verifying, rejecting   */
/* or reversing a payment then rewrote its date to today, which corrupts the     */
/* daily and monthly registers. This only changes the rule; stored dates are     */
/* not touched.                                                                  */
/* -------------------------------------------------------------------------- */

ALTER TABLE irvrzogs_drms2026.`fee_payments`
  MODIFY `paid_at` timestamp NOT NULL DEFAULT current_timestamp();

/* -------------------------------------------------------------------------- */
/* Step 3 — the two keys Laravel's schema builder would have created.          */
/* Run these last. If either reports "Duplicate foreign key constraint name" or  */
/* "already exists", stop: the columns are in place and that is what the app      */
/* needs. They can only fail here, never damage the rows above.                  */
/* -------------------------------------------------------------------------- */

ALTER TABLE irvrzogs_drms2026.`fee_payments`
  ADD CONSTRAINT `fee_payments_academic_session_id_foreign`
    FOREIGN KEY (`academic_session_id`) REFERENCES irvrzogs_drms2026.`academic_sessions` (`id`) ON DELETE SET NULL;

ALTER TABLE irvrzogs_drms2026.`fee_payments`
  ADD CONSTRAINT `fee_payments_fee_invoice_id_foreign`
    FOREIGN KEY (`fee_invoice_id`) REFERENCES irvrzogs_drms2026.`fee_invoices` (`id`) ON DELETE SET NULL;

/* -------------------------------------------------------------------------- */
/* Step 4 — give the payments already in the register their context.            */
/* Each row is filled from the invoice its allocation points at, which is the     */
/* same source the register uses today. COALESCE means a value that is already    */
/* stored is never overwritten, and the WHERE clause leaves complete rows alone,   */
/* so pasting this twice changes nothing the second time.                         */
/* -------------------------------------------------------------------------- */

UPDATE irvrzogs_drms2026.`fee_payments` fp
JOIN (
    SELECT fpa.`fee_payment_id`, MIN(fpa.`fee_invoice_id`) AS `fee_invoice_id`
    FROM irvrzogs_drms2026.`fee_payment_allocations` fpa
    GROUP BY fpa.`fee_payment_id`
) src ON src.`fee_payment_id` = fp.`id`
JOIN irvrzogs_drms2026.`fee_invoices` fi ON fi.`id` = src.`fee_invoice_id`
LEFT JOIN irvrzogs_drms2026.`enrollments` e ON e.`id` = fi.`enrollment_id`
SET
    fp.`fee_invoice_id`      = COALESCE(fp.`fee_invoice_id`, src.`fee_invoice_id`),
    fp.`invoice_no`          = COALESCE(fp.`invoice_no`, fi.`invoice_no`),
    fp.`academic_session_id` = COALESCE(fp.`academic_session_id`, fi.`academic_session_id`),
    fp.`billing_month`       = COALESCE(fp.`billing_month`, fi.`billing_period`),
    fp.`scheduled_amount`    = COALESCE(fp.`scheduled_amount`, fi.`total`),
    fp.`fee_type_id`         = COALESCE(fp.`fee_type_id`,
        (SELECT MIN(fii.`fee_type_id`)
         FROM irvrzogs_drms2026.`fee_invoice_items` fii
         WHERE fii.`fee_invoice_id` = fi.`id`)),
    fp.`class_id`            = COALESCE(fp.`class_id`, e.`class_id`)
WHERE fp.`fee_invoice_id` IS NULL
   OR fp.`academic_session_id` IS NULL
   OR fp.`scheduled_amount` IS NULL
   OR fp.`fee_type_id` IS NULL;

/* -------------------------------------------------------------------------- */
/* Step 5 — the accountant keeps the cash desk, loses the undo button.          */
/* Removes the fees.reverse grant from the accountant role only. Reversals then    */
/* belong to the office, so a mistake is corrected by somebody other than the      */
/* person who made it and the audit trail keeps both names.                        */
/* -------------------------------------------------------------------------- */

DELETE FROM irvrzogs_drms2026.role_has_permissions
WHERE role_id IN (
    SELECT id FROM irvrzogs_drms2026.roles WHERE name = 'accountant'
)
  AND permission_id IN (
    SELECT id FROM irvrzogs_drms2026.permissions WHERE name = 'fees.reverse'
);

/* -------------------------------------------------------------------------- */
/* Step 6 — read only. Verify, then sign in and try the desk.                   */
/*                                                                             */
/* 6a must return 4 rows, three of them nullable.                               */
/* 6b paid_at EXTRA must be empty.                                              */
/* 6c payments_sum must equal the number noted at 0e — not a peso changed.       */
/* 6d a payment with no invoice behind it is only possible for an old row that    */
/*    had no allocation record; it must never happen for a new collection.        */
/* 6e fees.reverse must no longer list the accountant.                           */
/* -------------------------------------------------------------------------- */

SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'irvrzogs_drms2026'
  AND TABLE_NAME = 'fee_payments'
  AND COLUMN_NAME IN ('academic_session_id', 'fee_invoice_id', 'scheduled_amount', 'paid_at')
ORDER BY ORDINAL_POSITION;

SELECT COLUMN_NAME, EXTRA
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'irvrzogs_drms2026'
  AND TABLE_NAME = 'fee_payments'
  AND COLUMN_NAME = 'paid_at';

SELECT COUNT(*) AS posted_payments, COALESCE(SUM(`amount`), 0) AS payments_sum
FROM irvrzogs_drms2026.fee_payments
WHERE `status` = 'posted';

SELECT COUNT(*) AS payments_without_invoice
FROM irvrzogs_drms2026.fee_payments
WHERE `fee_invoice_id` IS NULL;

SELECT r.name AS role_name, p.name AS permission_name
FROM irvrzogs_drms2026.role_has_permissions rp
JOIN irvrzogs_drms2026.permissions p ON p.id = rp.permission_id
JOIN irvrzogs_drms2026.roles r       ON r.id = rp.role_id
WHERE p.name IN ('fees.reverse', 'fees.collect', 'fees.manage')
ORDER BY p.name, r.name;

/*
 * ---------------------------------------------------------------------------
 * Rollback — the columns are additive and a report may already read them, so
 * there is nothing to undo safely except step 5. Re-granting fees.reverse to
 * the accountant is deliberately not provided here: the application refuses a
 * cash-desk reversal by role either way, so an old permission row would only
 * make the screen look inconsistent.
 * ---------------------------------------------------------------------------
 */
