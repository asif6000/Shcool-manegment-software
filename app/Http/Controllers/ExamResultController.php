<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Student;
use App\Services\ExamResultService;
use App\Services\TeachingScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamResultController extends Controller
{
    public function __construct(
        private readonly ExamResultService $results,
        private readonly TeachingScope $teaching,
    ) {}

    public function index(Request $request, Exam $exam): View
    {
        $this->requirePermission('results.view');

        $exam->load('academicSession');
        $tabulation = $this->tabulationFor($request, $exam);

        return view('exams.results', [
            'exam' => $exam,
            ...$tabulation,
        ]);
    }

    public function export(Request $request, Exam $exam): StreamedResponse
    {
        $this->requirePermission('results.view');

        $tabulation = $this->tabulationFor($request, $exam);
        $class = $tabulation['class'];
        abort_if($class === null, 404);

        $filename = 'results-'.$exam->id.'-'.$class->id.'.csv';

        return response()->streamDownload(function () use ($tabulation, $class, $exam): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");

            $headers = [
                __('Position'),
                __('Student ID'),
                __('Student'),
                __('Class'),
                __('Section'),
                __('Roll'),
            ];

            foreach ($tabulation['papers'] as $paper) {
                $headers[] = $paper->subject?->name ?? __('Subject');
            }

            $headers = array_merge($headers, [
                __('Total marks'),
                __('GPA'),
                __('Grade'),
                __('Status'),
            ]);

            fputcsv($handle, $headers);

            foreach ($tabulation['rows'] as $row) {
                $line = [
                    $row['position'],
                    $row['student']->student_id,
                    $this->safe($row['student']->full_name),
                    $this->safe($class->name),
                    $this->safe($row['enrollment']->section?->name),
                    $row['enrollment']->roll_no,
                ];

                foreach ($tabulation['papers'] as $paper) {
                    $cell = $row['cells'][$paper->id] ?? null;
                    $line[] = $cell === null || ! $cell['recorded']
                        ? ''
                        : ($cell['absent'] ? __('Absent') : number_format((float) $cell['obtained'], 2, '.', ''));
                }

                $line[] = number_format((float) $row['total_obtained'], 2, '.', '')
                    .' / '.number_format((float) $row['total_full'], 2, '.', '');
                $line[] = $row['gpa'] !== null ? number_format((float) $row['gpa'], 2, '.', '') : '';
                $line[] = $row['grade_letter'] ?? '';
                $line[] = $exam->isPublished() ? __('Published') : __('Draft');

                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function publish(Request $request, Exam $exam): RedirectResponse
    {
        $this->requirePermission('results.publish');

        $count = $this->results->publish($request->user(), $exam);

        return redirect()
            ->route('exams.results.index', $exam)
            ->with('success', trans_choice(
                'Results were published for :count student.|Results were published for :count students.',
                $count,
                ['count' => $count],
            ));
    }

    public function unpublish(Request $request, Exam $exam): RedirectResponse
    {
        $this->requirePermission('results.publish');

        $this->results->unpublish($request->user(), $exam);

        return redirect()
            ->route('exams.results.index', $exam)
            ->with('success', __('Results were unpublished. Marks can be edited again until they are republished.'));
    }

    public function reportCard(Request $request, Exam $exam, int $student): View
    {
        $this->requirePermission('results.view');

        $studentModel = Student::query()->findOrFail($student);

        // A report card carries the whole academic record, so it follows the same
        // rule as the profile: a teacher may open the card of a student they teach.
        abort_unless(
            $this->teaching->mayViewStudent($request->user(), $studentModel, $exam->academicSession),
            403,
            __('This student is not on your timetable.'),
        );

        $data = $this->results->staffReport($exam, $studentModel);

        return view('exams.report-card', [
            'exam' => $exam->load('academicSession'),
            'student' => $studentModel,
            'result' => $data['result'],
            'snapshot' => $data['snapshot'],
            'published' => $data['published'],
            'complete' => $data['complete'],
        ]);
    }

    /**
     * A tabulation limited to what the viewer may read. Office staff keep the
     * whole exam; a teacher is narrowed to the classes on their own timetable.
     *
     * @return array<string, mixed>
     */
    private function tabulationFor(Request $request, Exam $exam): array
    {
        $actor = $request->user();
        $tabulation = $this->results->tabulation($exam, $this->classFor($request, $exam));

        if (! $this->teaching->isUnrestricted($actor)) {
            $tabulation['classes'] = $this->teaching->classes($actor, $exam->academicSession, $tabulation['classes']);
        }

        return $tabulation;
    }

    private function classFor(Request $request, Exam $exam): ?int
    {
        $requested = $request->filled('class_id') ? (int) $request->query('class_id') : null;
        $actor = $request->user();

        if ($this->teaching->isUnrestricted($actor)) {
            return $requested;
        }

        $own = $this->teaching->classes($actor, $exam->academicSession, $this->results->classes($exam))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        if ($requested !== null) {
            abort_unless($own->contains($requested), 403, __('This class is not on your timetable.'));

            return $requested;
        }

        return $own->first();
    }

    private function safe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@]/', $value) === 1 ? "'".$value : $value;
    }
}
