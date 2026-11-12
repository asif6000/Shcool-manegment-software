<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The table shipped unused, so it has no columns for the two things a
        // gateway flow actually needs: our own request identity (created before
        // the provider returns an id) and the student the money belongs to.
        Schema::table('payment_gateway_transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('payment_gateway_transactions', 'payment_key')) {
                $table->string('payment_key', 64)->charset('ascii')->nullable()->after('school_id');
            }

            if (! Schema::hasColumn('payment_gateway_transactions', 'student_id')) {
                $table->unsignedBigInteger('student_id')->nullable()->after('payment_key');
            }

            if (! Schema::hasColumn('payment_gateway_transactions', 'currency')) {
                $table->char('currency', 3)->charset('ascii')->default('BDT')->after('amount');
            }

            if (! Schema::hasColumn('payment_gateway_transactions', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('payment_gateway_transactions', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('verified_at');
            }
        });

        Schema::table('payment_gateway_transactions', function (Blueprint $table): void {
            $table->string('gateway_transaction_id', 120)->charset('ascii')->nullable()->change();
        });

        $this->studentForeignKey();

        $this->uniqueKey(
            'payment_gateway_transactions',
            'gateway_transactions_payment_key_unique',
            ['school_id', 'payment_key'],
        );
    }

    public function down(): void
    {
        Schema::table('payment_gateway_transactions', function (Blueprint $table): void {
            $table->dropIndex('gateway_transactions_payment_key_unique');

            if (Schema::hasColumn('payment_gateway_transactions', 'student_id')) {
                try {
                    $table->dropConstrainedForeignId('student_id');
                } catch (Throwable) {
                    $table->dropColumn('student_id');
                }
            }

            foreach (['payment_key', 'currency', 'verified_at', 'failure_reason'] as $column) {
                if (Schema::hasColumn('payment_gateway_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * MySQL has no ADD CONSTRAINT IF NOT EXISTS, and MariaDB does not accept the
     * IF NOT EXISTS column syntax at all, so every change is guarded in PHP.
     */
    private function studentForeignKey(): void
    {
        $exists = DB::selectOne(
            'SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['payment_gateway_transactions', 'gateway_transactions_student_fk'],
        ) !== null;

        if (! $exists) {
            Schema::table('payment_gateway_transactions', function (Blueprint $table): void {
                $table->foreign('student_id', 'gateway_transactions_student_fk')
                    ->references('id')->on('students')->restrictOnDelete();
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function uniqueKey(string $table, string $name, array $columns): void
    {
        $exists = DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $name],
        ) !== null;

        if (! $exists) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                $blueprint->unique($columns, $name);
            });
        }
    }
};
