<?php

use App\Models\AcademicSession;
use App\Models\AttendanceSubmission;
use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Models\StudentAttendance;
use App\Models\User;

app(App\Support\SchoolContext::class)->set(1);

$session = AcademicSession::query()->where('is_current', true)->first();
$teacher = User::query()->where('email', 'teacher@edunest.test')->first();
$today = now()->toDateString();
$made = 0;

$pairs = ClassRoutine::query()
    ->where('academic_session_id', $session->id)
    ->where('teacher_id', 1)
    ->get(['class_id', 'section_id'])
    ->unique(fn (ClassRoutine $row) => $row->class_id.':'.$row->section_id);

foreach ($pairs as $pair) {
    $enrolments = Enrollment::query()
        ->where('academic_session_id', $session->id)
        ->where('class_id', $pair->class_id)
        ->where('section_id', $pair->section_id)
        ->where('status', 'active')
        ->limit(6)
        ->get();

    if ($enrolments->isEmpty()) {
        continue;
    }

    $cycle = ['present', 'present', 'absent', 'late', 'present', 'leave'];
    $counts = array_fill_keys(['present', 'absent', 'late', 'leave', 'excused'], 0);

    foreach ($enrolments as $index => $enrollment) {
        $status = $cycle[$index % 6];
        $counts[$status]++;

        StudentAttendance::query()->updateOrCreate(
            ['school_id' => 1, 'student_id' => $enrollment->student_id, 'attendance_date' => $today],
            [
                'enrollment_id' => $enrollment->id,
                'academic_session_id' => $session->id,
                'class_id' => $pair->class_id,
                'section_id' => $pair->section_id,
                'status' => $status,
                'source' => 'manual',
                'marked_by' => $teacher?->id,
            ]
        );
    }

    AttendanceSubmission::query()->updateOrCreate(
        [
            'school_id' => 1,
            'academic_session_id' => $session->id,
            'class_id' => $pair->class_id,
            'section_id' => $pair->section_id,
            'attendance_date' => $today,
        ],
        [
            'status' => $made === 0 ? 'submitted' : 'approved',
            'student_count' => $enrolments->count(),
            'present_count' => $counts['present'],
            'absent_count' => $counts['absent'],
            'late_count' => $counts['late'],
            'leave_count' => $counts['leave'],
            'excused_count' => $counts['excused'],
            'unmarked_count' => 0,
            'submitted_by' => $teacher?->id,
            'submitted_at' => now()->subHours($made + 1),
            'reviewed_by' => $made === 0 ? null : 1,
            'reviewed_at' => $made === 0 ? null : now()->subMinutes(20),
            'review_note' => $made === 0 ? null : 'Matches the register.',
        ]
    );

    $made++;
}

echo 'submissions: '.$made.PHP_EOL;
