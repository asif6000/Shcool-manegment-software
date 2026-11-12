<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 30);
            $table->text('message');
            $table->string('provider', 40)->nullable();
            $table->string('status', 20)->default('queued');
            $table->json('response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status', 'created_at'], 'sms_logs_status_index');
        });

        Schema::create('payment_gateway_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('fee_invoice_id')->nullable()->constrained('fee_invoices')->nullOnDelete();
            $table->foreignId('fee_payment_id')->nullable()->constrained('fee_payments')->nullOnDelete();
            $table->string('gateway', 40);
            $table->string('gateway_transaction_id', 120);
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('pending');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'gateway_transaction_id'], 'gateway_transactions_unique');
            $table->index(['school_id', 'status'], 'gateway_transactions_status_index');
        });

        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('filename');
            $table->string('disk', 40)->default('local');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('status', 20)->default('completed');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['school_id', 'created_at'], 'backup_logs_school_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('payment_gateway_transactions');
        Schema::dropIfExists('sms_logs');
    }
};
