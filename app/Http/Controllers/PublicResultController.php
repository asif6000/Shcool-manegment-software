<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsPublicSchool;
use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\StudentResult;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicResultController extends Controller
{
    use FindsPublicSchool;

    public function index(Request $request): View
    {
        $school = $this->publicSchool();

        $data = $this->inPublicSchool($school, function () use ($request): array {
            $exams = Exam::query()
                ->where('status', 'published')
                ->orderByDesc('published_at')
                ->orderByDesc('end_date')
                ->limit(20)
                ->get();

            $sessions = AcademicSession::query()
                ->orderByDesc('start_date')
                ->limit(20)
                ->get();

            $classes = SchoolClass::query()
                ->where('is_active', true)
                ->orderBy('level')
                ->get();

            // Load sections only when a class is already selected (for cascading)
            $selectedClassId = (int) $request->query('class_id', 0);
            $sections = $selectedClassId > 0
                ? Section::query()
                    ->where('class_id', $selectedClassId)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                : collect();

            $lookup = null;

            if ($request->hasAny(['exam_id', 'session_id', 'roll_no'])) {
                $input = $request->validate([
                    'exam_id'    => ['required', 'integer'],
                    'session_id' => ['required', 'integer'],
                    'class_id'   => ['nullable', 'integer'],
                    'section_id' => ['nullable', 'integer'],
                    'roll_no'    => ['required', 'string', 'max:20'],
                ]);

                $lookup = $this->lookup($input);
            }

            return [
                'exams'           => $exams,
                'sessions'        => $sessions,
                'classes'         => $classes,
                'sections'        => $sections,
                'selectedClassId' => $selectedClassId,
                'lookup'          => $lookup,
            ];
        });

        return view('public.results.index', [
            'school'          => $school,
            'exams'           => $data['exams'],
            'sessions'        => $data['sessions'],
            'classes'         => $data['classes'],
            'sections'        => $data['sections'],
            'selectedClassId' => $data['selectedClassId'],
            'lookup'          => $data['lookup'],
            'activeNav'       => 'results',
        ]);
    }

    /**
     * JSON: return active sections for a given class_id.
     * Used by the result search form cascade dropdown.
     */
    public function sections(Request $request): \Illuminate\Http\JsonResponse
    {
        $classId = (int) $request->query('class_id', 0);

        if ($classId === 0) {
            return response()->json([]);
        }

        $school = $this->publicSchool();

        $rows = $this->inPublicSchool($school, fn (): array => Section::query()
            ->where('class_id', $classId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray()
        );

        return response()->json($rows);
    }

    /**
     * @param  array{exam_id: int, session_id: int, class_id?: int|null, section_id?: int|null, roll_no: string}  $input
     * @return array{exam: Exam, result: StudentResult, enrollment: Enrollment}
     */
    private function lookup(array $input): array
    {
        $exam = Exam::query()
            ->whereKey((int) $input['exam_id'])
            ->where('status', 'published')
            ->first();

        if ($exam === null) {
            throw ValidationException::withMessages([
                'exam_id' => __('নির্বাচিত পরীক্ষার ফলাফল এখনো প্রকাশিত হয়নি।'),
            ]);
        }

        $session = AcademicSession::query()->whereKey((int) $input['session_id'])->first();

        if ($session === null) {
            throw ValidationException::withMessages([
                'session_id' => __('সঠিক শিক্ষাবর্ষ নির্বাচন করুন।'),
            ]);
        }

        $enrollmentQuery = Enrollment::query()
            ->where('academic_session_id', $session->id)
            ->where('roll_no', $input['roll_no'])
            ->with(['schoolClass', 'section', 'student']);

        // Optional class/section filter — narrows the search when selected
        if (!empty($input['class_id'])) {
            $enrollmentQuery->where('class_id', (int) $input['class_id']);
        }
        if (!empty($input['section_id'])) {
            $enrollmentQuery->where('section_id', (int) $input['section_id']);
        }

        $enrollment = $enrollmentQuery->first();

        if ($enrollment === null) {
            throw ValidationException::withMessages([
                'roll_no' => __('এই রোল নম্বরে কোনো ভর্তিকৃত শিক্ষার্থী পাওয়া যায়নি।'),
            ]);
        }

        $result = StudentResult::query()
            ->where('exam_id', $exam->id)
            ->where('student_id', $enrollment->student_id)
            ->where('status', 'published')
            ->first();

        if ($result === null) {
            throw ValidationException::withMessages([
                'roll_no' => __('এই পরীক্ষার ফলাফল এখনো প্রকাশিত হয়নি।'),
            ]);
        }

        return ['exam' => $exam, 'result' => $result, 'enrollment' => $enrollment];
    }
}
