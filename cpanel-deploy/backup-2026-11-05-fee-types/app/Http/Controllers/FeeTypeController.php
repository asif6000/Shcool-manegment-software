<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\SaveFeeTypeRequest;
use App\Models\FeeType;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The fee catalogue. Changing a type here deliberately does not touch any
 * invoice: issued amounts were snapshotted when the invoice was generated.
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
                ->when($search !== '', fn (Builder $query): Builder => $query->where(fn (Builder $inner): Builder => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'frequencies' => FeeType::FREQUENCIES,
            'categories' => FeeType::CATEGORIES,
        ]);
    }

    public function store(SaveFeeTypeRequest $request): RedirectResponse
    {
        $this->requirePermission('fees.manage');

        $type = FeeType::query()->create($request->validated());

        $this->audit->log(
            event: 'fee_type.created',
            auditable: $type,
            description: __('Fee type :name was created', ['name' => $type->name]),
            newValues: $request->validated(),
            actor: $request->user(),
        );

        return back()->with('success', __('The fee type was created.'));
    }

    public function update(SaveFeeTypeRequest $request, FeeType $feeType): RedirectResponse
    {
        $this->requirePermission('fees.manage');

        $before = $feeType->only(array_keys($request->validated()));

        $feeType->update($request->validated());

        $this->audit->log(
            event: 'fee_type.updated',
            auditable: $feeType,
            description: __('Fee type :name was changed', ['name' => $feeType->name]),
            oldValues: $before,
            newValues: $request->validated(),
            actor: $request->user(),
        );

        return back()->with('success', __('The fee type was updated. Invoices already issued keep the amounts they were billed with.'));
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
}
