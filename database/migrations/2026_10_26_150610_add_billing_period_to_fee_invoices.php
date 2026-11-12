<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->string('billing_period', 20)->nullable()->after('invoice_no');
            $table->index(
                ['school_id', 'student_id', 'billing_period'],
                'fee_invoices_student_period_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('fee_invoices', function (Blueprint $table) {
            $table->dropIndex('fee_invoices_student_period_index');
            $table->dropColumn('billing_period');
        });
    }
};
