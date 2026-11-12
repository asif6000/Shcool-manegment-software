<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\ClassSubject;
use App\Models\Holiday;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Services\PermanentDeleteService;
use App\Support\AuditLogger;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademicController extends Controller
{
    public function index(Request $request): View
    {
        $this->requirePermission('academic.view');

        $sessions = AcademicSession::query()
            ->withCount('enrollments')
            ->orderByDesc('start_date')
            ->get();

        $current = $sessions->firstWhere('is_current', true);
        $selectedSession = $request->filled('session')
            ? $sessions->firstWhere('id', (int) $request->integer('session'))
            : ($current ?? $sessions->first());

        $selectedClassId = $request->integer('class') ?: null;

        return view('academic.index', [
            'sessions' => $sessions,
            'currentSession' => $current,
            'selectedSession' => $selectedSession,
            'selectedClassId' => $selectedClassId,
            'classes' => SchoolClass::query()
                ->with(['sections' => fn ($query) => $query->orderBy('name')])
                ->withCount('enrollments')
                ->orderBy('level')
                ->orderBy('name')
                ->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
            'holidays' => Holiday::query()
                ->with('academicSession')
                ->orderByDesc('start_date')
                ->get(),
            'curriculum' => $selectedSession
                ? ClassSubject::query()
                    ->where('academic_session_id', $selectedSession->id)
                    ->with(['schoolClass', 'subject'])
                    ->get()
                    ->groupBy('class_id')
                : collect(),
        ]);
    }

    public function storeSession(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $data = $this->validateSession($request);

        $session = DB::transaction(function () use ($data, $audit): AcademicSession {
            if (($data['is_current'] ?? false) === true) {
                AcademicSession::query()->where('is_current', true)->update(['is_current' => false]);
            }

            $session = AcademicSession::query()->create($data);

            if (! AcademicSession::query()->where('is_current', true)->exists()) {
                $session->forceFill(['is_current' => true, 'status' => 'active'])->save();
            }

            $audit->log(
                event: 'academic.session_created',
                auditable: $session,
                description: __('Academic session :name was created', ['name' => $session->name]),
                newValues: $session->only(['name', 'start_date', 'end_date', 'status', 'is_current']),
            );

            return $session;
        });

        return back()->with('success', __('Academic session :name was saved.', ['name' => $session->name]));
    }

    public function updateSession(Request $request, AcademicSession $session, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $data = $this->validateSession($request, $session);

        DB::transaction(function () use ($session, $data, $audit): void {
            $old = $session->only(['name', 'start_date', 'end_date', 'status', 'is_current']);

            if (($data['is_current'] ?? false) === true) {
                AcademicSession::query()->whereKeyNot($session->id)->update(['is_current' => false]);
                $data['status'] = 'active';
            }

            $session->update($data);

            if (! AcademicSession::query()->where('is_current', true)->exists()) {
                $session->forceFill(['is_current' => true])->save();
            }

            $audit->log(
                event: 'academic.session_updated',
                auditable: $session,
                description: __('Academic session :name was updated', ['name' => $session->name]),
                oldValues: $old,
                newValues: $session->only(['name', 'start_date', 'end_date', 'status', 'is_current']),
            );
        });

        return back()->with('success', __('Academic session :name was updated.', ['name' => $session->name]));
    }

    public function setCurrentSession(AcademicSession $session, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        DB::transaction(function () use ($session, $audit): void {
            AcademicSession::query()->whereKeyNot($session->id)->update(['is_current' => false]);
            $session->forceFill(['is_current' => true, 'status' => 'active'])->save();

            $audit->log(
                event: 'academic.session_activated',
                auditable: $session,
                description: __('Academic session :name is now current', ['name' => $session->name]),
                newValues: ['is_current' => true],
            );
        });

        return back()->with('success', __(':name is now the current session.', ['name' => $session->name]));
    }

    public function destroySession(AcademicSession $session, PermanentDeleteService $deletes): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        if ($session->is_current) {
            throw ValidationException::withMessages([
                'session' => __('Choose another current session before deleting this one.'),
            ]);
        }

        $name = $session->name;
        $deletes->delete($session, ['label' => __('academic session')]);

        return back()->with('success', __('Academic session :name was permanently deleted.', ['name' => $name]));
    }

    public function storeClass(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $data = $this->validateClass($request);

        $class = SchoolClass::query()->create($data);

        $audit->log(
            event: 'academic.class_created',
            auditable: $class,
            description: __('Class :name was created', ['name' => $class->name]),
            newValues: $class->only(['name', 'level', 'is_active']),
        );

        return back()->with('success', __('Class :name was saved.', ['name' => $class->name]));
    }

    public function updateClass(Request $request, SchoolClass $class, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $old = $class->only(['name', 'level', 'is_active']);
        $class->update($this->validateClass($request, $class));

        $audit->log(
            event: 'academic.class_updated',
            auditable: $class,
            description: __('Class :name was updated', ['name' => $class->name]),
            oldValues: $old,
            newValues: $class->only(['name', 'level', 'is_active']),
        );

        return back()->with('success', __('Class :name was updated.', ['name' => $class->name]));
    }

    public function destroyClass(SchoolClass $class, PermanentDeleteService $deletes): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $name = $class->name;
        $deletes->delete($class, ['label' => __('class')]);

        return back()->with('success', __('Class :name was permanently deleted.', ['name' => $name]));
    }

    public function storeSection(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $data = $this->validateSection($request);
        $section = Section::query()->create($data);
        $section->load('schoolClass');

        $audit->log(
            event: 'academic.section_created',
            auditable: $section,
            description: __('Section :name was created', ['name' => $section->name]),
            newValues: $section->only(['class_id', 'name', 'capacity', 'is_active']),
        );

        return back()->with('success', __('Section :name was saved.', ['name' => $section->name]));
    }

    public function updateSection(Request $request, Section $section, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $old = $section->only(['class_id', 'name', 'capacity', 'is_active']);
        $section->update($this->validateSection($request, $section));

        $audit->log(
            event: 'academic.section_updated',
            auditable: $section,
            description: __('Section :name was updated', ['name' => $section->name]),
            oldValues: $old,
            newValues: $section->only(['class_id', 'name', 'capacity', 'is_active']),
        );

        return back()->with('success', __('Section :name was updated.', ['name' => $section->name]));
    }

    public function destroySection(Section $section, PermanentDeleteService $deletes): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $name = $section->name;
        $deletes->delete($section, ['label' => __('section')]);

        return back()->with('success', __('Section :name was permanently deleted.', ['name' => $name]));
    }

    public function storeSubject(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $subject = Subject::query()->create($this->validateSubject($request));

        $audit->log(
            event: 'academic.subject_created',
            auditable: $subject,
            description: __('Subject :name was created', ['name' => $subject->name]),
            newValues: $subject->only(['name', 'code', 'type', 'is_active']),
        );

        return back()->with('success', __('Subject :name was saved.', ['name' => $subject->name]));
    }

    public function updateSubject(Request $request, Subject $subject, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $old = $subject->only(['name', 'code', 'type', 'is_active']);
        $subject->update($this->validateSubject($request, $subject));

        $audit->log(
            event: 'academic.subject_updated',
            auditable: $subject,
            description: __('Subject :name was updated', ['name' => $subject->name]),
            oldValues: $old,
            newValues: $subject->only(['name', 'code', 'type', 'is_active']),
        );

        return back()->with('success', __('Subject :name was updated.', ['name' => $subject->name]));
    }

    public function destroySubject(Subject $subject, PermanentDeleteService $deletes): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $name = $subject->name;
        $deletes->delete($subject, ['label' => __('subject')]);

        return back()->with('success', __('Subject :name was permanently deleted.', ['name' => $name]));
    }

    public function updateCurriculum(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $schoolId = app(SchoolContext::class)->id();
        abort_if($schoolId === null, 403);

        $data = $request->validate([
            'academic_session_id' => ['required', 'integer', Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId)],
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')->where('school_id', $schoolId)->whereNull('deleted_at')],
            'subjects' => ['nullable', 'array'],
            'subjects.*.enabled' => ['nullable', 'boolean'],
            'subjects.*.full_marks' => ['nullable', 'numeric', 'min:1', 'max:1000'],
            'subjects.*.pass_marks' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'subjects.*.is_optional' => ['nullable', 'boolean'],
        ]);

        $subjectIds = Subject::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $changed = 0;

        DB::transaction(function () use ($data, $subjectIds, $audit, &$changed): void {
            foreach ($data['subjects'] ?? [] as $subjectId => $row) {
                $subjectId = (int) $subjectId;

                if (! in_array($subjectId, $subjectIds, true)) {
                    continue;
                }

                if (empty($row['enabled'])) {
                    $changed += ClassSubject::query()
                        ->where('academic_session_id', $data['academic_session_id'])
                        ->where('class_id', $data['class_id'])
                        ->where('subject_id', $subjectId)
                        ->delete();

                    continue;
                }

                $fullMarks = (float) ($row['full_marks'] ?? 100);
                $passMarks = (float) ($row['pass_marks'] ?? min(33, $fullMarks));

                if ($passMarks > $fullMarks) {
                    throw ValidationException::withMessages([
                        'subjects' => __('Pass marks cannot exceed full marks.'),
                    ]);
                }

                ClassSubject::query()->updateOrCreate(
                    [
                        'academic_session_id' => $data['academic_session_id'],
                        'class_id' => $data['class_id'],
                        'subject_id' => $subjectId,
                    ],
                    [
                        'full_marks' => $fullMarks,
                        'pass_marks' => $passMarks,
                        'is_optional' => (bool) ($row['is_optional'] ?? false),
                    ],
                );

                $changed++;
            }

            if ($changed > 0) {
                $audit->log(
                    event: 'academic.curriculum_updated',
                    auditable: SchoolClass::query()->find($data['class_id']),
                    description: __('Class curriculum was updated'),
                    newValues: ['session_id' => $data['academic_session_id'], 'subjects_changed' => $changed],
                );
            }
        });

        return back()->with('success', __('Curriculum saved. :count subject rows were updated.', ['count' => $changed]));
    }

    public function storeHoliday(Request $request, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $holiday = Holiday::query()->create($this->validateHoliday($request));

        $audit->log(
            event: 'academic.holiday_created',
            auditable: $holiday,
            description: __('Holiday :name was created', ['name' => $holiday->name]),
            newValues: $holiday->only(['name', 'start_date', 'end_date', 'type']),
        );

        return back()->with('success', __('Holiday :name was saved.', ['name' => $holiday->name]));
    }

    public function updateHoliday(Request $request, Holiday $holiday, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $old = $holiday->only(['name', 'start_date', 'end_date', 'type']);
        $holiday->update($this->validateHoliday($request));

        $audit->log(
            event: 'academic.holiday_updated',
            auditable: $holiday,
            description: __('Holiday :name was updated', ['name' => $holiday->name]),
            oldValues: $old,
            newValues: $holiday->only(['name', 'start_date', 'end_date', 'type']),
        );

        return back()->with('success', __('Holiday :name was updated.', ['name' => $holiday->name]));
    }

    public function destroyHoliday(Holiday $holiday, PermanentDeleteService $deletes): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $name = $holiday->name;
        $deletes->delete($holiday, ['label' => __('holiday')]);

        return back()->with('success', __('Holiday :name was permanently deleted.', ['name' => $name]));
    }

    private function validateSession(Request $request, ?AcademicSession $session = null): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return $request->validate([
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('academic_sessions', 'name')->where('school_id', $schoolId)->ignore($session?->id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['upcoming', 'active', 'completed'])],
            'is_current' => ['nullable', 'boolean'],
        ]) + ['is_current' => $request->boolean('is_current')];
    }

    private function validateClass(Request $request, ?SchoolClass $class = null): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return $request->validate([
            'name' => [
                'required', 'string', 'max:60',
                Rule::unique('classes', 'name')->where('school_id', $schoolId)->ignore($class?->id),
            ],
            'level' => ['required', 'integer', 'min:0', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }

    private function validateSection(Request $request, ?Section $section = null): array
    {
        $schoolId = app(SchoolContext::class)->id();
        $classId = $request->integer('class_id');

        $class = SchoolClass::query()->findOrFail($classId);
        abort_unless($class->school_id === $schoolId, 404);

        return $request->validate([
            'class_id' => ['required', 'integer'],
            'name' => [
                'required', 'string', 'max:30',
                Rule::unique('sections', 'name')
                    ->where('school_id', $schoolId)
                    ->where('class_id', $classId)
                    ->ignore($section?->id),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }

    private function validateSubject(Request $request, ?Subject $subject = null): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return $request->validate([
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('subjects', 'name')->where('school_id', $schoolId)->ignore($subject?->id),
            ],
            'code' => [
                'nullable', 'string', 'max:30',
                Rule::unique('subjects', 'code')->where('school_id', $schoolId)->ignore($subject?->id),
            ],
            'type' => ['required', Rule::in(['theory', 'practical', 'both'])],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }

    private function validateHoliday(Request $request): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return $request->validate([
            'academic_session_id' => ['nullable', 'integer', Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId)],
            'name' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['required', Rule::in(['public', 'school', 'exam', 'vacation'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
