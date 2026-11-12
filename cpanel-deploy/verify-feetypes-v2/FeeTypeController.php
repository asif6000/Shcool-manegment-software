<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\SaveFeeTypeRequest;
use App\Models\FeeDiscount;
use App\Models\FeeInvoiceDiscount;
use App\Models\FeeInvoiceItem;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeStudentAssignment;
use App\Models\FeeType;
use App\Support\AuditLogger;
use App\Support\SchoolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The fee catalogue. A fee type is nothing but a name: the money lives on the
 * class fee, and every figure already billed keeps the snapshot it was issued
 * with, so renaming here never rewrites history.
 */
class FeeTypeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->requirePermission('fees.view');

        $search = trim((string) $request->query('search'));

        return view('fees.types', [
            'search' => $search,
            'feeTypes' => FeeType::query()
                ->withCount(['structures', 'assignments' => fn (Builder $query): Builder => $query->where('is_active', true)])
                ->withCount([
                    'invoiceItems',
                    'payments',
                    'discountRules',
                    'invoiceDiscounts',
                ])
                ->when($search !== '', fn (Builder $query): Builder => $query->where(fn (Builder $inner): Builder => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(SaveFeeTypeRequest $request): RedirectResponse
    {
        $this->requirePermission('fees.manage');

        $type = FeeType::query()->create([
            'name' => $request->validated('name'),
            'code' => $this->nextCode((string) $request->validated('name')),
        ]);

        $this->audit->log(
            event: 'fee_type.created',
            auditable: $type,
            description: __('Fee type :name was created', ['name' => $type->name]),
            newValues: ['name' => $type->name, 'code' => $type->code],
            actor: $request->user(),
        );

        return back()->with('success', __('The fee type :name was created. Set its amount per class on the Class fees page.', ['name' => $type->name]));
    }

    public function update(SaveFeeTypeRequest $request, FeeType $feeType): RedirectResponse
    {
        $this->requirePermission('fees.manage');

        $before = $feeType->name;
        $name = (string) $request->validated('name');

        $feeType->update(['name' => $name]);

        $this->audit->log(
            event: 'fee_type.updated',
            auditable: $feeType,
            description: __('Fee type :name was renamed from :before', ['name' => $name, 'before' => $before]),
            oldValues: ['name' => $before],
            newValues: ['name' => $name],
            actor: $request->user(),
        );

        return back()->with('success', __('The fee type was renamed. Invoices already issued keep the amounts they were billed with.'));
    }

    public function toggle(Request $request, FeeType $feeType, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('fees.manage');

        $feeType->update(['is_active' => ! $feeType->is_active]);

        $audit->log(
            event: 'fee_type.toggled',
            auditable: $feeType,
            description: __('Fee type :name was :state', [
                'name' => $feeType->name,
                'state' => $feeType->is_active ? __('activated') : __('deactivated'),
            ]),
            oldValues: ['is_active' => ! $feeType->is_active],
            newValues: ['is_active' => $feeType->is_active],
            actor: $request->user(),
        );

        return back()->with('success', __('The fee type was updated.'));
    }

    /**
     * A fee type that has never been billed can be removed outright. Anything
     * with a class fee, a student setup, an invoice line, a payment or a
     * discount behind it stays: deleting it would erase financial history.
     */
    public function destroy(Request $request, FeeType $feeType): RedirectResponse
    {
        $this->requirePermission('fees.manage');

        $usage = $this->usage($feeType);

        if ($usage->isNotEmpty()) {
            return back()->with('error', __(':name is still used by :usage, so it cannot be deleted. Deactivate it instead — the fee setups, invoices and payments stay exactly as they are.', [
                'name' => $feeType->name,
                'usage' => $usage->implode(', '),
            ]));
        }

        $name = $feeType->name;

        DB::transaction(function () use ($request, $feeType, $name): void {
            $feeType->forceDelete();

            // The instance keeps its attributes after the row goes, so the
            // audit entry still points at the name and code that were removed.
            $this->audit->log(
                event: 'fee_type.deleted',
                auditable: $feeType,
                description: __('Fee type :name was deleted', ['name' => $name]),
                oldValues: ['name' => $name, 'code' => $feeType->code],
                actor: $request->user(),
            );
        });

        return back()->with('success', __('The fee type :name was deleted. No fee setup, invoice or payment was touched.', ['name' => $name]));
    }

    /**
     * Every place a fee type is referenced, counted for the delete guard.
     *
     * @return \Illuminate\Support\Collection<string, string>
     */
    private function usage(FeeType $feeType): \Illuminate\Support\Collection
    {
        return collect([
            __('class fee setups') => FeeStructure::query()->where('fee_type_id', $feeType->id)->count(),
            __('student fee setups') => FeeStudentAssignment::query()->where('fee_type_id', $feeType->id)->count(),
            __('invoice lines') => FeeInvoiceItem::query()->where('fee_type_id', $feeType->id)->count(),
            __('payments') => FeePayment::query()->where('fee_type_id', $feeType->id)->count(),
            __('discount rules') => FeeDiscount::query()->where('applies_to_fee_type_id', $feeType->id)->count(),
            __('invoice discounts') => FeeInvoiceDiscount::query()->where('fee_type_id', $feeType->id)->count(),
        ])->filter(fn (int $count): bool => $count > 0)
            ->map(fn (int $count, string $label): string => $count.' '.$label)
            ->values();
    }

    /**
     * The code is written once into every invoice line, so it is generated
     * from the name instead of being typed by hand.
     */
    private function nextCode(string $name): string
    {
        $base = Str::upper(Str::slug(Str::limit($name, 22, ''), '_')) ?: 'FEE';
        $schoolId = app(SchoolContext::class)->id();
        $code = $base;
        $suffix = 2;

        while (FeeType::query()->withTrashed()->where('school_id', $schoolId)->where('code', $code)->exists()) {
            $code = $base.'_'.$suffix++;
        }

        return $code;
    }
}
