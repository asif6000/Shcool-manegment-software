<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_invoices', function (Blueprint $table): void {
            $table->decimal('waived_total', 14, 2)->default(0)->after('due_total');
        });

        Schema::table('fee_payment_allocations', function (Blueprint $table): void {
            $table->decimal('waived_amount', 14, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('fee_payment_allocations', function (Blueprint $table): void {
            $table->dropColumn('waived_amount');
        });

        Schema::table('fee_invoices', function (Blueprint $table): void {
            $table->dropColumn('waived_total');
        });
    }
};
