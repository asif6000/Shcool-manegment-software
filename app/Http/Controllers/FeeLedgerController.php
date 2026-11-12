<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\FeeInvoice;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeLedgerController extends Controller
{
    public function index(Request $request): View
    {
        $this->requirePermission('fees.view');

        $filters = $this->filters($request);

        return view('fees.ledger', [
            'filters' => $filters,
            'students' => $this->query($filters)
                ->paginate((int) config('school.pagination.per_page', 15))
                ->withQueryString(),
            'summary' => $this->summary($filters),
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(['id', 'name']),
            'classes' => SchoolClass::query()->orderBy('level')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->requirePermission('fees.export');

        $filters = $this->filters($request);

        $students = $this->query($filters)->get();

        return response()->streamDownload(function () use ($students): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                __('Student ID'), __('Student'), __('Class'), __('Section'),
                __('Billed'), __('Paid'), __('Due'),
            ]);

            foreach ($students as $student) {
                $enrollment = $student->enrollments->first();

                fputcsv($out, [
                    $student->student_id,
                    $student->full_name,
                    $enrollment?->schoolClass?->name,
                    $enrollment?->section?->name,
                    number_format((float) ($student->billed_total ?? 0), 2, '.', ''),
                    number_format((float) ($student->paid_total ?? 0), 2, '.', ''),
                    number_format((float) ($student->due_total ?? 0), 2, '.', ''),
                ]);
            }

            fclose($out);
        }, 'student-fee-ledger.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{search: string, session_id: string, class_id: string, standing: string}
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->query('search')),
            'session_id' => (string) $request->query('session_id', ''),
            'class_id' => (string) $request->query('class_id', ''),
            'standing' => in_array($request->query('standing'), ['due', 'clear'], true)
                ? (string) $request->query('standing')
                : '',
        ];
    }

    /**
     * @param  array{search: string, session_id: string, class_id: string, standing: string}  $filters
     * @return array{billed: float, paid: float, due: float, students: int}
     */
    private function summary(array $filters): array
    {
        $query = FeeInvoice::query()
            ->where('status', '<>', 'cancelled')
            ->when($filters['session_id'] !== '', fn (Builder $query) => $query->where('academic_session_id', (int) $filters['session_id']))
            ->when($filters['class_id'] !== '', fn (Builder $query) => $query->whereHas(
                'enrollment',
                fn (Builder $query) => $query->where('class_id', (int) $filters['class_id']),
            ))
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $query->whereHas('student', function (Builder $query) use ($like): void {
                    $query->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('student_id', 'like', $like);
                });
            })
            ->when($filters['standing'] === 'due', fn (Builder $query) => $query->where('due_total', '>', 0))
            ->when($filters['standing'] === 'clear', fn (Builder $query) => $query->where('due_total', '<=', 0));

        $row = $query->selectRaw('COALESCE(SUM(total), 0) as billed, COALESCE(SUM(paid_total), 0) as paid, COALESCE(SUM(due_total), 0) as due')
            ->first();

        return [
            'billed' => (float) ($row->billed ?? 0),
            'paid' => (float) ($row->paid ?? 0),
            'due' => (float) ($row->due ?? 0),
            'students' => $query->distinct()->count('student_id'),
        ];
    }

    /**
     * @param  array{search: string, session_id: string, class_id: string, standing: string}  $filters
     */
    private function query(array $filters): Builder
    {
        $invoiceFilter = function (Builder $query) use ($filters): void {
            $query->where('status', '<>', 'cancelled')
                ->when($filters['session_id'] !== '', fn (Builder $query) => $query->where('academic_session_id', (int) $filters['session_id']));
        };

        return Student::query()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('student_id', 'like', $like);
                });
            })
            ->when($filters['class_id'] !== '', fn (Builder $query) => $query->whereHas(
                'enrollments',
                fn (Builder $query) => $query->where('class_id', (int) $filters['class_id'])->where('status', 'active'),
            ))
            ->whereHas('feeInvoices', $invoiceFilter)
            ->when($filters['standing'] === 'due', fn (Builder $query) => $query->whereHas(
                'feeInvoices',
                function (Builder $query) use ($invoiceFilter): void {
                    $invoiceFilter($query);
                    $query->where('due_total', '>', 0);
                },
            ))
            ->when($filters['standing'] === 'clear', fn (Builder $query) => $query->whereDoesntHave(
                'feeInvoices',
                function (Builder $query) use ($invoiceFilter): void {
                    $invoiceFilter($query);
                    $query->where('due_total', '>', 0);
                },
            ))
            ->withSum(['feeInvoices as billed_total' => $invoiceFilter], 'total')
            ->withSum(['feeInvoices as paid_total' => $invoiceFilter], 'paid_total')
            ->withSum(['feeInvoices as due_total' => $invoiceFilter], 'due_total')
            ->with(['enrollments' => fn ($query) => $query
                ->where('status', 'active')
                ->with(['schoolClass:id,name', 'section:id,name'])
                ->latest('enrolled_on')])
            ->orderBy('first_name')
            ->orderBy('last_name');
    }
}
