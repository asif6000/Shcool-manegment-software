<?php

namespace App\Http\Controllers;

use App\Services\AccountantCollectionService;
use App\Services\AccountantDashboardService;
use App\Services\DashboardService;
use App\Services\TeacherDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * The module screens a teacher may open inside the dashboard, with the
     * module paths each one is allowed to host. Anything else in the query
     * string falls back to no panel, so a hand-typed value can only select one
     * of these.
     *
     * @var array<string, list<string>>
     */
    private const TEACHER_PANELS = [
        'attendance' => ['/admin/attendance'],
        'lessons' => ['/admin/lessons'],
        'homework' => ['/admin/homework'],
        'classwork' => ['/admin/materials'],
        'results' => ['/admin/exams', '/admin/dashboard/results'],
    ];

    public function __invoke(
        Request $request,
        DashboardService $dashboard,
        AccountantDashboardService $accountantDashboard,
        AccountantCollectionService $accountantCollections,
        TeacherDashboardService $teacherDashboard,
    ): View {
        $user = $request->user();

        abort_unless($user->can('dashboard.view'), 403);

        // The accounts desk needs an operational view of the money, not the
        // school-wide academic summary. Admins and principals keep the full
        // dashboard even if they also hold the accountant role, but can preview
        // it with ?view=accountant when permitted.
        if (($user->hasRole('accountant') && ! $user->hasAnyRole(['school_admin', 'principal'])) || ($request->query('view') === 'accountant' && $user->can('accountants.view'))) {
            return view('dashboard.accountant', $accountantDashboard->overview($user));
        }

        // A teacher's dashboard is limited to the classes on their own
        // timetable; the school-wide figures are not theirs to act on.
        if ($user->hasRole('teacher') && ! $user->hasAnyRole(['school_admin', 'principal', 'accountant'])) {
            $school = \App\Models\School::first();
            $unread = $user->unreadNotifications()->count();

            return view('dashboard.teacher', array_merge($teacherDashboard->overview($user), [
                'activePanel' => $this->teacherPanel($request),
                'reopenUrl' => $this->reopenUrl($request),
                'shellSchool' => $school,
                'shellUser' => $user,
                'shellUnreadCount' => $unread,
            ]));
        }

        return view('dashboard', $dashboard->overview($user) + [
            'collection' => $user->can('accountants.view')
                ? $accountantCollections->collectionSummary()
                : null,
        ]);
    }

    private function teacherPanel(Request $request): string
    {
        $panel = (string) $request->query('panel');

        return array_key_exists($panel, self::TEACHER_PANELS) ? $panel : '';
    }

    /**
     * Where to send the panel after a saved write. Only a path under the panel's
     * own module is accepted, and only as a site-relative URL, so the value can
     * neither leave this app nor ask for a screen the dashboard does not host.
     */
    private function reopenUrl(Request $request): string
    {
        $panel = $this->teacherPanel($request);

        if ($panel === '') {
            return '';
        }

        $value = trim((string) $request->query('reopen'));

        if ($value === '') {
            return '';
        }

        $path = parse_url($value, PHP_URL_PATH);
        $query = parse_url($value, PHP_URL_QUERY);

        if (! is_string($path)) {
            return '';
        }

        $allowed = collect(self::TEACHER_PANELS[$panel])
            ->contains(fn (string $prefix): bool => $path === $prefix || str_starts_with($path, $prefix.'/'));

        return $allowed ? $path.($query ? '?'.$query : '') : '';
    }

    /**
     * The results panel of the teacher dashboard. Marks entry itself stays on
     * the exam route, which repeats the same permission and scope checks.
     */
    public function results(Request $request, TeacherDashboardService $teacherDashboard): View
    {
        $this->requirePermission('marks.enter');

        return view('teacher.panels.results-picker', [
            'marks' => $teacherDashboard->results($request->user()),
            'panel' => true,
        ]);
    }
}
