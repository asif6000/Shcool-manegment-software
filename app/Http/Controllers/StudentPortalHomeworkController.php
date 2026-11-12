<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPortalStudent;
use App\Http\Requests\Portal\SubmitHomeworkRequest;
use App\Models\AcademicSession;
use App\Models\Homework;
use App\Services\HomeworkService;
use App\Services\StudentPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentPortalHomeworkController extends Controller
{
    use ResolvesPortalStudent;

    private const UPLOAD_PROFILE = 'homework_attachment';

    private const SUBMISSION_PROFILE = 'homework_submission';

    public function __construct(
        private readonly StudentPortalService $portal,
        private readonly HomeworkService $homework,
    ) {}

    public function index(Request $request): View
    {
        $student = $this->student($request);
        $session = $this->session();

        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,submitted'],
        ]);

        $status = (string) ($data['status'] ?? '');
        // An unknown category simply falls back to the unfiltered list.
        $category = in_array($request->query('category'), Homework::CATEGORIES, true)
            ? (string) $request->query('category')
            : null;

        return view('student.homework.index', [
            'student' => $student,
            'session' => $session,
            'filter' => $status,
            'category' => $category,
            ...$this->portal->homework($student, $student->currentEnrollment(), $session?->id, $status, $category),
        ]);
    }

    public function show(Request $request, Homework $homework): View
    {
        $student = $this->student($request);
        $session = $this->session();

        return view('student.homework.show', [
            'student' => $student,
            'session' => $session,
            ...$this->portal->homeworkDetail($student, $student->currentEnrollment(), $session?->id, (int) $homework->id),
        ]);
    }

    public function submit(SubmitHomeworkRequest $request, Homework $homework): RedirectResponse
    {
        $student = $this->student($request);
        $session = $this->session();

        // Re-resolve through the student's own scope: the route model binding
        // alone cannot prove the homework belongs to this class.
        $resolvable = Homework::query()
            ->forEnrollment($student->currentEnrollment(), $session?->id)
            ->whereKey($homework->id)
            ->firstOrFail();

        $this->homework->submit(
            $student,
            $request->user(),
            $resolvable,
            $request->validated(),
            $request->hasFile('attachment') ? $request->file('attachment') : null,
        );

        return redirect()
            ->route('student.homework.show', $resolvable->id)
            ->with('success', __('Your homework has been submitted.'));
    }

    public function attachment(Request $request, Homework $homework): StreamedResponse
    {
        $student = $this->student($request);
        $session = $this->session();

        $resolvable = Homework::query()
            ->forEnrollment($student->currentEnrollment(), $session?->id)
            ->whereKey($homework->id)
            ->firstOrFail();

        return $this->serve((string) $resolvable->attachment_path, self::UPLOAD_PROFILE, $resolvable->title);
    }

    public function submissionFile(Request $request, int $submission): StreamedResponse
    {
        $student = $this->student($request);

        $record = $student->homeworkSubmissions()
            ->whereKey($submission)
            ->firstOrFail();

        return $this->serve((string) $record->attachment_path, self::SUBMISSION_PROFILE, (string) $record->attachment_name);
    }

    private function serve(string $path, string $profile, ?string $label): StreamedResponse
    {
        $prefix = (string) config("school.uploads.{$profile}.path");
        abort_unless(str_starts_with($path, $prefix.'/'), 404);

        $disk = Storage::disk('school_uploads');
        abort_unless($disk->exists($path), 404);

        // The download name is echoed into a response header, so it is reduced to
        // safe characters and the extension is taken from the stored path only.
        $filename = str($label)
            ->ascii()
            ->replace(['"', "'", '\\', '/', "\n", "\r", ';'], '')
            ->squish()
            ->limit(60, '')
            ->trim()
            ->toString();

        $filename = ($filename === '' ? 'file' : $filename).'.'.pathinfo($path, PATHINFO_EXTENSION);

        return $disk->response($path, $filename, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
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
