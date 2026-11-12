<?php

use App\Models\Expense;
use App\Models\FeePayment;
use App\Models\SalaryPayment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bank_accounts')) {
            Schema::create('bank_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained()->restrictOnDelete();
                $table->string('name', 80);
                $table->string('bank_name', 120);
                $table->string('account_no', 40);
                $table->string('branch', 120)->nullable();
                $table->decimal('opening_balance', 14, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['school_id', 'account_no'], 'bank_accounts_school_account_unique');
            });
        }

        $this->addColumn('cash_transactions', 'channel', fn (Blueprint $table) => $table
            ->string('channel', 20)->default('cash')->after('type'));

        $this->addReference('cash_transactions', 'bank_account_id', 'bank_accounts');

        $this->addIndex('cash_transactions', 'cash_transactions_channel_date_index', ['school_id', 'channel', 'transaction_date']);

        if (! Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('school_id')->constrained()->restrictOnDelete();
                $table->string('voucher_no', 30);
                $table->string('type', 20);
                $table->date('voucher_date');
                $table->decimal('amount', 14, 2);
                $table->string('channel', 20)->default('cash');
                $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
                $table->string('category', 80)->nullable();
                $table->string('payee', 120)->nullable();
                $table->string('description', 255);
                $table->string('reference_no', 100)->nullable();
                $table->string('status', 20)->default('posted');
                $table->foreignId('cash_transaction_id')->nullable()->constrained('cash_transactions')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancel_reason')->nullable();
                $table->timestamps();

                $table->unique(['school_id', 'voucher_no'], 'vouchers_school_no_unique');
                $table->index(['school_id', 'voucher_date', 'type'], 'vouchers_date_type_index');
            });
        }

        $this->addColumn('fee_payments', 'verification_status', fn (Blueprint $table) => $table
            ->string('verification_status', 20)->default('not_required')->after('status'));

        $this->addReference('fee_payments', 'verified_by', 'users');

        $this->addColumn('fee_payments', 'verified_at', fn (Blueprint $table) => $table
            ->timestamp('verified_at')->nullable()->after('verified_by'));

        $this->addColumn('fee_payments', 'rejection_reason', fn (Blueprint $table) => $table
            ->text('rejection_reason')->nullable()->after('verified_at'));

        $this->addReference('fee_payments', 'bank_account_id', 'bank_accounts', 'rejection_reason');

        $this->addIndex('fee_payments', 'fee_payments_verification_index', ['school_id', 'verification_status']);

        $this->addReference('expenses', 'bank_account_id', 'bank_accounts', 'payment_method');

        $this->addColumn('expenses', 'rejection_reason', fn (Blueprint $table) => $table
            ->text('rejection_reason')->nullable()->after('cancel_reason'));

        $this->addColumn('fee_invoice_discounts', 'status', fn (Blueprint $table) => $table
            ->string('status', 20)->default('applied')->after('amount'));

        $this->addReference('fee_invoice_discounts', 'requested_by', 'users');

        $this->addReference('fee_invoice_discounts', 'approved_by', 'users');

        $this->addColumn('fee_invoice_discounts', 'approved_at', fn (Blueprint $table) => $table
            ->timestamp('approved_at')->nullable()->after('approved_by'));

        $this->addColumn('fee_invoice_discounts', 'rejection_reason', fn (Blueprint $table) => $table
            ->text('rejection_reason')->nullable()->after('approved_at'));

        $this->addIndex('fee_invoice_discounts', 'fee_invoice_discounts_status_index', ['school_id', 'status']);

        $this->backfillChannels();

        // Offline evidence that already arrived through a non-cash method is
        // treated as verified; only new bank/mobile lines wait for a reviewer.
        DB::table('fee_payments')
            ->where('method', '!=', 'cash')
            ->where('verification_status', 'not_required')
            ->update(['verification_status' => 'verified']);
    }

    public function down(): void
    {
        Schema::table('fee_invoice_discounts', function (Blueprint $table) {
            $table->dropIndex('fee_invoice_discounts_status_index');
            $table->dropConstrainedForeignId('requested_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['status', 'approved_at', 'rejection_reason']);
        });

        Schema::table('fee_payments', function (Blueprint $table) {
            $table->dropIndex('fee_payments_verification_index');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn(['verification_status', 'verified_at', 'rejection_reason']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn('rejection_reason');
        });

        Schema::dropIfExists('vouchers');

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropIndex('cash_transactions_channel_date_index');
            $table->dropConstrainedForeignId('bank_account_id');
            $table->dropColumn('channel');
        });

        Schema::dropIfExists('bank_accounts');
    }

    /**
     * A stopped import can leave any of these steps half-applied, and the
     * schema differs between MySQL and MariaDB, so each step is checked in PHP
     * rather than assumed. Nothing here removes a column or a row.
     */
    private function addColumn(string $table, string $column, Closure $definition): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($definition): void {
            $definition($blueprint);
        });
    }

    private function addReference(string $table, string $column, string $references, ?string $after = null): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $after): void {
            $definition = $blueprint->foreignId($column)->nullable();

            if ($after !== null) {
                $definition->after($after);
            }

            $definition->constrained($references)->nullOnDelete();
        });
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

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->index($columns, $name);
        });
    }

    private function backfillChannels(): void
    {
        $sources = [
            FeePayment::class => ['fee_payments', 'method'],
            Expense::class => ['expenses', 'payment_method'],
            SalaryPayment::class => ['salary_payments', 'payment_method'],
        ];

        foreach ($sources as $morph => [$table, $column]) {
            DB::table($table)
                ->select('id', $column)
                ->orderBy('id')
                ->chunk(500, function ($rows) use ($morph, $column): void {
                    foreach ($rows->groupBy($column) as $method => $group) {
                        DB::table('cash_transactions')
                            ->where('source_type', $morph)
                            ->whereIn('source_id', $group->pluck('id'))
                            ->update(['channel' => $this->channelForMethod((string) $method)]);
                    }
                });
        }
    }

    private function channelForMethod(string $method): string
    {
        return match ($method) {
            'bank', 'card', 'cheque' => 'bank',
            'bkash', 'nagad', 'rocket' => 'mobile',
            default => 'cash',
        };
    }
};
