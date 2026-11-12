<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Production reported fee_invoices without configured_fee_amount even though
 * the fee-discount migration was recorded as run — the same schema drift that
 * hit study_materials. Add each column the fee screens read, so an environment
 * that missed part of the 2026-10-30/31 release still issues invoices.
 */
return new class extends Migration
{
    public function up(): void
    {
        $amountColumnsAdded = false;

        if ($this->addColumn('fee_invoices', 'configured_fee_amount', fn (Blueprint $table) => $table
            ->decimal('configured_fee_amount', 14, 2)->default(0), 'discount_total')) {
            $amountColumnsAdded = true;
        }

        if ($this->addColumn('fee_invoices', 'approved_discount_amount', fn (Blueprint $table) => $table
            ->decimal('approved_discount_amount', 14, 2)->default(0), 'configured_fee_amount')) {
            $amountColumnsAdded = true;
        }

        if ($this->addColumn('fee_invoices', 'final_payable_amount', fn (Blueprint $table) => $table
            ->decimal('final_payable_amount', 14, 2)->default(0), 'approved_discount_amount')) {
            $amountColumnsAdded = true;
        }

        if ($this->addColumn('fee_invoices', 'actual_collected_amount', fn (Blueprint $table) => $table
            ->decimal('actual_collected_amount', 14, 2)->default(0), 'paid_total')) {
            $amountColumnsAdded = true;
        }

        $this->addColumn('fee_invoices', 'waived_total', fn (Blueprint $table) => $table
            ->decimal('waived_total', 14, 2)->default(0), 'due_total');

        $this->addColumn('fee_payment_allocations', 'waived_amount', fn (Blueprint $table) => $table
            ->decimal('waived_amount', 14, 2)->default(0), 'amount');

        $this->addColumn('fee_discounts', 'description', fn (Blueprint $table) => $table
            ->text('description')->nullable(), 'code');
        $this->addColumn('fee_discounts', 'frequency', fn (Blueprint $table) => $table
            ->string('frequency', 20)->default('one_time'), 'type');
        $this->addColumn('fee_discounts', 'scope', fn (Blueprint $table) => $table
            ->string('scope', 20)->default('all'), 'frequency');
        $this->addReference('fee_discounts', 'class_id', 'classes', 'scope');
        $this->addReference('fee_discounts', 'student_id', 'students', 'class_id');
        $this->addColumn('fee_discounts', 'starts_on', fn (Blueprint $table) => $table
            ->date('starts_on')->nullable(), 'student_id');
        $this->addColumn('fee_discounts', 'ends_on', fn (Blueprint $table) => $table
            ->date('ends_on')->nullable(), 'starts_on');
        $this->addColumn('fee_discounts', 'max_amount', fn (Blueprint $table) => $table
            ->decimal('max_amount', 14, 2)->nullable(), 'value');
        $this->addColumn('fee_discounts', 'auto_apply', fn (Blueprint $table) => $table
            ->boolean('auto_apply')->default(false), 'is_active');
        $this->addReference('fee_discounts', 'created_by', 'users', 'auto_apply');
        $this->addReference('fee_discounts', 'updated_by', 'users', 'created_by');
        $this->addIndex('fee_discounts', 'fee_discounts_active_index', ['school_id', 'is_active', 'starts_on', 'ends_on']);

        $this->addReference('fee_invoice_discounts', 'fee_type_id', 'fee_types', 'fee_discount_id');
        $this->addColumn('fee_invoice_discounts', 'code', fn (Blueprint $table) => $table
            ->string('code', 30)->nullable(), 'fee_type_id');
        $this->addColumn('fee_invoice_discounts', 'frequency', fn (Blueprint $table) => $table
            ->string('frequency', 20)->nullable(), 'type');
        $this->addColumn('fee_invoice_discounts', 'scope', fn (Blueprint $table) => $table
            ->string('scope', 20)->nullable(), 'frequency');
        $this->addColumn('fee_invoice_discounts', 'reason', fn (Blueprint $table) => $table
            ->text('reason')->nullable(), 'value');
        $this->addColumn('fee_invoice_discounts', 'base_amount', fn (Blueprint $table) => $table
            ->decimal('base_amount', 14, 2)->default(0), 'amount');
        $this->addColumn('fee_invoice_discounts', 'max_amount', fn (Blueprint $table) => $table
            ->decimal('max_amount', 14, 2)->nullable(), 'base_amount');
        $this->addColumn('fee_invoice_discounts', 'starts_on', fn (Blueprint $table) => $table
            ->date('starts_on')->nullable(), 'max_amount');
        $this->addColumn('fee_invoice_discounts', 'ends_on', fn (Blueprint $table) => $table
            ->date('ends_on')->nullable(), 'starts_on');
        $this->addColumn('fee_invoice_discounts', 'requested_at', fn (Blueprint $table) => $table
            ->timestamp('requested_at')->nullable(), 'requested_by');
        $this->addReference('fee_invoice_discounts', 'rejected_by', 'users', 'approved_at');
        $this->addColumn('fee_invoice_discounts', 'rejected_at', fn (Blueprint $table) => $table
            ->timestamp('rejected_at')->nullable(), 'rejected_by');
        $this->addReference('fee_invoice_discounts', 'cancelled_by', 'users', 'rejection_reason');
        $this->addColumn('fee_invoice_discounts', 'cancelled_at', fn (Blueprint $table) => $table
            ->timestamp('cancelled_at')->nullable(), 'cancelled_by');
        $this->addColumn('fee_invoice_discounts', 'cancellation_reason', fn (Blueprint $table) => $table
            ->text('cancellation_reason')->nullable(), 'cancelled_at');
        $this->addIndex('fee_invoice_discounts', 'fee_invoice_discounts_created_index', ['school_id', 'status', 'created_at']);

        if ($amountColumnsAdded) {
            DB::table('fee_invoices')->update([
                'configured_fee_amount' => DB::raw('subtotal'),
                'approved_discount_amount' => DB::raw('discount_total'),
                'final_payable_amount' => DB::raw('total'),
                'actual_collected_amount' => DB::raw('paid_total'),
            ]);
        }

        if (Schema::hasColumn('fee_invoice_discounts', 'status')) {
            DB::table('fee_invoice_discounts')
                ->where('status', 'applied')
                ->update(['status' => 'approved']);
        }
    }

    public function down(): void
    {
        // The columns are owned by the original migrations; this repair only
        // fills gaps and must not remove data when it is rolled back.
    }

    private function addColumn(string $table, string $column, callable $definition, ?string $after = null): bool
    {
        if (Schema::hasColumn($table, $column)) {
            return false;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($definition, $after, $table): void {
            $columnDefinition = $definition($blueprint);

            if ($after !== null && Schema::hasColumn($table, $after)) {
                $columnDefinition->after($after);
            }
        });

        return true;
    }

    private function addReference(string $table, string $column, string $foreignTable, ?string $after = null): bool
    {
        if (Schema::hasColumn($table, $column)) {
            return false;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $foreignTable, $after, $table): void {
            $definition = $blueprint->foreignId($column)->nullable()->constrained($foreignTable)->nullOnDelete();

            if ($after !== null && Schema::hasColumn($table, $after)) {
                $definition->after($after);
            }
        });

        return true;
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
