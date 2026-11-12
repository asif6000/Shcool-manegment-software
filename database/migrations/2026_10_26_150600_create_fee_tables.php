<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->string('frequency', 20)->default('monthly');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'code'], 'fee_types_school_code_unique');
            $table->unique(['school_id', 'name'], 'fee_types_school_name_unique');
        });

        Schema::create('fee_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->string('code', 30);
            $table->string('type', 20)->default('fixed');
            $table->decimal('value', 12, 2);
            $table->foreignId('applies_to_fee_type_id')->nullable()->constrained('fee_types')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code'], 'fee_discounts_school_code_unique');
        });

        Schema::create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('fee_type_id')->constrained('fee_types')->restrictOnDelete();
            $table->string('name', 100)->nullable();
            $table->decimal('amount', 12, 2);
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['school_id', 'academic_session_id', 'class_id', 'fee_type_id'],
                'fee_structures_unique'
            );
            $table->index(['school_id', 'class_id', 'is_active'], 'fee_structures_class_index');
        });

        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $table->string('invoice_no', 30);
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_total', 14, 2)->default(0);
            $table->decimal('due_total', 14, 2)->default(0);
            $table->string('status', 20)->default('issued');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'invoice_no'], 'fee_invoices_school_no_unique');
            $table->index(['school_id', 'status', 'due_date'], 'fee_invoices_status_due_index');
            $table->index(['school_id', 'student_id', 'status'], 'fee_invoices_student_status_index');
        });

        Schema::create('fee_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_invoice_id')->constrained('fee_invoices')->cascadeOnDelete();
            $table->foreignId('fee_type_id')->nullable()->constrained('fee_types')->nullOnDelete();
            $table->string('description', 160);
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->index(['school_id', 'fee_invoice_id'], 'fee_invoice_items_invoice_index');
        });

        Schema::create('fee_invoice_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_invoice_id')->constrained('fee_invoices')->cascadeOnDelete();
            $table->foreignId('fee_discount_id')->nullable()->constrained('fee_discounts')->nullOnDelete();
            $table->string('name', 80);
            $table->string('type', 20);
            $table->decimal('value', 12, 2);
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->index(['school_id', 'fee_invoice_id'], 'fee_invoice_discounts_invoice_index');
        });

        Schema::create('fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method', 20)->default('cash');
            $table->string('transaction_ref', 100)->nullable();
            $table->timestamp('paid_at');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('posted');
            $table->string('idempotency_key', 64);
            $table->text('notes')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'idempotency_key'], 'fee_payments_idempotency_unique');
            $table->index(['school_id', 'student_id', 'paid_at'], 'fee_payments_student_date_index');
            $table->index(['school_id', 'status', 'paid_at'], 'fee_payments_status_date_index');
        });

        Schema::create('fee_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_payment_id')->unique()->constrained('fee_payments')->restrictOnDelete();
            $table->string('receipt_no', 30);
            $table->json('snapshot');
            $table->char('snapshot_hash', 64);
            $table->timestamp('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'receipt_no'], 'fee_receipts_school_no_unique');
        });

        Schema::create('fee_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_payment_id')->constrained('fee_payments')->restrictOnDelete();
            $table->foreignId('fee_invoice_id')->constrained('fee_invoices')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique(['fee_payment_id', 'fee_invoice_id'], 'fee_payment_allocations_unique');
            $table->index(['school_id', 'fee_invoice_id'], 'fee_allocations_invoice_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_payment_allocations');
        Schema::dropIfExists('fee_receipts');
        Schema::dropIfExists('fee_payments');
        Schema::dropIfExists('fee_invoice_discounts');
        Schema::dropIfExists('fee_invoice_items');
        Schema::dropIfExists('fee_invoices');
        Schema::dropIfExists('fee_structures');
        Schema::dropIfExists('fee_discounts');
        Schema::dropIfExists('fee_types');
    }
};
