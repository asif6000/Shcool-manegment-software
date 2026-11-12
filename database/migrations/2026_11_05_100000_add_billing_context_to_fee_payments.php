<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A payment must be readable on its own. The counter already records the class,
 * fee type and billing month; the session, the invoice it was taken against and
 * the fee that was scheduled for it were only reachable by walking the
 * allocation rows, which made the collection register and the fee-type summary
 * rebuild the same join on every page.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fee_payments', 'academic_session_id')) {
            Schema::table('fee_payments', function (Blueprint $table): void {
                $table->foreignId('academic_session_id')->nullable()
                    ->after('student_id')->constrained('academic_sessions')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('fee_payments', 'fee_invoice_id')) {
            Schema::table('fee_payments', function (Blueprint $table): void {
                $table->foreignId('fee_invoice_id')->nullable()
                    ->after('invoice_no')->constrained('fee_invoices')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('fee_payments', 'scheduled_amount')) {
            Schema::table('fee_payments', function (Blueprint $table): void {
                $table->decimal('scheduled_amount', 14, 2)->nullable()->after('fee_invoice_id');
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        // Context keys are additive; a report may already read them.
    }

    private function backfill(): void
    {
        if (! Schema::hasColumn('fee_payments', 'fee_invoice_id')) {
            return;
        }

        DB::statement(<<<'SQL'
UPDATE `fee_payments` fp
JOIN (
    SELECT fpa.`fee_payment_id`, MIN(fpa.`fee_invoice_id`) AS `fee_invoice_id`
    FROM `fee_payment_allocations` fpa
    GROUP BY fpa.`fee_payment_id`
) src ON src.`fee_payment_id` = fp.`id`
JOIN `fee_invoices` fi ON fi.`id` = src.`fee_invoice_id`
LEFT JOIN `enrollments` e ON e.`id` = fi.`enrollment_id`
SET
    fp.`fee_invoice_id` = COALESCE(fp.`fee_invoice_id`, src.`fee_invoice_id`),
    fp.`invoice_no` = COALESCE(fp.`invoice_no`, fi.`invoice_no`),
    fp.`academic_session_id` = COALESCE(fp.`academic_session_id`, fi.`academic_session_id`),
    fp.`billing_month` = COALESCE(fp.`billing_month`, fi.`billing_period`),
    fp.`scheduled_amount` = COALESCE(fp.`scheduled_amount`, fi.`total`),
    fp.`fee_type_id` = COALESCE(
        fp.`fee_type_id`,
        (SELECT MIN(fii.`fee_type_id`) FROM `fee_invoice_items` fii WHERE fii.`fee_invoice_id` = fi.`id`)
    ),
    fp.`class_id` = COALESCE(fp.`class_id`, e.`class_id`)
WHERE fp.`fee_invoice_id` IS NULL
   OR fp.`academic_session_id` IS NULL
   OR fp.`scheduled_amount` IS NULL
   OR fp.`fee_type_id` IS NULL
SQL);
    }
};
