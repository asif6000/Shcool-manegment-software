/*
 * DRMS production — optional: put the two purge-damaged invoices into the
 * "settled / written off" state instead of "cancelled".
 *
 * Background: the 2026-11-06 13:54 purge of accountant "emon" (01810902817) ran
 * the old build. It DELETED the two 500.00 receipts and reopened both invoices
 * (paid 0, due 500, issued), so the two students showed ৳1,000 on Outstanding
 * that they had already paid at the counter. The backup proves the state before
 * that purge (storage/app/private/backups/accountant-purge/accountant-5-20261106-135448.json):
 *
 *     INV-2026-0001  total 500.00  paid 500.00  waived 0.00  due 0.00  paid
 *     INV-2026-0002  total 500.00  paid 500.00  waived 0.00  due 0.00  paid
 *
 * On 2026-11-06 both invoices were cancelled through the admin invoice screen
 * (POST /admin/fees/{id}/cancel, audited as fee_invoice.cancelled). Outstanding
 * Fees is ৳0, no student owes anything, and no money or receipt was invented.
 *
 * Cancelling leaves the invoices out of the "billed" statistics. If you would
 * rather keep them visible as billed-and-settled — what the new purge code
 * produces, and what the pre-purge records actually were — run Step 2 below.
 * It changes nothing financial: total 500.00, paid 0.00, waived 500.00,
 * due 0.00, status settled. Outstanding stays ৳0 either way.
 *
 * Re-runnable: a second run matches 0 rows because the status is no longer
 * 'cancelled'. Nothing else is touched — no student, enrollment, payment,
 * receipt, cashbook row or fee structure.
 *
 * Run it in phpMyAdmin -> SQL tab. Every name is schema-qualified so the node
 * clicked in the left panel cannot send the rows into another database.
 *
 * If your host rejects the irvrzogs_drms2026. prefixes, select that database
 * first and remove them — nothing else changes.
 */

/* -------------------------------------------------------------------------- */
/* Step 1 — read only. The two invoices as they stand right now.               */
/*                                                                             */
/* Expect 2 rows: total 500.00, paid 0.00, waived 0.00, due 500.00, cancelled.*/
/* After Step 2 the same read shows waived 500.00, due 0.00, settled.          */
/* -------------------------------------------------------------------------- */

SELECT i.id, i.invoice_no, i.student_id, i.total, i.paid_total, i.waived_total,
       i.due_total, i.status, i.updated_at
FROM irvrzogs_drms2026.fee_invoices AS i
WHERE i.invoice_no IN ('INV-2026-0001', 'INV-2026-0002')
ORDER BY i.invoice_no;

/* -------------------------------------------------------------------------- */
/* Step 2 — OPTIONAL. Turn "cancelled" into "settled with a ৳500 write-off".   */
/*                                                                             */
/* Expected result: 2 rows affected. The WHERE clause only matches these two   */
/* invoices in exactly the state the cancel left them, so a genuine payment    */
/* recorded later can never be overwritten.                                    */
/* -------------------------------------------------------------------------- */

UPDATE irvrzogs_drms2026.fee_invoices AS i
SET i.paid_total = 0.00,
    i.actual_collected_amount = 0.00,
    i.waived_total = 500.00,
    i.due_total = 0.00,
    i.status = 'settled',
    i.updated_at = NOW()
WHERE i.invoice_no IN ('INV-2026-0001', 'INV-2026-0002')
  AND i.total = 500.00
  AND i.paid_total = 0.00
  AND i.waived_total = 0.00
  AND i.due_total = 500.00
  AND i.status = 'cancelled';

/* -------------------------------------------------------------------------- */
/* Step 3 — read only. Prove no balance is left anywhere.                      */
/*                                                                             */
/* Expect 0 rows. Any row returned still carries a balance.                    */
/* -------------------------------------------------------------------------- */

SELECT i.invoice_no, i.total, i.paid_total, i.waived_total, i.due_total, i.status
FROM irvrzogs_drms2026.fee_invoices AS i
WHERE i.invoice_no IN ('INV-2026-0001', 'INV-2026-0002')
  AND i.due_total > 0;

/* -------------------------------------------------------------------------- */
/* Step 4 — read only. The school-wide outstanding total.                      */
/*                                                                             */
/* Outstanding dues only counts open invoices with a balance, so these two     */
/* rows stay out of it whether they are cancelled or settled.                  */
/* -------------------------------------------------------------------------- */

SELECT COUNT(*) AS open_invoices, COALESCE(SUM(i.due_total), 0.00) AS outstanding_total
FROM irvrzogs_drms2026.fee_invoices AS i
WHERE i.status IN ('issued', 'partial')
  AND i.due_total > 0;
