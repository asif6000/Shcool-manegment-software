<?php

namespace App\Http\Controllers;

use App\Models\FeeInvoiceDiscount;
use App\Services\FeeInvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeeDiscountController extends Controller
{
    public function __construct(private readonly FeeInvoiceService $invoices) {}

    public function index(Request $request): View
    {
        $this->requirePermission('fees.view');

        $filters = [
            'search' => trim((string) $request->query('search')),
            'status' => in_array($request->query('status'), FeeInvoiceDiscount::STATUSES, true)
                ? (string) $request->query('status')
                : '',
        ];

        $query = FeeInvoiceDiscount::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('name', 'like', $like)
                        ->orWhereHas('invoice', fn (Builder $query) => $query->where('invoice_no', 'like', $like))
                        ->orWhereHas('invoice.student', function (Builder $query) use ($like): void {
                            $query->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('student_id', 'like', $like);
                        });
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']));

        $pending = FeeInvoiceDiscount::query()->where('status', 'pending');

        return view('fees.discounts', [
            'filters' => $filters,
            'discounts' => $query
                ->with(['invoice.student', 'feeDiscount', 'requester', 'approver'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate((int) config('school.pagination.per_page', 15))
                ->withQueryString(),
            'summary' => [
                'pending_count' => (clone $pending)->count(),
                'pending_value' => (float) (clone $pending)->sum('amount'),
                'applied_value' => (float) FeeInvoiceDiscount::query()
                    ->whereIn('status', FeeInvoiceDiscount::ACTIVE_STATUSES)
                    ->sum('amount'),
                'applied_count' => FeeInvoiceDiscount::query()
                    ->whereIn('status', FeeInvoiceDiscount::ACTIVE_STATUSES)
                    ->count(),
            ],
            'threshold' => (float) config('finance.discount_approval_threshold', 0),
        ]);
    }

    public function approve(Request $request, FeeInvoiceDiscount $discount): RedirectResponse
    {
        $this->requirePermission('fees.discounts.approve');

        $this->invoices->approveDiscount($request->user(), $discount->invoice, $discount);

        return back()->with('success', __('The discount request was approved and the invoice was recalculated.'));
    }

    public function reject(Request $request, FeeInvoiceDiscount $discount): RedirectResponse
    {
        $this->requirePermission('fees.discounts.approve');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->invoices->rejectDiscount($request->user(), $discount->invoice, $discount, $data['reason']);

        return back()->with('success', __('The discount request was rejected.'));
    }
}
