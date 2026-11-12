<?php

namespace App\Http\Controllers;

use App\Http\Requests\Timetable\SaveSlotRequest;
use App\Models\AcademicSession;
use App\Models\AuditLog;
use App\Models\ClassRoutine;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Services\TeachingScope;
use App\Services\TimetableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TimetableController extends Controller
{
    public function __construct(
        private readonly TimetableService $timetable,
        private readonly TeachingScope $teaching,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission('timetable.view');

        $sessions = AcademicSession::query()->orderByDesc('start_date')->get();
        $session = $this->resolveSession($request, $sessions);

        $classes = $this->teaching->classes(
            $request->user(),
            $session,
            SchoolClass::query()->orderBy('level')->orderBy('name')->get(['id', 'name', 'level']),
        );
        $requestedClassId = $request->integer('class');
        $classId = $classes->contains('id', $requestedClassId)
            ? $requestedClassId
            : $classes->first()?->id;
        $classId = $classId === null ? null : (int) $classId;

        $sections = Section::query()
            ->where('class_id', (int) $classId)
            ->orderBy('name')
            ->get(['id', 'name']);

        // A teacher only browses the classes and sections on their own
        // timetable. Administrators and principals stay unrestricted.
        if (! $this->teaching->isUnrestricted($request->user())) {
            $allowedSections = $session === null || $classId === null
                ? collect()
                : ($this->teaching->sectionIds($request->user(), $session, $classId) ?? collect());

            $sections = $sections->whereIn('id', $allowedSections)->values();
        }

        $requestedSectionId = $request->integer('section');
        $sectionId = $sections->contains('id', $requestedSectionId)
            ? $requestedSectionId
            : $sections->first()?->id;
        $sectionId = $sectionId === null ? null : (int) $sectionId;

        $slots = $sectionId
            ? ClassRoutine::query()
                ->with(['subject', 'teacher'])
                ->where('academic_session_id', (int) ($session?->id ?? 0))
                ->where('class_id', (int) $classId)
                ->where('section_id', (int) $sectionId)
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get()
                ->groupBy('day_of_week')
            : collect();

        return view('timetable.index', [
            'sessions' => $sessions,
            'session' => $session,
            'classes' => $classes,
            'classId' => $classId,
            'sections' => $sections,
            'sectionId' => $sectionId,
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'teachers' => Teacher::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
            'days' => ClassRoutine::DAYS,
            'slots' => $slots,
            'totalSlots' => $slots->sum(fn ($rows): int => $rows->count()),
            'assignmentHistory' => $this->assignmentHistory($request),
        ]);
    }

    public function store(SaveSlotRequest $request): RedirectResponse
    {
        $this->requireSchoolAdmin();

        $this->timetable->create($request->user(), $request->validated());

        return $this->backToGrid($request, __('The class period was added.'));
    }

    public function update(SaveSlotRequest $request, ClassRoutine $slot): RedirectResponse
    {
        $this->requireSchoolAdmin();

        $this->timetable->update($request->user(), $slot, $request->validated());

        return $this->backToGrid($request, __('The class period was updated.'));
    }

    public function destroy(Request $request, ClassRoutine $slot): RedirectResponse
    {
        $this->requireSchoolAdmin();
        $this->requirePermission('timetable.manage');

        $this->timetable->delete($request->user(), $slot);

        return $this->backToGrid($request, __('The class period was removed.'));
    }

    /**
     * The admin-only record of which teacher was given which class, section and
     * subject. It is read from the same audit trail the timetable writes.
     */
    private function assignmentHistory(Request $request): \Illuminate\Support\Collection
    {
        if (! $request->user()->hasRole('school_admin')) {
            return collect();
        }

        return AuditLog::query()
            ->whereIn('event', ['timetable.slot.created', 'timetable.slot.updated'])
            ->with('user')
            ->latest('created_at')
            ->limit(20)
            ->get();
    }

    private function requireSchoolAdmin(): void
    {
        abort_unless(
            auth()->user()?->hasRole('school_admin'),
            403,
            __('Only the school administrator can assign subjects to teachers.'),
        );
    }

    /**
     * The grid is the only screen for this module, so every form returns to the
     * same class/section view the staff member was already looking at.
     */
    private function backToGrid(Request $request, string $message): RedirectResponse
    {
        return redirect()
            ->route('timetable.index', [
                'session' => $request->input('session'),
                'class' => $request->input('class'),
                'section' => $request->input('section'),
            ])
            ->with('success', $message);
    }

    /**
     * @param  Collection<int, AcademicSession>  $sessions
     */
    private function resolveSession(Request $request, Collection $sessions): ?AcademicSession
    {
        $id = $request->integer('session') ?: null;

        if ($id !== null) {
            return $sessions->firstWhere('id', $id);
        }

        return $sessions->firstWhere('is_current', true)
            ?? $sessions->firstWhere('status', 'active')
            ?? $sessions->first();
    }
}
