<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentPortalService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentPortalResultController extends Controller
{
    public function __construct(private readonly StudentPortalService $portal) {}

    public function index(Request $request): View
    {
        $student = $this->student($request);
        $school = \App\Models\School::first();
        $user = $request->user();
        $unread = $user->unreadNotifications()->count();

        return view('student.results.index', [
            'student' => $student,
            ...$this->portal->results($student),
            'shellSchool' => $school,
            'shellUser' => $user,
            'shellUnreadCount' => $unread,
        ]);
    }

    public function show(Request $request, int $exam): View
    {
        $student = $this->student($request);
        $data = $this->portal->result($student, $exam);
        $school = \App\Models\School::first();
        $user = $request->user();
        $unread = $user->unreadNotifications()->count();

        return view('student.results.show', [
            'student' => $student,
            'exam' => $data['result']->exam,
            'result' => $data['result'],
            'snapshot' => $data['snapshot'],
            'shellSchool' => $school,
            'shellUser' => $user,
            'shellUnreadCount' => $unread,
        ]);
    }

    private function student(Request $request): Student
    {
        return $request->user()->student()->firstOrFail();
    }
}
