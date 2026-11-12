<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\ApplyFeeDiscountRequest;
use App\Http\Requests\Finance\GenerateFeeInvoicesRequest;
use App\Models\AcademicSession;
use App\Models\BankAccount;
use App\Models\FeeDiscount;
use App\Models\FeeInvoice;
use App\Models\FeeInvoiceDiscount;
use App\Models\FeePayment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\FeeInvoiceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeInvoiceController extends Controller
{
    public function __construct(private readonly FeeInvoiceService $invoices) {}

    public function index(Request $request): View
    {
        $this->requirePermission('fees.view');

        $filters = $this->filters($request);
        $query = $this->filteredQuery($filters);

        $summary = (clone $query)->selectRaw(
            'COALESCE(SUM(CASE WHEN status <> ? THEN total ELSE 0 END), 0) AS total,
             COALESCE(SUM(CASE WHEN status <> ? THEN paid_total ELSE 0 END), 0) AS paid,
             COALESCE(SUM(CASE WHEN status <> ? THEN due_total ELSE 0 END), 0) AS due,
             COALESCE(SUM(CASE WHEN status <> ? THEN 1 ELSE 0 END), 0) AS invoice_count',
            ['cancelled', 'cancelled', 'cancelled', 'cancelled'],
        )->first();

        $overdue = (clone $query)
            ->whereIn('status', FeeInvoice::OPEN_STATUSES)
            ->where('due_total', '>', 0)
            ->where('due_date', '<', now()->toDateString())
            ->count();

        return view('fees.index', [
            'filters' => $filters,
            'invoices' => $query
                ->with(['student', 'enrollment.schoolClass', 'enrollment.section'])
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->paginate((int) config('school.pagination.per_page', 15))
                ->withQueryString(),
            'summary' => [
                'total' => (float) ($summary->total ?? 0),
                'paid' => (float) ($summary->paid ?? 0),
                'due' => (float) ($summary->due ?? 0),
                'count' => (int) ($summary->invoice_count ?? 0),
                'overdue' => $overdue,
            ],
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name']),
            'classes' => SchoolClass::query()->orderBy('level')->get(['id', 'name']),
            'methods' => FeePayment::METHODS,
        ]);
    }

    public function create(Request $request): View
    {
        $this->requirePermission('fees.manage');

        $sessions = AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name', 'is_current']);
        $session = $this->resolveSession($request, $sessions);

        $search = trim((string) $request->query('search'));

        $students = Student::query()
            ->where(function (Builder $query) use ($session): void {
                $query->whereHas('enrollments', fn (Builder $enrollment): Builder => $enrollment->where('status', 'active'));
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $inner) use ($like, $search): void {
                    $inner->where('student_id', 'like', $like)
                        ->orWhere('admission_no', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhereHas('enrollments', fn (Builder $enrollment): Builder => $enrollment->where('roll_no', 'like', $search));
                });
            })
            ->orderBy('first_name')
            ->limit(30)
            ->get(['id', 'student_id', 'first_name', 'last_name']);

        $student = $request->filled('student')
            ? Student::query()->findOrFail((int) $request->query('student'))
            : null;

        $issueDate = Carbon::parse((string) $request->query('issue_date', now()->toDateString()));

        $quote = $student === null || $session === null
            ? ['enrollment' => null, 'lines' => [], 'existing' => collect()]
            : $this->invoices->quoteFor($student, $session, $issueDate);

        return view('fees.create', [
            'sessions' => $sessions,
            'session' => $session,
            'search' => $search,
            'students' => $students,
            'student' => $student,
            'enrollment' => $quote['enrollment'],
            'lines' => $quote['lines'],
            'existing' => $quote['existing'],
            'billingPeriod' => (string) $request->query('billing_period', now()->format('Y-m')),
            'issueDate' => $issueDate->toDateString(),
            'dueDate' => (string) $request->query('due_date', $issueDate->copy()->addDays(10)->toDateString()),
        ]);
    }

    public function generate(GenerateFeeInvoicesRequest $request): RedirectResponse
    {
        $result = $this->invoices->generate($request->user(), $request->validated());

        $message = trans_choice(
            ':count invoice was generated (:total).|:count invoices were generated (:total).',
            count($result['invoices']),
            ['count' => count($result['invoices']), 'total' => number_format($result['total'], 2)],
        );

        if ($result['skipped'] > 0) {
            $message .= ' '.__(':count students were skipped because they already had an invoice for this period.', ['count' => $result['skipped']]);
        }

        $redirect = count($result['invoices']) === 1
            ? redirect()->route('fees.show', $result['invoices'][0])
            : redirect()->route('fees.index');

        return $redirect->with('success', $message);
    }

    public function show(FeeInvoice $invoice): View
    {
        $this->requirePermission('fees.view');

        $invoice->load([
            'student',
            'enrollment.schoolClass',
            'enrollment.section',
            'academicSession',
            'items.feeType',
            'discounts.feeDiscount',
            'allocations.payment.receipt',
            'creator',
        ]);

        return view('fees.show', [
            'invoice' => $invoice,
            'discounts' => FeeDiscount::query()
                ->active()
                ->with('feeType')
                ->orderBy('name')
                ->get(),
            'idempotencyKey' => (string) Str::uuid(),
            'methods' => FeePayment::METHODS,
            'bankAccounts' => BankAccount::query()->active()->orderBy('name')->get(),
            'verifyMethods' => $this->verificationMethods(),
        ]);
    }

    public function cancel(Request $request, FeeInvoice $invoice): RedirectResponse
    {
        $this->requirePermission('fees.manage');

        $this->invoices->cancel($request->user(), $invoice);

        return back()->with('success', __('Invoice :invoice was cancelled.', ['invoice' => $invoice->invoice_no]));
    }

    public function storeDiscount(ApplyFeeDiscountRequest $request, FeeInvoice $invoice): RedirectResponse
    {
        $this->invoices->applyDiscount($request->user(), $invoice, $request->validated());

        return back()->with('success', __('The discount was applied to this invoice.'));
    }

    public function destroyDiscount(FeeInvoice $invoice, FeeInvoiceDiscount $discount): RedirectResponse
    {
        $this->requirePermission('fees.manage');

        $this->invoices->removeDiscount(request()->user(), $invoice, $discount);

        return back()->with('success', __('The discount was removed from this invoice.'));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->requirePermission('fees.export');

        $filters = $this->filters($request);

        $invoices = $this->filteredQuery($filters)
            ->with(['student', 'enrollment.schoolClass', 'enrollment.section'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();

        return response()->streamDownload(function () use ($invoices): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                __('Invoice'), __('Student ID'), __('Student'), __('Class'), __('Section'),
                __('Roll'), __('Period'), __('Issued'), __('Due date'),
                __('Total'), __('Paid'), __('Due'), __('Status'),
            ]);

            foreach ($invoices as $invoice) {
                fputcsv($out, [
                    $invoice->invoice_no,
                    $invoice->student?->student_id,
                    $invoice->student?->full_name,
                    $invoice->enrollment?->schoolClass?->name,
                    $invoice->enrollment?->section?->name,
                    $invoice->enrollment?->roll_no,
                    $invoice->billing_period,
                    $invoice->issue_date?->toDateString(),
                    $invoice->due_date?->toDateString(),
                    number_format((float) $invoice->total, 2, '.', ''),
                    number_format((float) $invoice->paid_total, 2, '.', ''),
                    number_format((float) $invoice->due_total, 2, '.', ''),
                    $invoice->status,
                ]);
            }

            fclose($out);
        }, 'fee-invoices.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Payment methods whose money arrives through a bank or wallet, so the
     * cashier can choose to hold them until the statement confirms the payment.
     *
     * @return list<string>
     */
    private function verificationMethods(): array
    {
        $map = (array) config('finance.method_channels', []);

        return array_values(array_filter(
            FeePayment::METHODS,
            fn (string $method): bool => ($map[$method] ?? 'cash') !== 'cash',
        ));
    }

    private function resolveSession(Request $request, \Illuminate\Support\Collection $sessions): ?AcademicSession
    {
        $id = $request->query('session_id');

        if ($id !== null && $id !== '') {
            $found = $sessions->firstWhere('id', (int) $id);

            if ($found !== null) {
                return AcademicSession::query()->find($found->id);
            }
        }

        return AcademicSession::query()->current()->first()
            ?? $sessions->firstWhere('is_current', true)
            ?? $sessions->first();
    }

    /**
     * @return array{search: string, session_id: string, class_id: string, status: string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search')),
            'session_id' => (string) $request->query('session_id', ''),
            'class_id' => (string) $request->query('class_id', ''),
            'status' => (string) $request->query('status', ''),
        ];
    }

    /**
     * @param  array{search: string, session_id: string, class_id: string, status: string}  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        return FeeInvoice::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('invoice_no', 'like', $like)
                        ->orWhere('billing_period', 'like', $like)
                        ->orWhereHas('student', function (Builder $query) use ($like): void {
                            $query->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhere('student_id', 'like', $like)
                                ->orWhere('admission_no', 'like', $like);
                        });
                });
            })
            ->when($filters['session_id'] !== '', fn (Builder $query) => $query->where('academic_session_id', (int) $filters['session_id']))
            ->when($filters['class_id'] !== '', fn (Builder $query) => $query->whereHas(
                'enrollment',
                fn (Builder $query) => $query->where('class_id', (int) $filters['class_id']),
            ))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']));
    }
}
