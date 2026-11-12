<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherAssignment\SaveTeacherAssignmentRequest;
use App\Models\AcademicSession;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class TeacherAssignmentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', TeacherAssignment::class);

        $sessions = AcademicSession::query()->orderByDesc('start_date')->get();
        $session = $this->resolveSession($request, $sessions);
        $ready = Schema::hasTable('teacher_assignments');

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'teacher' => $request->integer('teacher') ?: null,
            'class' => $request->integer('class') ?: null,
            'subject' => $request->integer('subject') ?: null,
            'status' => in_array($request->query('status'), TeacherAssignment::STATUSES, true)
                ? (string) $request->query('status')
                : null,
        ];

        $assignments = ! $ready || $session === null
            ? new LengthAwarePaginator([], 0, $this->perPage(), 1, ['path' => route('teacher_assignments.index')])
            : $this->base($session, $filters)
                ->with(['teacher', 'schoolClass', 'section', 'subject'])
                ->orderByDesc('assigned_on')
                ->orderByDesc('id')
                ->paginate($this->perPage())
                ->withQueryString();

        return view('teacher-assignments.index', [
            ...$this->formData(),
            'sessions' => $sessions,
            'session' => $session,
            'assignments' => $assignments,
            'filters' => $filters,
            'ready' => $ready,
        ]);
    }

    public function store(SaveTeacherAssignmentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $assignment = DB::transaction(function () use ($request, $data): TeacherAssignment {
            $assignment = TeacherAssignment::query()->create($data + [
                'created_by' => $request->user()->id,
            ]);

            $assignment->load(['teacher', 'schoolClass', 'section', 'subject']);

            $this->audit->log(
                event: 'teacher_assignment.created',
                auditable: $assignment,
                description: $this->describe($assignment, 'assigned'),
                newValues: $data,
                actor: $request->user(),
            );

            return $assignment;
        });

        return redirect()
            ->route('teacher_assignments.index', ['session' => $assignment->academic_session_id])
            ->with('success', __('The assignment was saved. The teacher can now use that class, section and subject.'));
    }

    public function edit(Request $request, TeacherAssignment $assignment): View
    {
        $this->authorize('update', $assignment);

        return view('teacher-assignments.edit', [
            ...$this->formData(),
            'assignment' => $assignment,
            'session' => $assignment->academicSession,
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'statuses' => TeacherAssignment::STATUSES,
        ]);
    }

    public function update(SaveTeacherAssignmentRequest $request, TeacherAssignment $assignment): RedirectResponse
    {
        $this->authorize('update', $assignment);

        $data = $request->validated();
        $old = $assignment->only([
            'academic_session_id', 'teacher_id', 'class_id', 'section_id', 'subject_id', 'assigned_on', 'status',
        ]);

        DB::transaction(function () use ($request, $assignment, $data, $old): void {
            $assignment->update($data);
            $assignment->load(['teacher', 'schoolClass', 'section', 'subject']);

            $this->audit->log(
                event: 'teacher_assignment.updated',
                auditable: $assignment,
                description: $this->describe($assignment, 'updated'),
                oldValues: $old,
                newValues: $data,
                actor: $request->user(),
            );
        });

        return redirect()
            ->route('teacher_assignments.index', ['session' => $data['academic_session_id']])
            ->with('success', __('The assignment was updated.'));
    }

    public function destroy(Request $request, TeacherAssignment $assignment): RedirectResponse
    {
        $this->authorize('delete', $assignment);

        $sessionId = $assignment->academic_session_id;
        $old = $assignment->only([
            'academic_session_id', 'teacher_id', 'class_id', 'section_id', 'subject_id', 'assigned_on', 'status',
        ]);

        DB::transaction(function () use ($request, $assignment, $old): void {
            $assignment->load(['teacher', 'schoolClass', 'section', 'subject']);

            $this->audit->log(
                event: 'teacher_assignment.deleted',
                auditable: $assignment,
                description: $this->describe($assignment, 'removed'),
                oldValues: $old,
                actor: $request->user(),
            );

            $assignment->delete();
        });

        return redirect()
            ->route('teacher_assignments.index', ['session' => $sessionId])
            ->with('success', __('The assignment was removed. History already recorded under it is kept.'));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function base(AcademicSession $session, array $filters): Builder
    {
        return TeacherAssignment::query()
            ->where('academic_session_id', $session->id)
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';

                $query->whereHas('teacher', fn (Builder $teacher): Builder => $teacher->where(
                    fn (Builder $name): Builder => $name
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('employee_code', 'like', $term),
                ));
            })
            ->when($filters['teacher'] !== null, fn (Builder $query): Builder => $query->where('teacher_id', $filters['teacher']))
            ->when($filters['class'] !== null, fn (Builder $query): Builder => $query->where('class_id', $filters['class']))
            ->when($filters['subject'] !== null, fn (Builder $query): Builder => $query->where('subject_id', $filters['subject']))
            ->when($filters['status'] !== null, fn (Builder $query): Builder => $query->where('status', $filters['status']));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'teachers' => Teacher::query()
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get(['id', 'first_name', 'last_name', 'employee_code']),
            'classes' => SchoolClass::query()
                ->with(['sections' => fn ($query) => $query->orderBy('name')])
                ->orderBy('level')
                ->orderBy('name')
                ->get(['id', 'name', 'level']),
            'subjects' => Subject::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'sections' => Section::query()->orderBy('class_id')->orderBy('name')->get(['id', 'class_id', 'name']),
        ];
    }

    private function describe(TeacherAssignment $assignment, string $verb): string
    {
        return __(':subject was :verb for :class, section :section, taught by :teacher.', [
            'subject' => $assignment->subject?->name ?? __('Subject'),
            'verb' => $verb,
            'class' => $assignment->schoolClass?->name ?? __('Class'),
            'section' => $assignment->section?->name ?? '—',
            'teacher' => $assignment->teacher?->full_name ?? __('Teacher'),
        ]);
    }

    /**
     * @param  Collection<int, AcademicSession>  $sessions
     */
    private function resolveSession(Request $request, Collection $sessions): ?AcademicSession
    {
        $id = $request->integer('session') ?: null;

        if ($id !== null) {
            $selected = $sessions->firstWhere('id', $id);

            if ($selected !== null) {
                return $selected;
            }
        }

        return $sessions->firstWhere('is_current', true)
            ?? $sessions->firstWhere('status', 'active')
            ?? $sessions->first();
    }

    private function perPage(): int
    {
        return (int) config('school.pagination.per_page', 15);
    }
}
