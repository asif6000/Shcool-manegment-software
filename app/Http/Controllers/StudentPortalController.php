<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPortalStudent;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Models\FeeInvoice;
use App\Models\Student;
use App\Services\ProfilePhotoService;
use App\Services\StudentPortalService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentPortalController extends Controller
{
    use ResolvesPortalStudent;

    private const PHOTO_PROFILE = 'student_photo';

    public function __construct(
        private readonly StudentPortalService $portal,
        private readonly UserService $users,
        private readonly ProfilePhotoService $photos,
    ) {}

    public function dashboard(Request $request): View
    {
        $student = $this->student($request);
        $student->load(['guardians', 'enrollments.academicSession', 'enrollments.schoolClass', 'enrollments.section']);
        $school = \App\Models\School::first();
        $user = $request->user();
        $unread = $user->unreadNotifications()->count();

        $session = \App\Models\AcademicSession::query()
            ->where('is_current', true)
            ->orderByDesc('start_date')
            ->first();

        $enrollment = $student->currentEnrollment();
        $sessionId = $session?->id;

        return view('student.dashboard', [
            'student' => $student,
            ...$this->portal->overview($student),
            'attendanceMonth' => $this->portal->attendanceForMonth($student, now()->startOfMonth()),
            'weekDays' => $this->portal->weekRoutine($enrollment, $sessionId, now()),
            'homeworkData' => $this->portal->homework($student, $enrollment, $sessionId, ''),
            'feesData' => $this->portal->fees($student, ''),
            'resultsData' => $this->portal->results($student),
            'noticesList' => \App\Models\Notice::query()
                ->published()
                ->whereIn('audience', ['all', 'students'])
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->paginate((int) config('school.pagination.per_page', 15))
                ->withQueryString(),
            'lessonsRows' => \App\Models\Lesson::query()
                ->forEnrollment($enrollment, $sessionId)
                ->with(['subject', 'teacher'])
                ->withCount('materials')
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->limit(30)
                ->get(),
            'studyMaterials' => \App\Models\StudyMaterial::query()
                ->forEnrollment($enrollment, $sessionId)
                ->with(['subject', 'creator'])
                ->where('kind', \App\Models\StudyMaterial::MATERIAL)
                ->orderByDesc('work_date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(30)
                ->get(),
            'classworkRows' => \App\Models\StudyMaterial::query()
                ->forEnrollment($enrollment, $sessionId)
                ->with(['subject', 'creator'])
                ->where('kind', \App\Models\StudyMaterial::CLASSWORK)
                ->orderByDesc('work_date')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(30)
                ->get(),
            'onlineClasses' => \App\Models\ClassVideo::query()
                ->where('class_id', $enrollment?->class_id)
                ->where('section_id', $enrollment?->section_id)
                ->when($sessionId !== null, fn ($query) => $query->where('academic_session_id', $sessionId))
                ->with('subject')
                ->orderByDesc('id')
                ->limit(30)
                ->get(),
            'notificationsList' => $user->notifications()->latest()->limit(10)->get(),
            'shellSchool' => $school,
            'shellUser' => $user,
            'shellUnreadCount' => $unread,
            'photoMaxKb' => $this->photos->maxKb(self::PHOTO_PROFILE),
            'photoAccept' => $this->photos->acceptedFormats(self::PHOTO_PROFILE),
        ]);
    }

    public function profile(Request $request): View
    {
        $student = $this->student($request);
        $student->load([
            'guardians',
            'enrollments' => fn ($query) => $query
                ->with(['academicSession', 'schoolClass', 'section'])
                ->orderByDesc('enrolled_on'),
        ]);
        $school = \App\Models\School::first();
        $user = $request->user();
        $unread = $user->unreadNotifications()->count();

        return view('student.profile', [
            'student' => $student,
            'shellSchool' => $school,
            'shellUser' => $user,
            'shellUnreadCount' => $unread,
            'photoMaxKb' => $this->photos->maxKb(self::PHOTO_PROFILE),
            'photoAccept' => $this->photos->acceptedFormats(self::PHOTO_PROFILE),
        ]);
    }

    public function attendance(Request $request): View
    {
        $student = $this->student($request);

        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = isset($data['month'])
            ? Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth()
            : now()->startOfMonth();

        $school = \App\Models\School::first();
        $user = $request->user();
        $unread = $user->unreadNotifications()->count();

        return view('student.attendance', [
            'student' => $student,
            ...$this->portal->attendanceForMonth($student, $month),
            'shellSchool' => $school,
            'shellUser' => $user,
            'shellUnreadCount' => $unread,
        ]);
    }

    public function fees(Request $request): View
    {
        $student = $this->student($request);

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(FeeInvoice::STATUSES)],
        ]);

        $school = \App\Models\School::first();
        $user = $request->user();
        $unread = $user->unreadNotifications()->count();

        return view('student.fees.index', [
            'student' => $student,
            ...$this->portal->fees($student, (string) ($data['status'] ?? '')),
            'shellSchool' => $school,
            'shellUser' => $user,
            'shellUnreadCount' => $unread,
        ]);
    }

    public function invoice(Request $request, int $invoice): View
    {
        $student = $this->student($request);
        $school = \App\Models\School::first();
        $user = $request->user();
        $unread = $user->unreadNotifications()->count();

        return view('student.fees.show', [
            'student' => $student,
            'invoice' => $this->portal->invoice($student, $invoice),
            'shellSchool' => $school,
            'shellUser' => $user,
            'shellUnreadCount' => $unread,
        ]);
    }

    public function receipt(Request $request, int $payment): View
    {
        $student = $this->student($request);
        $payment = $this->portal->payment($student, $payment);
        $school = \App\Models\School::first();
        $user = $request->user();
        $unread = $user->unreadNotifications()->count();

        return view('student.fees.receipt', [
            'student' => $student,
            'payment' => $payment,
            'receipt' => $payment->receipt,
            'shellSchool' => $school,
            'shellUser' => $user,
            'shellUnreadCount' => $unread,
        ]);
    }

    public function password(Request $request): View
    {
        $school = \App\Models\School::first();
        $user = $request->user();
        $unread = $user->unreadNotifications()->count();

        return view('student.password', [
            'student' => $this->student($request),
            'shellSchool' => $school,
            'shellUser' => $user,
            'shellUnreadCount' => $unread,
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->users->updatePassword($request->user(), $request->string('password')->toString());

        return redirect()
            ->route('student.dashboard')
            ->with('success', __('Your password has been changed. Other devices have been signed out.'));
    }

    public function photo(Request $request): StreamedResponse
    {
        $student = $this->student($request);

        abort_if($student->photo_path === null || ! str_starts_with($student->photo_path, 'students/'), 404);

        $disk = Storage::disk('school_uploads');
        abort_unless($disk->exists($student->photo_path), 404);

        return $disk->response($student->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The student's own picture, replaced from the portal. The record comes from
     * the signed-in account, so a tampered URL or field can only ever reach the
     * caller's own row.
     */
    public function storePhoto(Request $request): RedirectResponse|JsonResponse
    {
        $student = $this->student($request);

        $request->validate($this->photos->rules(self::PHOTO_PROFILE), $this->photos->messages(self::PHOTO_PROFILE));

        $this->photos->replace($request->user(), $student, $request->file('image'), self::PHOTO_PROFILE);

        if ($request->expectsJson()) {
            return $this->photoResponse($student, __('Your profile photo has been updated.'));
        }

        return back()->with('success', __('Your profile photo has been updated.'));
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $student = $this->student($request);

        $this->photos->remove($request->user(), $student, self::PHOTO_PROFILE);

        return back()->with('success', __('Your profile photo has been removed.'));
    }

    private function photoResponse(Student $student, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'photo' => [
                'url' => route('student.photo', ['v' => $student->photoVersion()]),
                'version' => $student->photoVersion(),
            ],
        ]);
    }
}
