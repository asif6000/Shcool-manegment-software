<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsPublicSchool;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\StudentService;
use App\Services\TeachingScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Identity cards carry a signed link, so a scanner opens a public proof that the
 * child is enrolled here without ever exposing an address, a guardian list or a
 * record lookup that a caller could walk through by number.
 */
class StudentIdCardController extends Controller
{
    use FindsPublicSchool;

    private const SHEET_LIMIT = 60;

    public function __construct(
        private readonly StudentService $students,
        private readonly TeachingScope $teaching,
    ) {}

    public function card(Request $request, Student $student): View
    {
        $this->requirePermission('students.view');
        $this->authorizeStudentAccess($request, $student);

        return view('students.id-card', [
            'student' => $student->loadMissing(['guardians', 'school']),
            'enrollment' => $student->currentEnrollment(),
            'verifyUrl' => $this->verifyUrl($student),
        ]);
    }

    public function sheet(Request $request): View
    {
        $this->requirePermission('students.view');

        $filters = $request->only(['search', 'class_id', 'section_id', 'session_id', 'status']);

        $query = $this->students->filteredQuery($filters, $request->user())
            ->with(['school', 'guardians'])
            ->orderBy('first_name')
            ->limit(self::SHEET_LIMIT);

        $ids = array_filter(array_map('intval', (array) $request->query('ids', [])));

        if ($ids !== []) {
            $query->whereIn('students.id', $ids);
        }

        $students = $query->get();

        $enrollments = Enrollment::query()
            ->with(['schoolClass', 'section', 'academicSession'])
            ->whereIn('student_id', $students->pluck('id'))
            ->where('status', 'active')
            ->orderByDesc('enrolled_on')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->first());

        $blocked = $students->count() >= self::SHEET_LIMIT
            && $this->students->filteredQuery($filters, $request->user())->count() > self::SHEET_LIMIT;

        return view('students.id-cards', [
            'students' => $students,
            'enrollments' => $enrollments,
            'verifyUrls' => $students->mapWithKeys(fn (Student $student): array => [
                $student->id => $this->verifyUrl($student),
            ]),
            'filters' => $filters,
            'blocked' => $blocked,
            'limit' => self::SHEET_LIMIT,
        ]);
    }

    public function verify(Request $request, Student $student): View
    {
        $school = $this->publicSchool();

        $record = $this->inPublicSchool($school, function () use ($student): ?Student {
            $fresh = Student::query()->whereKey($student->id)->first();

            if ($fresh === null) {
                return null;
            }

            return $fresh->loadMissing(['school', 'guardians']);
        });

        if ($record === null) {
            abort(404);
        }

        return view('public.student-verify', [
            'school' => $school,
            'student' => $record,
            'enrollment' => $record->currentEnrollment(),
            'photoUrl' => $this->photoUrl($record),
        ]);
    }

    /**
     * The card photo on the public check page cannot come from the staff route,
     * which needs a sign-in, so it gets its own signed link beside the signed
     * page itself.
     */
    public function photo(Request $request, Student $student): StreamedResponse
    {
        $school = $this->publicSchool();

        abort_if($school !== null && (int) $student->school_id !== (int) $school->id, 404);
        abort_if($student->photo_path === null || ! str_starts_with($student->photo_path, 'students/'), 404);

        $disk = Storage::disk('school_uploads');

        abort_unless($disk->exists($student->photo_path), 404);

        return $disk->response($student->photo_path, null, [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function photoUrl(Student $student): ?string
    {
        if ($student->photo_path === null) {
            return null;
        }

        $parameters = ['student' => $student->id];

        if ($version = $student->photoVersion()) {
            $parameters['v'] = $version;
        }

        return URL::signedRoute('student.verify.photo', $parameters);
    }

    private function verifyUrl(Student $student): string
    {
        return URL::signedRoute('student.verify', ['student' => $student->id]);
    }

    private function authorizeStudentAccess(Request $request, Student $student): void
    {
        abort_unless(
            $this->teaching->mayViewStudent($request->user(), $student, $this->teaching->currentSession()),
            403,
            __('This student is not on your timetable.'),
        );
    }
}
