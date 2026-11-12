<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\MarkStaffAttendanceRequest;
use App\Models\User;
use App\Services\StaffAttendanceService;
use App\Services\TeachingScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffAttendanceController extends Controller
{
    public function __construct(
        private readonly StaffAttendanceService $attendance,
        private readonly TeachingScope $teaching,
    ) {}

    public function index(Request $request): View
    {
        $this->requirePermission('attendance.view');

        $date = $this->dateOrToday($request->query('date') ?? $request->old('attendance_date'));
        [$ownTeachers, $withEmployees] = $this->desk($request->user());

        return view('attendance.staff', [
            'date' => $date,
            'holiday' => $this->attendance->holidayOn($date),
            'sheet' => $this->attendance->sheet($date, $ownTeachers, $withEmployees),
            'summary' => $this->attendance->monthlySummary($date, $ownTeachers, $withEmployees),
        ]);
    }

    public function store(MarkStaffAttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        [$ownTeachers, $withEmployees] = $this->desk($request->user());

        $count = $this->attendance->mark($request->user(), $data, $ownTeachers, $withEmployees);

        return redirect()
            ->route('attendance.staff.index', ['date' => $data['attendance_date']])
            ->with('success', trans_choice(
                'Attendance was saved for :count staff member.|Attendance was saved for :count staff members.',
                $count,
                ['count' => $count],
            ));
    }

    /**
     * The office desk holds the whole register. A teacher is given their own row
     * only, and never the non-teaching staff list.
     *
     * @return array{0: array<int, int>|null, 1: bool}
     */
    private function desk(User $actor): array
    {
        if ($this->teaching->isUnrestricted($actor)) {
            return [null, true];
        }

        $teacherId = $this->teaching->teacherId($actor);

        return [$teacherId === null ? [] : [$teacherId], false];
    }
}
