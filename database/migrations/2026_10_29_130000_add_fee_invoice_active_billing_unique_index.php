<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A duplicate that slips in before the index exists would make the
        // ALTER fail on production data, so surface the exact invoices first.
        $duplicates = DB::table('fee_invoices')
            ->selectRaw("CONCAT(school_id, ':', student_id, ':', academic_session_id, ':', billing_period) AS active_key, COUNT(*) AS invoices")
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('billing_period')
            ->groupBy('active_key')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('invoices')
            ->limit(10)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cancel the duplicate active invoices before running this migration: '
                .$duplicates->map(fn (object $row): string => $row->active_key.' ('.$row->invoices.' invoices)')->implode(', ')
            );
        }

        // Guards in PHP, not IF NOT EXISTS in DDL: MySQL and MariaDB accept
        // different forms, and a half-applied schema (a stopped phpMyAdmin
        // import) must be finishable without re-running statements that landed.
        if (! Schema::hasColumn('fee_invoices', 'active_billing_key')) {
            DB::statement(<<<'SQL'
ALTER TABLE `fee_invoices`
    ADD COLUMN `active_billing_key` VARCHAR(96) CHARACTER SET ascii
        GENERATED ALWAYS AS (
            CASE
                WHEN `status` <> 'cancelled' AND `billing_period` IS NOT NULL
                THEN CONCAT(`school_id`, ':', `student_id`, ':', `academic_session_id`, ':', `billing_period`)
            END
        ) STORED
SQL);
        }

        $hasUniqueKey = DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['fee_invoices', 'fee_invoices_active_billing_unique'],
        ) !== null;

        if (! $hasUniqueKey) {
            DB::statement('ALTER TABLE `fee_invoices` ADD UNIQUE KEY `fee_invoices_active_billing_unique` (`active_billing_key`)');
        }
    }

    public function down(): void
    {
        Schema::table('fee_invoices', function (Blueprint $table): void {
            $table->dropIndex('fee_invoices_active_billing_unique');
            $table->dropColumn('active_billing_key');
        });
    }
};
