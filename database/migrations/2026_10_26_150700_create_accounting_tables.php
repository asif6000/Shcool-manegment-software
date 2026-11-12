<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->string('code', 30);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code'], 'expense_categories_school_code_unique');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            $table->string('expense_no', 30);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->date('expense_date');
            $table->string('payment_method', 20)->default('cash');
            $table->string('reference_no', 100)->nullable();
            $table->string('paid_to', 120)->nullable();
            $table->string('status', 20)->default('paid');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'expense_no'], 'expenses_school_no_unique');
            $table->index(['school_id', 'expense_date', 'status'], 'expenses_date_status_index');
            $table->index(['school_id', 'expense_category_id'], 'expenses_category_index');
        });

        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->decimal('amount', 14, 2);
            $table->date('transaction_date');
            $table->string('description', 255);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference_no', 100)->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('cash_transactions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'transaction_date', 'type'], 'cash_transactions_date_type_index');
            $table->index(['source_type', 'source_id'], 'cash_transactions_source_index');
            $table->index(['school_id', 'reconciled_at'], 'cash_transactions_reconciled_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
