<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\MarkStudentAttendanceRequest;
use App\Models\AcademicSession;
use App\Models\AttendanceSubmission;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\AttendanceScope;
use App\Services\StudentAttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentAttendanceController extends Controller
{
    public function __construct(
        private readonly StudentAttendanceService $attendance,
        private readonly AttendanceScope $scope,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission('attendance.view');

        ['sessions' => $sessions, 'session' => $session, 'classes' => $classes, 'class' => $class, 'section' => $section] = $this->context($request);
        $date = $this->dateOrToday($request->query('date') ?? $request->old('attendance_date'));

        $data = [
            'sessions' => $sessions,
            'session' => $session,
            'classes' => $classes,
            'class' => $class,
            'section' => $section,
            'date' => $date,
            'holiday' => $this->attendance->holidayOn($date),
            'submission' => $session !== null && $class !== null
                ? AttendanceSubmission::forRegister(
                    (int) $session->id,
                    (int) $class->id,
                    $section !== null ? (int) $section->id : null,
                    $date->toDateString(),
                )
                : null,
            'sheet' => $session !== null && $class !== null
                ? $this->attendance->sheet($request->user(), $session, $class, $section, $date)
                : collect(),
        ];

        return $this->pageView($request, 'attendance.index', 'attendance', $data);
    }

    public function store(MarkStudentAttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $count = $this->attendance->mark($request->user(), $data);

        return $this->saved(
            $request,
            'attendance.students.index',
            [
                'session_id' => $data['academic_session_id'],
                'class_id' => $data['class_id'],
                'section_id' => $data['section_id'] ?? null,
                'date' => $data['attendance_date'],
            ],
            trans_choice(
                'Attendance was saved for :count student.|Attendance was saved for :count students.',
                $count,
                ['count' => $count],
            ),
        );
    }

    public function report(Request $request): View
    {
        $this->requirePermission('attendance.report');

        ['sessions' => $sessions, 'session' => $session, 'classes' => $classes, 'class' => $class, 'section' => $section] = $this->context($request);
        $month = $this->monthOrNow($request->query('month'));

        return view('attendance.report', [
            'sessions' => $sessions,
            'session' => $session,
            'classes' => $classes,
            'class' => $class,
            'section' => $section,
            'month' => $month,
            'report' => $session !== null && $class !== null
                ? $this->attendance->report($request->user(), $session, $class, $section, $month)
                : null,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->requirePermission('attendance.report');

        ['session' => $session, 'class' => $class, 'section' => $section] = $this->context($request);
        $month = $this->monthOrNow($request->query('month'));

        abort_if($session === null || $class === null, 404);

        $report = $this->attendance->report($request->user(), $session, $class, $section, $month);
        $filename = 'attendance-'.$class->id.'-'.$month->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($report, $class, $section): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                __('Student'),
                __('Student ID'),
                __('Class'),
                __('Section'),
                __('Roll'),
                __('Present'),
                __('Absent'),
                __('Late'),
                __('Leave'),
                __('Excused'),
                __('Marked days'),
                __('Attendance %'),
            ]);

            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['enrollment']->student?->full_name,
                    $row['enrollment']->student?->student_id,
                    $class->name,
                    $row['enrollment']->section?->name,
                    $row['enrollment']->roll_no,
                    $row['counts']['present'],
                    $row['counts']['absent'],
                    $row['counts']['late'],
                    $row['counts']['leave'],
                    $row['counts']['excused'],
                    $row['marked'],
                    $row['rate'] !== null ? number_format($row['rate'], 1) : '',
                ]);
            }

            fputcsv($handle, [
                __('Total'),
                '',
                $class->name,
                $section?->name,
                '',
                $report['totals']['present'],
                $report['totals']['absent'],
                $report['totals']['late'],
                $report['totals']['leave'],
                $report['totals']['excused'],
                $report['working_days'],
                $report['average_rate'] !== null ? number_format($report['average_rate'], 1) : '',
            ]);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{
     *     sessions: Collection<int, AcademicSession>,
     *     session: ?AcademicSession,
     *     classes: Collection<int, SchoolClass>,
     *     class: ?SchoolClass,
     *     section: ?Section
     * }
     */
    private function context(Request $request): array
    {
        $sessions = AcademicSession::query()->orderByDesc('start_date')->get();
        $defaultSession = $sessions->firstWhere('is_current', true) ?? $sessions->first();
        $sessionId = $request->filled('session_id')
            ? (int) $request->query('session_id')
            : (int) ($request->old('academic_session_id') ?? $defaultSession?->id);
        $session = $sessions->firstWhere('id', $sessionId);

        $classes = $this->scope->classes(
            $request->user(),
            $session,
            SchoolClass::query()
                ->with(['sections' => fn ($query) => $query->orderBy('name')])
                ->orderBy('level')
                ->orderBy('name')
                ->get(),
        );
        $classId = $request->filled('class_id')
            ? (int) $request->query('class_id')
            : (int) $request->old('class_id');
        $class = $classId > 0 ? $classes->firstWhere('id', $classId) : null;

        $sectionId = $request->filled('section_id')
            ? (int) $request->query('section_id')
            : (int) $request->old('section_id');
        $section = $class !== null && $sectionId > 0
            ? $class->sections->firstWhere('id', $sectionId)
            : null;

        return compact('sessions', 'session', 'classes', 'class', 'section');
    }

    private function monthOrNow(?string $value): Carbon
    {
        return is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1
            ? Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfMonth()
            : now()->startOfMonth();
    }
}
