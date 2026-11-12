<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Models\ExamMark;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Services\TeachingScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShortcutDashboardController extends Controller
{
    public function __construct(
        private readonly TeachingScope $teaching,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $school = \App\Models\School::first();
        $currentSession = AcademicSession::where('is_current', true)->first();

        $shortcuts = $this->getShortcutsByRole($user, $currentSession);
        $summary = $this->getSummary($user, $currentSession);

        return view('shortcut-dashboard', [
            'school' => $school,
            'user' => $user,
            'session' => $currentSession,
            'shortcuts' => $shortcuts,
            'summary' => $summary,
            'shellSchool' => $school,
            'shellUser' => $user,
            'shellUnreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    private function getShortcutsByRole($user, $currentSession): array
    {
        $shortcuts = [];

        // Common shortcuts for all roles
        $shortcuts[] = [
            'id' => 'profile',
            'label' => __('My Profile'),
            'icon' => 'bi-person',
            'color' => 'primary',
            'route' => route('profile.edit'),
            'category' => __('Personal'),
        ];

        if ($user->hasRole('school_admin') || $user->hasRole('principal')) {
            $shortcuts = array_merge($shortcuts, $this->getAdminShortcuts());
        }

        if ($user->hasRole('teacher') && !$user->hasAnyRole(['school_admin', 'principal'])) {
            $shortcuts = array_merge($shortcuts, $this->getTeacherShortcuts($user, $currentSession));
        }

        if ($user->hasRole('accountant') && !$user->hasAnyRole(['school_admin', 'principal'])) {
            $shortcuts = array_merge($shortcuts, $this->getAccountantShortcuts());
        }

        // A tile that names a permission only appears for an account that holds
        // it, so the shortcut grid can never offer a screen that answers 403.
        return collect($shortcuts)
            ->reject(fn (array $shortcut): bool => isset($shortcut['permission']) && ! $user->can($shortcut['permission']))
            ->values()
            ->all();
    }

    private function getAdminShortcuts(): array
    {
        return [
            // Student Management
            [
                'id' => 'add_student',
                'label' => __('Add Student'),
                'icon' => 'bi-person-plus',
                'color' => 'success',
                'route' => route('students.create'),
                'category' => __('Student Management'),
            ],
            [
                'id' => 'student_list',
                'label' => __('Student List'),
                'icon' => 'bi-people',
                'color' => 'primary',
                'route' => route('students.index'),
                'category' => __('Student Management'),
            ],
            [
                'id' => 'student_attendance',
                'label' => __('Student Attendance'),
                'icon' => 'bi-calendar-check',
                'color' => 'info',
                'route' => route('attendance.students.index'),
                'category' => __('Student Management'),
            ],
            [
                'id' => 'student_promotion',
                'label' => __('Student Promotion'),
                'icon' => 'bi-arrow-up-circle',
                'color' => 'warning',
                'route' => route('students.index'), // Will filter to promotion
                'category' => __('Student Management'),
            ],

            // Teacher Management
            [
                'id' => 'add_teacher',
                'label' => __('Add Teacher'),
                'icon' => 'bi-person-plus-fill',
                'color' => 'success',
                'route' => route('staff.teachers.create'),
                'category' => __('Teacher Management'),
            ],
            [
                'id' => 'teacher_list',
                'label' => __('Teacher List'),
                'icon' => 'bi-people-fill',
                'color' => 'primary',
                'route' => route('staff.index'),
                'category' => __('Teacher Management'),
            ],
            [
                'id' => 'teacher_attendance',
                'label' => __('Teacher Attendance'),
                'icon' => 'bi-calendar-check2',
                'color' => 'info',
                'route' => route('attendance.staff.index'),
                'category' => __('Teacher Management'),
            ],

            // Academic
            [
                'id' => 'class_management',
                'label' => __('Class Management'),
                'icon' => 'bi-grid-3x3',
                'color' => 'primary',
                'route' => route('academic.index'),
                'category' => __('Academic'),
            ],
            [
                'id' => 'section_management',
                'label' => __('Section Management'),
                'icon' => 'bi-diagram-3',
                'color' => 'primary',
                'route' => route('academic.index'),
                'category' => __('Academic'),
            ],
            [
                'id' => 'subject_management',
                'label' => __('Subject Management'),
                'icon' => 'bi-book',
                'color' => 'primary',
                'route' => route('academic.index'),
                'category' => __('Academic'),
            ],
            [
                'id' => 'routine',
                'label' => __('Class Routine'),
                'icon' => 'bi-calendar-week',
                'color' => 'info',
                'route' => route('timetable.index'),
                'category' => __('Academic'),
            ],
            [
                'id' => 'homework',
                'label' => __('Homework'),
                'icon' => 'bi-journal-text',
                'color' => 'warning',
                'route' => route('teacher.homework.index'),
                'permission' => 'homework.view',
                'category' => __('Academic'),
            ],
            [
                'id' => 'online_class',
                'label' => __('Online Class'),
                'icon' => 'bi-camera-video',
                'color' => 'danger',
                'route' => route('videos.index'),
                'category' => __('Academic'),
            ],

            // Examination
            [
                'id' => 'create_exam',
                'label' => __('Create Exam'),
                'icon' => 'bi-file-earmark-plus',
                'color' => 'success',
                'route' => route('exams.create'),
                'category' => __('Examination'),
            ],
            [
                'id' => 'marks_entry',
                'label' => __('Marks Entry'),
                'icon' => 'bi-pencil-square',
                'color' => 'primary',
                'route' => route('dashboard.results'),
                'category' => __('Examination'),
            ],
            [
                'id' => 'result_entry',
                'label' => __('Result Entry'),
                'icon' => 'bi-table',
                'color' => 'info',
                'route' => route('exams.results.index', ['exam' => 1]),
                'category' => __('Examination'),
            ],
            [
                'id' => 'publish_result',
                'label' => __('Publish Result'),
                'icon' => 'bi-send',
                'color' => 'success',
                'route' => route('exams.results.index', ['exam' => 1]),
                'category' => __('Examination'),
            ],

            // Accounts
            [
                'id' => 'fee_collection',
                'label' => __('Fee Collection'),
                'icon' => 'bi-cash-coin',
                'color' => 'success',
                'route' => route('fees.collect'),
                'category' => __('Accounts'),
            ],
            [
                'id' => 'student_due',
                'label' => __('Student Due'),
                'icon' => 'bi-exclamation-circle',
                'color' => 'danger',
                'route' => route('fees.dues'),
                'category' => __('Accounts'),
            ],
            [
                'id' => 'expense',
                'label' => __('Expense'),
                'icon' => 'bi-cart-x',
                'color' => 'warning',
                'route' => route('expenses.index'),
                'category' => __('Accounts'),
            ],
            [
                'id' => 'accounts_report',
                'label' => __('Accounts Report'),
                'icon' => 'bi-file-earmark-bar-graph',
                'color' => 'info',
                'route' => route('reports.index'),
                'category' => __('Accounts'),
            ],

            // Communication
            [
                'id' => 'notice_board',
                'label' => __('Notice Board'),
                'icon' => 'bi-megaphone',
                'color' => 'primary',
                'route' => route('notices.index'),
                'category' => __('Communication'),
            ],

            // Other
            [
                'id' => 'reports',
                'label' => __('Reports'),
                'icon' => 'bi-file-earmark-text',
                'color' => 'info',
                'route' => route('reports.index'),
                'category' => __('Other'),
            ],
            [
                'id' => 'settings',
                'label' => __('Settings'),
                'icon' => 'bi-gear',
                'color' => 'secondary',
                'route' => route('schools.show'),
                'category' => __('Other'),
            ],
            [
                'id' => 'user_management',
                'label' => __('User Management'),
                'icon' => 'bi-people',
                'color' => 'primary',
                'route' => route('users.index'),
                'category' => __('Other'),
            ],
        ];
    }

    private function getTeacherShortcuts($user, $currentSession): array
    {
        $shortcuts = [
            [
                'id' => 'student_attendance',
                'label' => __('Student Attendance'),
                'icon' => 'bi-calendar-check',
                'color' => 'info',
                'route' => route('attendance.students.index'),
                'category' => __('Daily Tasks'),
            ],
            [
                'id' => 'homework',
                'label' => __('Homework'),
                'icon' => 'bi-journal-text',
                'color' => 'warning',
                'route' => route('teacher.homework.index'),
                'category' => __('Academic'),
            ],
            [
                'id' => 'materials',
                'label' => __('Study materials'),
                'icon' => 'bi-journal-bookmark',
                'color' => 'success',
                'route' => route('materials.index'),
                'category' => __('Academic'),
            ],
            [
                'id' => 'marks_entry',
                'label' => __('Marks Entry'),
                'icon' => 'bi-pencil-square',
                'color' => 'primary',
                'route' => route('dashboard.results'),
                'category' => __('Examination'),
            ],
            [
                'id' => 'class_routine',
                'label' => __('My Routine'),
                'icon' => 'bi-calendar-week',
                'color' => 'info',
                'route' => route('teacher.routine'),
                'category' => __('Academic'),
            ],
            [
                'id' => 'my_classes',
                'label' => __('My Classes'),
                'icon' => 'bi-diagram-3',
                'color' => 'primary',
                'route' => route('teacher.classes'),
                'category' => __('Academic'),
            ],
        ];

        return $shortcuts;
    }

    private function getAccountantShortcuts(): array
    {
        return [
            [
                'id' => 'fee_collection',
                'label' => __('Fee Collection'),
                'icon' => 'bi-cash-coin',
                'color' => 'success',
                'route' => route('fees.collect'),
                'category' => __('Accounts'),
            ],
            [
                'id' => 'student_due',
                'label' => __('Student Due'),
                'icon' => 'bi-exclamation-circle',
                'color' => 'danger',
                'route' => route('fees.dues'),
                'category' => __('Accounts'),
            ],
            [
                'id' => 'fee_invoices',
                'label' => __('Fee Invoices'),
                'icon' => 'bi-file-earmark-text',
                'color' => 'primary',
                'route' => route('fees.index'),
                'category' => __('Accounts'),
            ],
            [
                'id' => 'expense',
                'label' => __('Expense'),
                'icon' => 'bi-cart-x',
                'color' => 'warning',
                'route' => route('expenses.index'),
                'category' => __('Accounts'),
            ],
            [
                'id' => 'cashbook',
                'label' => __('Cashbook'),
                'icon' => 'bi-wallet',
                'color' => 'info',
                'route' => route('cashbook.index'),
                'category' => __('Accounts'),
            ],
            [
                'id' => 'accounts_report',
                'label' => __('Accounts Report'),
                'icon' => 'bi-file-earmark-bar-graph',
                'color' => 'success',
                'route' => route('reports.index'),
                'category' => __('Accounts'),
            ],
        ];
    }

    private function getSummary($user, $currentSession): array
    {
        $summary = [
            'total_students' => 0,
            'total_teachers' => 0,
            'total_staff' => 0,
            'today_attendance' => 0,
            'today_collection' => 0,
            'pending_fees' => 0,
        ];

        // Get real data from database
        $summary['total_students'] = Student::where('status', 'active')->count();
        $summary['total_teachers'] = Teacher::count();
        $summary['total_staff'] = Teacher::count(); // Simplified - could add employees

        // Today's attendance
        $today = now()->toDateString();
        $summary['today_attendance'] = StudentAttendance::where('attendance_date', $today)
            ->where('status', 'present')
            ->count();

        // Today's collection
        $summary['today_collection'] = FeePayment::whereDate('created_at', $today)
            ->sum('amount');

        // Pending fees
        $summary['pending_fees'] = (float) FeeInvoice::query()
            ->whereIn('status', FeeInvoice::OPEN_STATUSES)
            ->sum('due_total');

        return $summary;
    }
}
