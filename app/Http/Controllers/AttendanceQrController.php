<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\AttendanceEvent;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendanceCard;
use App\Services\AttendanceEventService;
use App\Services\StudentAttendanceCardService;
use App\Services\TeachingScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AttendanceQrController extends Controller
{
    public function __construct(
        private readonly StudentAttendanceCardService $cards,
        private readonly AttendanceEventService $events,
        private readonly TeachingScope $teaching,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission('attendance.mark');

        $canManageCards = $request->user()->can('attendance.qr.manage');
        $classes = $canManageCards
            ? SchoolClass::query()->with(['sections' => fn ($query) => $query->orderBy('name')])->orderBy('level')->get()
            : collect();
        $class = $request->filled('class_id') ? $classes->firstWhere('id', (int) $request->query('class_id')) : null;
        $section = $class !== null && $request->filled('section_id')
            ? $class->sections->firstWhere('id', (int) $request->query('section_id'))
            : null;

        return view('attendance.qr', [
            'canManageCards' => $canManageCards,
            'classes' => $classes,
            'class' => $class,
            'section' => $section,
            'roster' => $class !== null ? $this->roster($class, $section) : collect(),
            'today' => now()->startOfDay(),
            'recent' => AttendanceEvent::query()
                ->where('source', 'qr')
                ->where('received_at', '>=', now()->startOfDay())
                ->when(
                    ! $this->teaching->isUnrestricted($request->user()),
                    fn (Builder $query): Builder => $query->whereHas(
                        'student',
                        fn (Builder $student): Builder => $this->teaching->constrainStudents(
                            $request->user(),
                            $this->teaching->currentSession(),
                            $student,
                        ),
                    ),
                )
                ->with(['student', 'attendance'])
                ->latest('received_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function scan(Request $request): JsonResponse|RedirectResponse
    {
        $this->requirePermission('attendance.mark');

        $data = $request->validate([
            'token' => ['required', 'string', 'max:256'],
        ]);

        $card = $this->cards->resolve($data['token']);

        if ($card === null || $card->student === null) {
            $message = __('This card is not valid or has been revoked.');

            return $request->expectsJson()
                ? response()->json(['status' => 'rejected', 'message' => $message], 422)
                : redirect()->route('attendance.qr.index')->with('warning', $message);
        }

        $event = $this->events->ingest(
            actor: $request->user(),
            student: $card->student,
            source: 'qr',
            externalRef: 'qr:'.Str::uuid(),
            occurredAt: now(),
        );

        if (in_array($event->status, [AttendanceEvent::STATUS_APPLIED, AttendanceEvent::STATUS_DUPLICATE], true)) {
            $card->forceFill(['last_scanned_at' => now()])->save();
        }

        $enrollment = $card->student->currentEnrollment();
        $message = $event->reasonLabel($card->student->full_name);

        if (! $request->expectsJson()) {
            return redirect()
                ->route('attendance.qr.index')
                ->with($event->status === AttendanceEvent::STATUS_APPLIED ? 'success' : 'warning', $message);
        }

        return response()->json([
            'status' => $event->status,
            'message' => $message,
            'student' => [
                'name' => $card->student->full_name,
                'student_id' => $card->student->student_id,
                'class' => $enrollment?->schoolClass?->name,
                'section' => $enrollment?->section?->name,
            ],
            'time' => now()->format('H:i'),
        ]);
    }

    public function issue(Request $request, Student $student): RedirectResponse
    {
        $this->requirePermission('attendance.qr.manage');

        ['token' => $token] = $this->cards->issue($student, $request->user());

        return redirect()
            ->route('attendance.cards.print', $student)
            ->with('attendance_card_token', $token)
            ->with('success', __('A new attendance card was issued for :name. Print it now; the code is shown once.', [
                'name' => $student->full_name,
            ]));
    }

    public function printCard(Request $request, Student $student): Response
    {
        $this->requirePermission('attendance.qr.manage');

        return response()
            ->view('attendance.card', [
                'student' => $student,
                'enrollment' => $student->currentEnrollment(),
                'token' => $request->session()->get('attendance_card_token'),
                'card' => $this->cards->activeFor($student),
            ])
            ->header('Cache-Control', 'no-store, private');
    }

    public function revoke(Request $request, StudentAttendanceCard $card): RedirectResponse
    {
        $this->requirePermission('attendance.qr.manage');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->cards->revoke($card, $request->user(), $data['reason'] ?? null);

        return back()->with('success', __('The attendance card was revoked.'));
    }

    /**
     * @return Collection<int, array{enrollment: Enrollment, card: ?StudentAttendanceCard}>
     */
    private function roster(SchoolClass $class, ?Section $section): Collection
    {
        $session = AcademicSession::query()
            ->where('is_current', true)
            ->orderByDesc('start_date')
            ->first();

        if ($session === null) {
            return collect();
        }

        $enrollments = Enrollment::query()
            ->with(['student', 'section'])
            ->where('academic_session_id', $session->id)
            ->where('class_id', $class->id)
            ->when($section !== null, fn ($query) => $query->where('section_id', $section->id))
            ->where('status', 'active')
            ->whereHas('student')
            ->orderBy('section_id')
            ->orderBy('roll_no')
            ->get();

        $cards = StudentAttendanceCard::query()
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->active()
            ->get()
            ->keyBy('student_id');

        return $enrollments->map(fn (Enrollment $enrollment): array => [
            'enrollment' => $enrollment,
            'card' => $cards->get($enrollment->student_id),
        ]);
    }
}
