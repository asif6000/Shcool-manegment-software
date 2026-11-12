<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addColumn('fee_discounts', 'description', fn (Blueprint $table) => $table
            ->text('description')->nullable()->after('code'));
        $this->addColumn('fee_discounts', 'frequency', fn (Blueprint $table) => $table
            ->string('frequency', 20)->default('one_time')->after('type'));
        $this->addColumn('fee_discounts', 'scope', fn (Blueprint $table) => $table
            ->string('scope', 20)->default('all')->after('frequency'));
        $this->addReference('fee_discounts', 'class_id', 'classes', 'scope');
        $this->addReference('fee_discounts', 'student_id', 'students', 'class_id');
        $this->addColumn('fee_discounts', 'starts_on', fn (Blueprint $table) => $table
            ->date('starts_on')->nullable()->after('student_id'));
        $this->addColumn('fee_discounts', 'ends_on', fn (Blueprint $table) => $table
            ->date('ends_on')->nullable()->after('starts_on'));
        $this->addColumn('fee_discounts', 'max_amount', fn (Blueprint $table) => $table
            ->decimal('max_amount', 14, 2)->nullable()->after('value'));
        $this->addColumn('fee_discounts', 'auto_apply', fn (Blueprint $table) => $table
            ->boolean('auto_apply')->default(false)->after('is_active'));
        $this->addReference('fee_discounts', 'created_by', 'users', 'auto_apply');
        $this->addReference('fee_discounts', 'updated_by', 'users', 'created_by');
        $this->addIndex('fee_discounts', 'fee_discounts_active_index', ['school_id', 'is_active', 'starts_on', 'ends_on']);

        $this->addReference('fee_invoice_discounts', 'fee_type_id', 'fee_types', 'fee_discount_id');
        $this->addColumn('fee_invoice_discounts', 'code', fn (Blueprint $table) => $table
            ->string('code', 30)->nullable()->after('fee_type_id'));
        $this->addColumn('fee_invoice_discounts', 'frequency', fn (Blueprint $table) => $table
            ->string('frequency', 20)->nullable()->after('type'));
        $this->addColumn('fee_invoice_discounts', 'scope', fn (Blueprint $table) => $table
            ->string('scope', 20)->nullable()->after('frequency'));
        $this->addColumn('fee_invoice_discounts', 'reason', fn (Blueprint $table) => $table
            ->text('reason')->nullable()->after('value'));
        $this->addColumn('fee_invoice_discounts', 'base_amount', fn (Blueprint $table) => $table
            ->decimal('base_amount', 14, 2)->default(0)->after('amount'));
        $this->addColumn('fee_invoice_discounts', 'max_amount', fn (Blueprint $table) => $table
            ->decimal('max_amount', 14, 2)->nullable()->after('base_amount'));
        $this->addColumn('fee_invoice_discounts', 'starts_on', fn (Blueprint $table) => $table
            ->date('starts_on')->nullable()->after('max_amount'));
        $this->addColumn('fee_invoice_discounts', 'ends_on', fn (Blueprint $table) => $table
            ->date('ends_on')->nullable()->after('starts_on'));
        $this->addColumn('fee_invoice_discounts', 'requested_at', fn (Blueprint $table) => $table
            ->timestamp('requested_at')->nullable()->after('requested_by'));
        $this->addReference('fee_invoice_discounts', 'rejected_by', 'users', 'approved_at');
        $this->addColumn('fee_invoice_discounts', 'rejected_at', fn (Blueprint $table) => $table
            ->timestamp('rejected_at')->nullable()->after('rejected_by'));
        $this->addReference('fee_invoice_discounts', 'cancelled_by', 'users', 'rejection_reason');
        $this->addColumn('fee_invoice_discounts', 'cancelled_at', fn (Blueprint $table) => $table
            ->timestamp('cancelled_at')->nullable()->after('cancelled_by'));
        $this->addColumn('fee_invoice_discounts', 'cancellation_reason', fn (Blueprint $table) => $table
            ->text('cancellation_reason')->nullable()->after('cancelled_at'));
        $this->addIndex('fee_invoice_discounts', 'fee_invoice_discounts_created_index', ['school_id', 'status', 'created_at']);

        $this->addColumn('fee_invoices', 'configured_fee_amount', fn (Blueprint $table) => $table
            ->decimal('configured_fee_amount', 14, 2)->default(0)->after('discount_total'));
        $this->addColumn('fee_invoices', 'approved_discount_amount', fn (Blueprint $table) => $table
            ->decimal('approved_discount_amount', 14, 2)->default(0)->after('configured_fee_amount'));
        $this->addColumn('fee_invoices', 'final_payable_amount', fn (Blueprint $table) => $table
            ->decimal('final_payable_amount', 14, 2)->default(0)->after('approved_discount_amount'));
        $this->addColumn('fee_invoices', 'actual_collected_amount', fn (Blueprint $table) => $table
            ->decimal('actual_collected_amount', 14, 2)->default(0)->after('paid_total'));

        DB::table('fee_invoice_discounts')
            ->where('status', 'applied')
            ->update(['status' => 'approved']);

        DB::table('fee_invoices')->update([
            'configured_fee_amount' => DB::raw('subtotal'),
            'approved_discount_amount' => DB::raw('discount_total'),
            'final_payable_amount' => DB::raw('total'),
            'actual_collected_amount' => DB::raw('paid_total'),
        ]);
    }

    public function down(): void
    {
        Schema::table('fee_invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'configured_fee_amount',
                'approved_discount_amount',
                'final_payable_amount',
                'actual_collected_amount',
            ]);
        });

        Schema::table('fee_invoice_discounts', function (Blueprint $table): void {
            $table->dropIndex('fee_invoice_discounts_created_index');
            $table->dropConstrainedForeignId('fee_type_id');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'code',
                'frequency',
                'scope',
                'reason',
                'base_amount',
                'max_amount',
                'starts_on',
                'ends_on',
                'requested_at',
                'rejected_at',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });

        Schema::table('fee_discounts', function (Blueprint $table): void {
            $table->dropIndex('fee_discounts_active_index');
            $table->dropConstrainedForeignId('class_id');
            $table->dropConstrainedForeignId('student_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'description',
                'frequency',
                'scope',
                'starts_on',
                'ends_on',
                'max_amount',
                'auto_apply',
            ]);
        });

        DB::table('fee_invoice_discounts')
            ->where('status', 'approved')
            ->update(['status' => 'applied']);
    }

    private function addColumn(string $table, string $column, callable $definition): void
    {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($definition): void {
            $definition($blueprint);
        });
    }

    private function addReference(
        string $table,
        string $column,
        string $foreignTable,
        ?string $after = null,
    ): void {
        if (Schema::hasColumn($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $foreignTable, $after): void {
            $definition = $blueprint->foreignId($column)->nullable()->constrained($foreignTable)->nullOnDelete();

            if ($after !== null) {
                $definition->after($after);
            }
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

        Schema::table($table, function (Blueprint $blueprint) use ($name, $columns): void {
            $blueprint->index($columns, $name);
        });
    }
};
