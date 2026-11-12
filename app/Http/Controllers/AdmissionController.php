<?php

namespace App\Http\Controllers;

use App\Http\Requests\Students\SaveAdmissionRequest;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\AdmissionService;
use App\Support\AuditLogger;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionController extends Controller
{
    public function __construct(private readonly AdmissionService $admissions) {}

    public function index(Request $request): View
    {
        $this->requirePermission('admissions.view');

        $filters = $request->only(['search', 'status', 'session_id']);

        return view('admissions.index', [
            'admissions' => $this->admissions->paginate($filters),
            'filters' => $filters,
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'counts' => [
                'pending' => Admission::query()->where('status', 'pending')->count(),
                'waitlisted' => Admission::query()->where('status', 'waitlisted')->count(),
                'approved' => Admission::query()->where('status', 'approved')->count(),
                'rejected' => Admission::query()->where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->requirePermission('admissions.manage');

        return view('admissions.create', $this->formData());
    }

    public function store(SaveAdmissionRequest $request): RedirectResponse
    {
        $admission = $this->admissions->create($request->user(), $request->validated());

        return redirect()
            ->route('admissions.show', $admission)
            ->with('success', __('Application :no was recorded.', ['no' => $admission->application_no]));
    }

    public function show(Admission $admission): View
    {
        $this->requirePermission('admissions.view');

        $admission->load(['academicSession', 'appliedClass.sections' => fn ($query) => $query->orderBy('name'), 'student', 'reviewer', 'user']);

        return view('admissions.show', [
            'admission' => $admission,
        ]);
    }

    public function photo(Admission $admission): StreamedResponse
    {
        $this->requirePermission('admissions.view');

        abort_unless($admission->hasPhoto(), 404);

        $disk = Storage::disk('school_uploads');
        abort_unless($disk->exists($admission->photo_path), 404);

        return $disk->response($admission->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function edit(Admission $admission): View
    {
        $this->requirePermission('admissions.manage');

        return view('admissions.edit', [
            'admission' => $admission,
            ...$this->formData(),
        ]);
    }

    public function update(SaveAdmissionRequest $request, Admission $admission): RedirectResponse
    {
        $this->admissions->update($request->user(), $admission, $request->validated());

        return redirect()
            ->route('admissions.show', $admission)
            ->with('success', __('Application :no was updated.', ['no' => $admission->application_no]));
    }

    public function approve(Request $request, Admission $admission): RedirectResponse
    {
        $this->requirePermission('admissions.manage');

        $schoolId = app(SchoolContext::class)->id();

        $data = $request->validate([
            'section_id' => [
                'required', 'integer',
                Rule::exists('sections', 'id')
                    ->where('school_id', $schoolId)
                    ->where('class_id', $admission->applied_class_id),
            ],
            'roll_no' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $student = $this->admissions->approve($request->user(), $admission, $data);

        return redirect()
            ->route('students.show', $student)
            ->with('success', __('Application approved. Student :name is enrolled with ID :id.', [
                'name' => $student->full_name,
                'id' => $student->student_id,
            ]));
    }

    public function updateStatus(Request $request, Admission $admission): RedirectResponse
    {
        $this->requirePermission('admissions.manage');

        $schoolId = app(SchoolContext::class)->id();

        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'waitlisted', 'rejected', 'approved'])],
            'reason' => ['nullable', 'string', 'max:500'],
            'section_id' => [
                Rule::requiredIf($request->input('status') === Admission::APPROVED),
                'nullable', 'integer',
                Rule::exists('sections', 'id')
                    ->where('school_id', $schoolId)
                    ->where('class_id', $admission->applied_class_id),
            ],
            'roll_no' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        if ($data['status'] === Admission::APPROVED) {
            $student = $this->admissions->approve($request->user(), $admission, [
                'section_id' => (int) $data['section_id'],
                'roll_no' => $data['roll_no'] ?? null,
            ]);

            return redirect()
                ->route('students.show', $student)
                ->with('success', __('Application approved. Student :name is enrolled with ID :id.', [
                    'name' => $student->full_name,
                    'id' => $student->student_id,
                ]));
        }

        $this->admissions->changeStatus($request->user(), $admission, $data['status'], $data['reason'] ?? null);

        return redirect()
            ->back(302, [], route('admissions.index'))
            ->with('success', __('Application :no is now :status.', [
                'no' => $admission->application_no,
                'status' => __(ucfirst($data['status'])),
            ]));
    }

    /**
     * Add a section to the applied class straight from the approval dialog, so a
     * class that was set up without sections can still take admissions.
     */
    public function storeSection(Request $request, Admission $admission, AuditLogger $audit): RedirectResponse
    {
        $this->requirePermission('academic.manage');

        $schoolId = app(SchoolContext::class)->id();
        $classId = (int) $admission->applied_class_id;

        if ($classId === 0) {
            throw ValidationException::withMessages([
                'name' => __('This application has no applied class.'),
            ]);
        }

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:30',
                Rule::unique('sections', 'name')
                    ->where('school_id', $schoolId)
                    ->where('class_id', $classId)
                    ->whereNull('deleted_at'),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        // The sections table is soft-deleted and name-unique, so a reused name
        // restores the old row instead of failing on the database index.
        $section = Section::withTrashed()
            ->where('class_id', $classId)
            ->where('name', $data['name'])
            ->first();

        if ($section !== null) {
            $section->restore();
            $section->forceFill([
                'capacity' => $data['capacity'] ?? $section->capacity,
                'is_active' => true,
            ])->save();
        } else {
            $section = Section::query()->create([
                'class_id' => $classId,
                'name' => $data['name'],
                'capacity' => $data['capacity'] ?? null,
                'is_active' => true,
            ]);
        }

        $audit->log(
            event: 'academic.section_created',
            auditable: $section,
            description: __('Section :name was created', ['name' => $section->name]),
            newValues: $section->only(['class_id', 'name', 'capacity', 'is_active']),
        );

        return redirect()
            ->back(302, [], route('admissions.index'))
            ->with('success', __('Section :name was added to :class. Choose it to finish the approval.', [
                'name' => $section->name,
                'class' => $admission->appliedClass?->name ?? '',
            ]))
            ->with('reopen_status', $admission->id);
    }

    public function reject(Request $request, Admission $admission): RedirectResponse
    {
        $this->requirePermission('admissions.manage');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->admissions->reject($request->user(), $admission, $data['reason'] ?? null);

        return redirect()
            ->route('admissions.show', $admission)
            ->with('success', __('Application :no was rejected.', ['no' => $admission->application_no]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::query()->orderBy('level')->orderBy('name')->get(['id', 'name']),
            'sections' => Section::query()->orderBy('name')->get(['id', 'class_id', 'name']),
        ];
    }
}
