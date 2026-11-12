<?php

namespace App\Http\Controllers;

use App\Http\Requests\Applicant\StoreAdmissionApplicationRequest;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\SchoolClass;
use App\Services\AdmissionService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The applicant workspace. Every screen and endpoint here requires a signed-in
 * student account, so an admission application can never be posted anonymously.
 */
class ApplicantAdmissionController extends Controller
{
    public function __construct(
        private readonly AdmissionService $admissions,
        private readonly UserService $users,
    ) {}

    public function dashboard(Request $request): View
    {
        $applicant = $request->user();

        return view('applicant.dashboard', [
            'admissions' => $this->admissions->forApplicant($applicant),
            'canApply' => ! $applicant->hasStudentRecord(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $applicant = $request->user();

        if ($applicant->hasStudentRecord()) {
            return redirect()
                ->route('student.dashboard')
                ->with('info', __('Your admission is already complete. The student portal holds your records.'));
        }

        return view('applicant.apply', [
            'sessions' => AcademicSession::query()
                ->where('status', 'active')
                ->orderByDesc('start_date')
                ->get(),
            'classes' => SchoolClass::query()
                ->where('is_active', true)
                ->orderBy('level')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreAdmissionApplicationRequest $request): RedirectResponse
    {
        $admission = $this->admissions->submitForApplicant(
            $request->user(),
            $request->validated(),
            $request->file('photo'),
        );

        return redirect()
            ->route('applicant.show', $admission)
            ->with('success', __('Application :no was submitted and is now pending review.', [
                'no' => $admission->application_no,
            ]));
    }

    public function show(Request $request, Admission $admission): View
    {
        $this->authorizeOwnership($request, $admission);

        $admission->load(['academicSession', 'appliedClass', 'student']);

        return view('applicant.show', [
            'admission' => $admission,
        ]);
    }

    public function photo(Request $request, Admission $admission): StreamedResponse
    {
        $this->authorizeOwnership($request, $admission);

        abort_unless($admission->hasPhoto(), 404);

        $disk = Storage::disk('school_uploads');
        abort_unless($disk->exists($admission->photo_path), 404);

        return $disk->response($admission->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function password(): View
    {
        return view('applicant.password');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->users->updatePassword($request->user(), $request->string('password')->toString());

        return redirect()
            ->route('applicant.dashboard')
            ->with('success', __('Your password has been changed. Other devices have been signed out.'));
    }

    private function authorizeOwnership(Request $request, Admission $admission): void
    {
        // 404 rather than 403: another applicant's reference number must not be
        // confirmed to exist.
        abort_unless((int) $admission->user_id === (int) $request->user()->id, 404);
    }
}
