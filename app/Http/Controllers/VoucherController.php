<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoucherController extends Controller
{
    public function __construct(private readonly VoucherService $vouchers) {}

    public function index(Request $request): View
    {
        $this->requirePermission('vouchers.view');

        $filters = [
            'type' => in_array($request->query('type'), Voucher::TYPES, true) ? (string) $request->query('type') : '',
            'channel' => in_array($request->query('channel'), Voucher::CHANNELS, true) ? (string) $request->query('channel') : '',
            'status' => in_array($request->query('status'), ['posted', 'cancelled'], true) ? (string) $request->query('status') : '',
            'search' => trim((string) $request->query('search')),
            'from' => $request->filled('from') ? $this->dateOrToday($request->query('from'))->toDateString() : '',
            'to' => $request->filled('to') ? $this->dateOrToday($request->query('to'))->toDateString() : '',
        ];

        $query = $this->filteredQuery($filters);

        return view('vouchers.index', [
            'filters' => $filters,
            'vouchers' => $query
                ->with(['creator', 'bankAccount'])
                ->orderByDesc('voucher_date')
                ->orderByDesc('id')
                ->paginate((int) config('school.pagination.per_page', 15))
                ->withQueryString(),
            'summary' => [
                'income' => (float) (clone $query)->where('type', 'income')->where('status', 'posted')->sum('amount'),
                'expense' => (float) (clone $query)->where('type', 'expense')->where('status', 'posted')->sum('amount'),
                'posted' => (clone $query)->where('status', 'posted')->count(),
                'cancelled' => (clone $query)->where('status', 'cancelled')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->requirePermission('vouchers.manage');

        return view('vouchers.create', [
            'bankAccounts' => BankAccount::query()->active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requirePermission('vouchers.manage');

        $data = $request->validate([
            'type' => ['required', 'in:income,expense'],
            'voucher_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'channel' => ['required', 'in:cash,bank,mobile'],
            'bank_account_id' => ['nullable', 'required_if:channel,bank', 'integer'],
            'category' => ['nullable', 'string', 'max:80'],
            'payee' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:100'],
        ]);

        $voucher = $this->vouchers->post($request->user(), $data);

        return redirect()
            ->route('vouchers.show', $voucher)
            ->with('success', __('Voucher :no was posted to the ledger.', ['no' => $voucher->voucher_no]));
    }

    public function show(Voucher $voucher): View
    {
        $this->requirePermission('vouchers.view');

        $voucher->load(['creator', 'canceller', 'bankAccount', 'transaction']);

        return view('vouchers.show', [
            'voucher' => $voucher,
        ]);
    }

    public function cancel(Request $request, Voucher $voucher): RedirectResponse
    {
        $this->requirePermission('vouchers.manage');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $this->vouchers->cancel($request->user(), $voucher, $data['reason']);

        return back()->with('success', __('Voucher :no was cancelled and the ledger was reversed.', ['no' => $voucher->voucher_no]));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->requirePermission('vouchers.view');

        $filters = [
            'type' => in_array($request->query('type'), Voucher::TYPES, true) ? (string) $request->query('type') : '',
            'channel' => in_array($request->query('channel'), Voucher::CHANNELS, true) ? (string) $request->query('channel') : '',
            'status' => in_array($request->query('status'), ['posted', 'cancelled'], true) ? (string) $request->query('status') : '',
            'search' => trim((string) $request->query('search')),
            'from' => $request->filled('from') ? $this->dateOrToday($request->query('from'))->toDateString() : '',
            'to' => $request->filled('to') ? $this->dateOrToday($request->query('to'))->toDateString() : '',
        ];

        $rows = $this->filteredQuery($filters)->with(['bankAccount', 'creator'])->orderBy('voucher_date')->orderBy('id')->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                __('Voucher no'), __('Date'), __('Type'), __('Channel'), __('Bank account'), __('Category'),
                __('Payee'), __('Description'), __('Reference'), __('Amount'), __('Status'), __('Created by'),
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->voucher_no,
                    $row->voucher_date?->toDateString(),
                    $row->type,
                    $row->channel,
                    $row->bankAccount?->name,
                    $row->category,
                    $row->payee,
                    $row->description,
                    $row->reference_no,
                    number_format((float) $row->amount, 2, '.', ''),
                    $row->status,
                    $row->creator?->name,
                ]);
            }

            fclose($out);
        }, 'vouchers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return Voucher::query()
            ->when($filters['type'] !== '', fn (Builder $query) => $query->where('type', $filters['type']))
            ->when($filters['channel'] !== '', fn (Builder $query) => $query->where('channel', $filters['channel']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->whereDate('voucher_date', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->whereDate('voucher_date', '<=', $filters['to']))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('voucher_no', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('payee', 'like', $like)
                        ->orWhere('reference_no', 'like', $like);
                });
            });
    }
}
