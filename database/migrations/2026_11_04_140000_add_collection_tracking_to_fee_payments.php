<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The accountant counter records one class, one fee type and one billing month
 * per payment. The invoice and receipt already carry most of that story, but
 * storing the three keys on the payment itself lets the admin filters and the
 * duplicate guard answer without walking every allocation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('fee_payments', 'class_id')) {
            Schema::table('fee_payments', function (Blueprint $table): void {
                $table->foreignId('class_id')->nullable()->after('student_id')->constrained('classes')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('fee_payments', 'fee_type_id')) {
            Schema::table('fee_payments', function (Blueprint $table): void {
                $table->foreignId('fee_type_id')->nullable()->after('class_id')->constrained('fee_types')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('fee_payments', 'billing_month')) {
            Schema::table('fee_payments', function (Blueprint $table): void {
                $table->string('billing_month', 7)->nullable()->after('fee_type_id');
            });
        }

        if (! Schema::hasColumn('fee_payments', 'invoice_no')) {
            Schema::table('fee_payments', function (Blueprint $table): void {
                $table->string('invoice_no', 30)->nullable()->after('billing_month');
            });
        }

        $this->addIndex('fee_payments', 'fee_payments_tracking_index', [
            'school_id',
            'billing_month',
            'class_id',
            'fee_type_id',
        ]);

        // Existing payments predate the three keys. Backfill them from the
        // invoice they were allocated to so old collections keep showing in
        // the class, month and fee-type reports.
        DB::statement(<<<'SQL'
UPDATE `fee_payments` fp
JOIN (
    SELECT
        fpa.`fee_payment_id`,
        MIN(fi.`invoice_no`) AS `invoice_no`,
        MIN(fi.`billing_period`) AS `billing_month`,
        MIN(fi.`enrollment_id`) AS `enrollment_id`,
        MIN(fii.`fee_type_id`) AS `fee_type_id`
    FROM `fee_payment_allocations` fpa
    JOIN `fee_invoices` fi ON fi.`id` = fpa.`fee_invoice_id`
    LEFT JOIN `fee_invoice_items` fii ON fii.`fee_invoice_id` = fi.`id`
    GROUP BY fpa.`fee_payment_id`
) src ON src.`fee_payment_id` = fp.`id`
LEFT JOIN `enrollments` e ON e.`id` = src.`enrollment_id`
SET
    fp.`invoice_no` = COALESCE(fp.`invoice_no`, src.`invoice_no`),
    fp.`billing_month` = COALESCE(fp.`billing_month`, src.`billing_month`),
    fp.`fee_type_id` = COALESCE(fp.`fee_type_id`, src.`fee_type_id`),
    fp.`class_id` = COALESCE(fp.`class_id`, e.`class_id`)
WHERE fp.`billing_month` IS NULL
   OR fp.`fee_type_id` IS NULL
   OR fp.`class_id` IS NULL
   OR fp.`invoice_no` IS NULL
SQL);
    }

    public function down(): void
    {
        // Tracking keys are additive; leaving them behind is safer than
        // deleting data a report may already have used.
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndex(string $table, string $name, array $columns): void
    {
        $exists = DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $name],
        ) !== null;

        if ($exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name, $columns): void {
            $blueprint->index($columns, $name);
        });
    }
};
