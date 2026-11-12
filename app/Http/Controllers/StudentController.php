<?php

namespace App\Http\Controllers;

use App\Http\Requests\Students\SaveStudentRequest;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Services\StudentService;
use App\Services\TeachingScope;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentService $students,
        private readonly TeachingScope $teaching,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission('students.view');

        $filters = $request->only(['search', 'class_id', 'section_id', 'session_id', 'status']);

        $sections = filled($filters['class_id'] ?? null)
            ? Section::query()->where('class_id', (int) $filters['class_id'])->orderBy('name')->get(['id', 'name'])
            : collect();

        // A section only means something inside the class it belongs to, so a
        // leftover section filter from a previous class is dropped rather than
        // quietly emptying the list.
        if (filled($filters['section_id'] ?? null) && ! $sections->contains('id', (int) $filters['section_id'])) {
            unset($filters['section_id']);
        }

        return view('students.index', [
            'students' => $this->students->paginate($filters, $request->user()),
            'filters' => $filters,
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'sections' => $sections,
            'classes' => $this->teaching->classes(
                $request->user(),
                $this->teaching->currentSession(),
                SchoolClass::query()->orderBy('level')->orderBy('name')->get(['id', 'name']),
            ),
        ]);
    }

    public function create(): View
    {
        $this->requirePermission('students.create');

        return view('students.create', $this->formData());
    }

    public function store(SaveStudentRequest $request): RedirectResponse
    {
        $student = $this->students->create(
            $request->user(),
            $request->validated(),
            $request->file('photo'),
        );

        return redirect()
            ->route('students.show', $student)
            ->with('success', __('Student :name was admitted with ID :id.', [
                'name' => $student->full_name,
                'id' => $student->student_id,
            ]));
    }

    public function show(Request $request, Student $student): View
    {
        $this->requirePermission('students.view');
        $this->authorizeStudentAccess($request, $student);

        $student->load([
            'user',
            'guardians',
            'enrollments' => fn ($query) => $query->with(['academicSession', 'schoolClass', 'section'])->orderByDesc('enrolled_on'),
        ]);

        return view('students.show', [
            'student' => $student,
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::query()
                ->with(['sections' => fn ($query) => $query->orderBy('name')])
                ->orderBy('level')
                ->get(),
        ]);
    }

    public function edit(Student $student): View
    {
        $this->requirePermission('students.update');

        $student->load(['guardians', 'enrollments']);

        return view('students.edit', [
            'student' => $student,
            ...$this->formData(),
        ]);
    }

    public function update(SaveStudentRequest $request, Student $student): RedirectResponse
    {
        $this->students->update(
            $request->user(),
            $student,
            $request->validated(),
            $request->file('photo'),
        );

        return redirect()
            ->route('students.show', $student)
            ->with('success', __('Student :name was updated.', ['name' => $student->full_name]));
    }

    public function archive(Request $request, Student $student): RedirectResponse
    {
        $this->requirePermission('students.delete');

        $request->validate([
            'archive_confirmed' => ['accepted'],
        ]);

        $name = $student->full_name;
        $this->students->archive($request->user(), $student);

        return redirect()
            ->route('students.show', $student)
            ->with('success', __('Student :name was archived. Attendance, marks, results and audit history were kept.', ['name' => $name]));
    }

    public function destroyLoginAccount(Request $request, Student $student): RedirectResponse
    {
        $this->requirePermission('students.delete');

        $name = $student->full_name;
        $this->students->deleteLoginAccount($request->user(), $student);

        return redirect()
            ->route('students.show', $student)
            ->with('success', __('The login account for :name was permanently deleted. Academic history was kept.', ['name' => $name]));
    }

    /**
     * The delete button on the register: the pupil is removed for good, with the
     * enrolments, QR cards, photo and their own login. A student who still owes
     * or has attendance, marks or results is refused, because that history is
     * kept and would have to be destroyed with the row.
     */
    public function destroy(Request $request, Student $student): RedirectResponse
    {
        $this->requirePermission('students.delete');

        $name = $student->full_name;
        $this->students->delete($request->user(), $student);

        return redirect()
            ->route('students.index')
            ->with('success', __(':name was permanently deleted from the database.', ['name' => $name]));
    }

    public function export(Request $request): StreamedResponse
    {
        $this->requirePermission('students.export');

        $filters = $request->only(['search', 'class_id', 'section_id', 'session_id', 'status']);
        $rows = $this->students->filteredQuery($filters, $request->user())->orderBy('first_name')->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                __('Student ID'),
                __('Admission no'),
                __('Name'),
                __('Gender'),
                __('Date of birth'),
                __('Class'),
                __('Section'),
                __('Roll'),
                __('Session'),
                __('Guardian'),
                __('Guardian phone'),
                __('Status'),
                __('Admission date'),
            ]);

            foreach ($rows as $student) {
                $enrollment = $student->enrollments->first();
                fputcsv($handle, [
                    $student->student_id,
                    $student->admission_no,
                    $student->full_name,
                    __(ucfirst($student->gender)),
                    $student->date_of_birth?->format('Y-m-d'),
                    $enrollment?->schoolClass?->name,
                    $enrollment?->section?->name,
                    $enrollment?->roll_no,
                    $enrollment?->academicSession?->name,
                    $student->guardians->first()?->name,
                    $student->guardians->first()?->phone,
                    __(ucfirst($student->status)),
                    $student->admission_date?->format('Y-m-d'),
                ]);
            }

            fclose($handle);
        }, 'students-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function photo(Request $request, Student $student): StreamedResponse
    {
        $this->requirePermission('students.view');
        $this->authorizeStudentAccess($request, $student);

        abort_if($student->photo_path === null || ! str_starts_with($student->photo_path, 'students/'), 404);

        $disk = Storage::disk('school_uploads');
        abort_unless($disk->exists($student->photo_path), 404);

        return $disk->response($student->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storeEnrollment(Request $request, Student $student): RedirectResponse
    {
        $this->requirePermission('students.update');

        $schoolId = app(SchoolContext::class)->id();

        $data = $request->validate([
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'class_id' => [
                'required', 'integer',
                Rule::exists('classes', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'section_id' => [
                'required', 'integer',
                Rule::exists('sections', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'roll_no' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'enrolled_on' => ['nullable', 'date'],
        ]);

        $this->students->createEnrollment($student, $data, $request->user());

        return redirect()
            ->route('students.show', $student)
            ->with('success', __('An enrollment was added for :name.', ['name' => $student->full_name]));
    }

    public function updateEnrollment(Request $request, Student $student, Enrollment $enrollment): RedirectResponse
    {
        $this->requirePermission('students.update');

        $data = $request->validate([
            'roll_no' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'status' => ['required', Rule::in(['active', 'completed', 'transferred', 'withdrawn'])],
        ]);

        $this->students->updateEnrollment($student, $enrollment, $data, $request->user());

        return redirect()
            ->route('students.show', $student)
            ->with('success', __('The enrollment was updated.'));
    }

    private function authorizeStudentAccess(Request $request, Student $student): void
    {
        abort_unless(
            $this->teaching->mayViewStudent($request->user(), $student, $this->teaching->currentSession()),
            403,
            __('This student is not on your timetable.'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::query()
                ->with(['sections' => fn ($query) => $query->orderBy('name')])
                ->orderBy('level')
                ->orderBy('name')
                ->get(),
            'bloodGroups' => ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'],
        ];
    }
}
