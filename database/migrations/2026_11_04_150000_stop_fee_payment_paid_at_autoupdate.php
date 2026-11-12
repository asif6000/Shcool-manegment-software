<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MariaDB gives the first TIMESTAMP column an implicit ON UPDATE
 * CURRENT_TIMESTAMP. Every later update of a payment row - verifying,
 * rejecting, reversing - then silently moves its collection date to now,
 * which corrupts the daily and monthly collection reports. The column must
 * keep the date the money was actually received.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE `fee_payments` MODIFY `paid_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(): void
    {
        // The implicit ON UPDATE was never intended; there is nothing to restore.
    }
};
