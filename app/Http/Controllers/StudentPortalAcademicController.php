<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPortalStudent;
use App\Models\AcademicSession;
use App\Models\Notice;
use App\Services\StudentPortalService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StudentPortalAcademicController extends Controller
{
    use ResolvesPortalStudent;

    public function __construct(private readonly StudentPortalService $portal) {}

    public function routine(Request $request): View
    {
        $student = $this->student($request);
        $session = $this->session();
        $enrollment = $student->currentEnrollment();

        $data = $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $date = isset($data['date']) ? Carbon::parse($data['date']) : now();

        return view('student.routine', [
            'student' => $student,
            'session' => $session,
            'enrollment' => $enrollment,
            'weekStart' => $date->copy()->startOfDay(),
            'days' => $this->portal->weekRoutine($enrollment, $session?->id, $date),
        ]);
    }

    public function calendar(Request $request): View
    {
        $student = $this->student($request);
        $session = $this->session();

        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = isset($data['month'])
            ? Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth()
            : now()->startOfMonth();

        return view('student.calendar', [
            'student' => $student,
            'session' => $session,
            ...$this->portal->calendarMonth($student, $student->currentEnrollment(), $session?->id, $month),
        ]);
    }

    public function notices(Request $request): View
    {
        $student = $this->student($request);

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $term = trim((string) ($data['q'] ?? ''));

        $notices = Notice::query()
            ->published()
            ->whereIn('audience', ['all', 'students'])
            ->when($term !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', '%'.$term.'%')
                ->orWhere('body', 'like', '%'.$term.'%')))
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->paginate((int) config('school.pagination.per_page', 15))
            ->withQueryString();

        return view('student.notices.index', [
            'student' => $student,
            'notices' => $notices,
            'term' => $term,
        ]);
    }

    public function notice(Request $request, int $notice): View
    {
        $student = $this->student($request);

        // Only published notices addressed to students or everyone resolve here;
        // staff-only notices stay out of reach even with a known id.
        $record = Notice::query()
            ->published()
            ->whereIn('audience', ['all', 'students'])
            ->findOrFail($notice);

        return view('student.notices.show', [
            'student' => $student,
            'notice' => $record,
        ]);
    }

    private function session(): ?AcademicSession
    {
        return AcademicSession::query()
            ->where('is_current', true)
            ->orderByDesc('start_date')
            ->first();
    }
}
