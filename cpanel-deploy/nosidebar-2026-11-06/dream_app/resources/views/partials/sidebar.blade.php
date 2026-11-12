@php
    $school = $shellSchool ?? null;
    $user = $shellUser ?? auth()->user();
    $unread = $shellUnreadCount ?? 0;
    $brandInitials = $school?->initials() ?? 'EN';
    $isActive = fn (string ...$patterns): bool => request()->routeIs(...$patterns);
    $canAny = fn (array $permissions): bool => $user !== null && collect($permissions)->contains(fn (string $permission): bool => $user->can($permission));
    $subjectsTab = $isActive('academic.*') && request()->query('tab') === 'subjects';
    $isTeacherOnly = $user?->hasRole('teacher') && ! $user?->hasAnyRole(['school_admin', 'principal', 'super_admin']);
    $isAccountantOnly = $user?->hasRole('accountant') && ! $user?->hasAnyRole(['school_admin', 'principal', 'super_admin']);
    $isTeachingUser = $isTeacherOnly;
    $canCreateAccountant = $user !== null
        && $user->can('users.create')
        && \App\Support\RoleHierarchy::canAssignRole($user, 'accountant');
    $isMonitoringFees = $user?->hasAnyRole(['school_admin', 'principal']) ?? false;
    $isAccountantDesk = ! $isMonitoringFees
        && $user?->hasRole('accountant')
        && $user->can('fees.collect');

    $navGroups = [];

    $studentItems = [];
    if ($user?->can('students.view')) {
        $studentItems[] = ['label' => __('All students'), 'url' => route('students.index'), 'icon' => 'users-round', 'active' => $isActive('students.*')];
    }
    if ($user?->can('students.create')) {
        $studentItems[] = ['label' => __('Import students'), 'url' => route('student_import.index'), 'icon' => 'upload', 'active' => $isActive('student_import.*')];
    }
    if ($user?->can('admissions.view')) {
        $studentItems[] = ['label' => __('Admissions'), 'url' => route('admissions.index'), 'icon' => 'user-plus', 'active' => $isActive('admissions.*')];
    }
    if ($user?->can('academic.view')) {
        $studentItems[] = ['label' => __('Student promotion'), 'url' => route('promotions.index'), 'icon' => 'arrow-right', 'active' => $isActive('promotions.*')];
    }
    if ($user?->can('leave.view')) {
        $studentItems[] = ['label' => __('Student leave'), 'url' => route('student_leaves.index'), 'icon' => 'calendar-x', 'active' => $isActive('student_leaves.*')];
    }
    if ($studentItems !== []) {
        $navGroups[] = ['label' => __('Student Management'), 'icon' => 'graduation-cap', 'items' => $studentItems];
    }

    $teacherItems = [];
    if ($user?->can('staff.view')) {
        $teacherItems[] = ['label' => __('Teachers'), 'url' => route('staff.index', ['type' => 'teachers']), 'icon' => 'users', 'active' => $isActive('staff.index', 'staff.teachers.*') && request()->query('type') !== 'employees'];
        $teacherItems[] = ['label' => __('Employees'), 'url' => route('staff.index', ['type' => 'employees']), 'icon' => 'briefcase', 'active' => ($isActive('staff.index') && request()->query('type') === 'employees') || $isActive('staff.employees.*')];
    }
    if ($user?->can('leave.view')) {
        $teacherItems[] = ['label' => __('Staff leave'), 'url' => route('staff.leaves.index'), 'icon' => 'calendar-x', 'active' => $isActive('staff.leaves.*')];
    }
    if ($user?->can('salary.manage')) {
        $teacherItems[] = ['label' => __('Salary structures'), 'url' => route('staff.salary.index'), 'icon' => 'wallet', 'active' => $isActive('staff.salary.*')];
    }
    if ($teacherItems !== []) {
        $navGroups[] = ['label' => __('Teacher Management'), 'icon' => 'user-round-check', 'items' => $teacherItems];
    }

    if ($user?->can('students.view')) {
        $navGroups[] = [
            'label' => __('Guardian Management'),
            'icon' => 'user-round',
            'items' => [
                ['label' => __('Guardian directory'), 'url' => route('guardians.index'), 'icon' => 'users-round', 'active' => $isActive('guardians.*')],
            ],
        ];
    }

    $academicItems = [];
    if ($user?->can('academic.view')) {
        $academicItems[] = ['label' => __('Academic setup'), 'url' => route('academic.index'), 'icon' => 'book-open', 'active' => $isActive('academic.*') && ! $subjectsTab];
        $academicItems[] = ['label' => __('Subjects'), 'url' => route('academic.index', ['tab' => 'subjects']), 'icon' => 'book', 'active' => $subjectsTab];
    }
    if ($user?->can('timetable.view')) {
        $academicItems[] = ['label' => __('Class routine'), 'url' => route('timetable.index'), 'icon' => 'calendar-days', 'active' => $isActive('timetable.*')];
    }
    if ($academicItems !== []) {
        $navGroups[] = ['label' => __('Academic Management'), 'icon' => 'book-open', 'items' => $academicItems];
    }

    $attendanceItems = [];
    if ($user?->can('attendance.view')) {
        $attendanceItems[] = ['label' => __('Student attendance'), 'url' => route('attendance.students.index'), 'icon' => 'calendar-check', 'active' => $isActive('attendance.students.*') && ! $isActive('attendance.students.report', 'attendance.students.export')];
        $attendanceItems[] = ['label' => __('Staff attendance'), 'url' => route('attendance.staff.index'), 'icon' => 'users-round', 'active' => $isActive('attendance.staff.*')];
    }
    if ($user?->can('attendance.report')) {
        $attendanceItems[] = ['label' => __('Attendance report'), 'url' => route('attendance.students.report'), 'icon' => 'chart-no-axes-column', 'active' => $isActive('attendance.students.report')];
    }
    if ($canAny(['attendance.submit', 'attendance.approve'])) {
        $attendanceItems[] = ['label' => __('Submissions'), 'url' => route('attendance.submissions.index'), 'icon' => 'clipboard-check', 'active' => $isActive('attendance.submissions.*')];
    }
    if ($canAny(['attendance.mark', 'attendance.qr.manage'])) {
        $attendanceItems[] = ['label' => __('QR attendance'), 'url' => route('attendance.qr.index'), 'icon' => 'check', 'active' => $isActive('attendance.qr.*')];
    }
    if ($canAny(['attendance.view', 'attendance.devices.manage'])) {
        $attendanceItems[] = ['label' => __('Methods & devices'), 'url' => route('attendance.methods.index'), 'icon' => 'settings', 'active' => $isActive('attendance.methods.*')];
    }
    if ($attendanceItems !== []) {
        $navGroups[] = ['label' => __('Attendance'), 'icon' => 'calendar-check', 'items' => $attendanceItems];
    }

    $learningItems = [];
    if ($isTeachingUser && $user?->can('homework.view')) {
        $learningItems[] = ['label' => __('Homework'), 'url' => route('teacher.homework.index'), 'icon' => 'clipboard-list', 'active' => $isActive('teacher.homework.*')];
    }
    if ($user?->can('lessons.view')) {
        $learningItems[] = ['label' => __('Lessons'), 'url' => route('lessons.index'), 'icon' => 'presentation', 'active' => $isActive('lessons.*')];
    }
    if ($isTeachingUser && $user?->can('materials.view')) {
        $learningItems[] = ['label' => __('Study materials'), 'url' => route('materials.index'), 'icon' => 'folder-open', 'active' => $isActive('materials.*')];
    }
    if ($learningItems !== []) {
        $navGroups[] = ['label' => __('Homework & Lessons'), 'icon' => 'book-open-check', 'items' => $learningItems];
    }

    $examItems = [];
    if ($user?->can('exams.view')) {
        $examItems[] = ['label' => __('Exams & results'), 'url' => route('exams.index'), 'icon' => 'clipboard-list', 'active' => $isActive('exams.*') && ! $isActive('exams.grade-scale.*')];
    }
    if ($canAny(['marks.submit', 'marks.approve'])) {
        $examItems[] = ['label' => __('Marks submissions'), 'url' => route('marks.submissions.index'), 'icon' => 'clipboard-check', 'active' => $isActive('marks.submissions.*')];
    }
    if ($user?->can('exams.manage')) {
        $examItems[] = ['label' => __('Grade scale'), 'url' => route('exams.grade-scale.edit'), 'icon' => 'settings', 'active' => $isActive('exams.grade-scale.*')];
    }
    if ($examItems !== []) {
        $navGroups[] = ['label' => __('Examination'), 'icon' => 'clipboard-check', 'items' => $examItems];
    }

    $feeItems = [];
    if ($user?->can('fees.view')) {
        $feeItems[] = ['label' => __('Fees & payments'), 'url' => route('fees.index'), 'icon' => 'receipt', 'active' => $isActive('fees.index')];
        $feeItems[] = ['label' => __('Student fee ledger'), 'url' => route('fees.ledger'), 'icon' => 'book-user', 'active' => $isActive('fees.ledger')];
        $feeItems[] = ['label' => __('Payment receipts'), 'url' => route('fees.receipts'), 'icon' => 'file-text', 'active' => $isActive('fees.receipts')];
        $feeItems[] = ['label' => __('Dues'), 'url' => route('fees.dues'), 'icon' => 'circle-alert', 'active' => $isActive('fees.dues')];
        $feeItems[] = ['label' => __('Class fees'), 'url' => route('fees.structures.index'), 'icon' => 'layers', 'active' => $isActive('fees.structures.*')];
        $feeItems[] = ['label' => __('Fee types'), 'url' => route('fees.types.index'), 'icon' => 'tags', 'active' => $isActive('fees.types.*')];
    }
    if ($isAccountantDesk) {
        $feeItems[] = ['label' => __('Fee counter'), 'url' => route('fees.counter'), 'icon' => 'calculator', 'active' => $isActive('fees.counter')];
    } elseif (! $isMonitoringFees && $user?->can('fees.collect.scoped')) {
        $feeItems[] = ['label' => __('Collect fee'), 'url' => route('fees.collect'), 'icon' => 'circle-dollar-sign', 'active' => $isActive('fees.collect')];
    }
    if ($canAny(['fees.discounts.manage', 'fees.discounts.approve'])) {
        $feeItems[] = ['label' => __('Discounts'), 'url' => route('fees.discounts.index'), 'icon' => 'percent', 'active' => $isActive('fees.discounts.*')];
    }
    if ($user?->can('fees.verify')) {
        $feeItems[] = ['label' => __('Verify payments'), 'url' => route('fees.verifications'), 'icon' => 'shield-check', 'active' => $isActive('fees.verifications')];
    }
    if ($user?->can('cash.view')) {
        $feeItems[] = ['label' => __('Cashbook'), 'url' => route('cashbook.index'), 'icon' => 'book-open-check', 'active' => $isActive('cashbook.*')];
    }
    if ($user?->can('expenses.view')) {
        $feeItems[] = ['label' => __('Expenses'), 'url' => route('expenses.index'), 'icon' => 'receipt', 'active' => $isActive('expenses.*')];
    }
    if ($user?->can('vouchers.view')) {
        $feeItems[] = ['label' => __('Vouchers'), 'url' => route('vouchers.index'), 'icon' => 'ticket', 'active' => $isActive('vouchers.*')];
    }
    if ($user?->can('bank.view')) {
        $feeItems[] = ['label' => __('Bank accounts'), 'url' => route('bank.index'), 'icon' => 'landmark', 'active' => $isActive('bank.*')];
    }
    if ($feeItems !== []) {
        $navGroups[] = ['label' => __('Fees & Accounts'), 'icon' => 'wallet', 'items' => $feeItems];
    }

    $accountantItems = [];
    if ($user?->can('accountants.view')) {
        $accountantItems[] = ['label' => __('Accountant list'), 'url' => route('accountants.index'), 'icon' => 'users-round', 'active' => $isActive('accountants.index', 'accountants.show')];
        $accountantItems[] = ['label' => __('Daily collection'), 'url' => route('accountants.daily'), 'icon' => 'calendar-check', 'active' => $isActive('accountants.daily')];
        $accountantItems[] = ['label' => __('Monthly collection'), 'url' => route('accountants.monthly'), 'icon' => 'calendar-days', 'active' => $isActive('accountants.monthly')];
    }
    if ($canCreateAccountant) {
        $accountantItems[] = ['label' => __('Add accountant'), 'url' => route('accountants.create'), 'icon' => 'user-plus', 'active' => $isActive('accountants.create')];
    }
    if ($accountantItems !== []) {
        $navGroups[] = ['label' => __('Accountant Management'), 'icon' => 'user-round-check', 'items' => $accountantItems];
    }

    $onlineItems = [];
    if ($user?->can('videos.view')) {
        $onlineItems[] = ['label' => __('Class videos'), 'url' => route('videos.index'), 'icon' => 'video', 'active' => $isActive('videos.*')];
    }
    if ($onlineItems !== []) {
        $navGroups[] = ['label' => __('Online Classes'), 'icon' => 'video', 'items' => $onlineItems];
    }

    $communicationItems = [];
    if ($user?->can('notices.view')) {
        $communicationItems[] = ['label' => __('Notices'), 'url' => route('notices.index'), 'icon' => 'megaphone', 'active' => $isActive('notices.*')];
    }
    if ($user?->can('notices.manage')) {
        $communicationItems[] = ['label' => __('Send notification'), 'url' => route('notifications.compose'), 'icon' => 'send', 'active' => $isActive('notifications.compose')];
    }
    $communicationItems[] = ['label' => __('Notifications'), 'url' => route('notifications.index'), 'icon' => 'bell', 'active' => $isActive('notifications.index'), 'badge' => $unread];
    $navGroups[] = ['label' => __('Communication'), 'icon' => 'megaphone', 'items' => $communicationItems];

    $reportItems = [];
    if ($canAny(['fees.view', 'cash.view', 'expenses.view'])) {
        $reportItems[] = ['label' => __('Financial overview'), 'url' => route('reports.index'), 'icon' => 'chart-no-axes-column', 'active' => $isActive('reports.index')];
        $reportItems[] = ['label' => __('Collection report'), 'url' => route('reports.collection'), 'icon' => 'circle-dollar-sign', 'active' => $isActive('reports.collection')];
        $reportItems[] = ['label' => __('Student collection'), 'url' => route('reports.students'), 'icon' => 'graduation-cap', 'active' => $isActive('reports.students')];
        $reportItems[] = ['label' => __('Class collection'), 'url' => route('reports.classes'), 'icon' => 'layers', 'active' => $isActive('reports.classes')];
        $reportItems[] = ['label' => __('Fee type report'), 'url' => route('reports.fee-types'), 'icon' => 'tags', 'active' => $isActive('reports.fee-types')];
        $reportItems[] = ['label' => __('Payment methods'), 'url' => route('reports.methods'), 'icon' => 'wallet', 'active' => $isActive('reports.methods')];
        $reportItems[] = ['label' => __('Collector report'), 'url' => route('reports.collectors'), 'icon' => 'user-round-check', 'active' => $isActive('reports.collectors')];
        $reportItems[] = ['label' => __('Expense report'), 'url' => route('reports.expenses'), 'icon' => 'receipt', 'active' => $isActive('reports.expenses')];
        $reportItems[] = ['label' => __('Account statement'), 'url' => route('reports.statement'), 'icon' => 'file-text', 'active' => $isActive('reports.statement')];
    }
    if ($reportItems !== []) {
        $navGroups[] = ['label' => __('Reports'), 'icon' => 'chart-no-axes-column', 'items' => $reportItems];
    }

    $settingsItems = [];
    if ($user?->can('school.view')) {
        $settingsItems[] = ['label' => __('School profile'), 'url' => route('schools.index'), 'icon' => 'building-2', 'active' => $isActive('schools.*')];
    }
    if ($user?->can('users.view')) {
        $settingsItems[] = ['label' => __('Users'), 'url' => route('users.index'), 'icon' => 'users', 'active' => $isActive('users.*')];
    }
    if ($user?->can('roles.view')) {
        $settingsItems[] = ['label' => __('Roles & permissions'), 'url' => route('roles.index'), 'icon' => 'shield', 'active' => $isActive('roles.*')];
    }
    if ($user?->can('audit.view')) {
        $settingsItems[] = ['label' => __('Audit trail'), 'url' => route('audit.index'), 'icon' => 'history', 'active' => $isActive('audit.*')];
    }
    if ($user?->can('school.update')) {
        $settingsItems[] = ['label' => __('Homepage banner'), 'url' => route('banner.edit'), 'icon' => 'image', 'active' => $isActive('banner.*')];
    }
    if ($settingsItems !== []) {
        $navGroups[] = ['label' => __('Settings'), 'icon' => 'settings', 'items' => $settingsItems];
    }

    foreach ($navGroups as &$group) {
        $group['active'] = collect($group['items'])->contains(fn (array $item): bool => $item['active'] === true);
    }
    unset($group);
@endphp
@if ($isTeacherOnly)
    {{-- STRICT REQUIREMENT: TEACHER MUST NEVER HAVE A SIDEBAR --}}
    @php return; @endphp
@endif
@if ($isAccountantOnly)
    {{-- STRICT REQUIREMENT: THE CASH DESK GETS NO NAVIGATION COLUMN AT ANY WIDTH --}}
    @php return; @endphp
@endif
<aside class="app-sidebar" aria-label="{{ __('Primary navigation') }}">
    <div class="sidebar-head">
        <a href="{{ route('dashboard') }}" class="sidebar-brand" data-sidebar-close>
            <span class="brand-mark">
                @if ($school?->logoUrl())
                    <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}">
                @else
                    {{ $brandInitials }}
                @endif
            </span>
            <span class="brand-copy">
                <span class="brand-name">{{ $school?->short_name ?: ($school?->name ?? config('app.name')) }}</span>
                <span class="brand-meta">{{ __('School workspace') }}</span>
            </span>
        </a>
        <button type="button" class="sidebar-close" data-sidebar-close aria-label="{{ __('Close navigation') }}">
            <x-icon name="x" :size="18" />
        </button>
    </div>

    <div class="sidebar-scroll">
        <nav class="sidebar-group" aria-label="{{ __('Main navigation') }}">
            <ul class="sidebar-nav">
                <li>
                    <a class="nav-link nav-single {{ $isActive('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"
                       title="{{ __('Dashboard') }}" @if ($isActive('dashboard')) aria-current="page" @endif data-sidebar-close>
                        <x-icon name="layout-dashboard" class="nav-icon" />
                        <span class="nav-label">{{ __('Dashboard') }}</span>
                    </a>
                </li>

                @foreach ($navGroups as $group)
                    <li class="nav-group {{ $group['active'] ? 'is-active is-open' : '' }}" data-nav-group>
                        <button class="nav-link nav-parent" type="button" data-nav-toggle
                                aria-expanded="{{ $group['active'] ? 'true' : 'false' }}"
                                aria-controls="nav-group-{{ $loop->index }}" title="{{ $group['label'] }}">
                            <x-icon :name="$group['icon']" class="nav-icon" />
                            <span class="nav-label">{{ $group['label'] }}</span>
                            <x-icon name="chevron-down" class="nav-chevron" :size="15" />
                        </button>
                        <div class="nav-submenu" id="nav-group-{{ $loop->index }}">
                            <ul class="nav-submenu-inner">
                                @foreach ($group['items'] as $item)
                                    <li>
                                        <a class="nav-link nav-sub-link {{ $item['active'] ? 'active' : '' }}"
                                           href="{{ $item['url'] }}" title="{{ $item['label'] }}"
                                           @if ($item['active']) aria-current="page" @endif data-sidebar-close>
                                            <x-icon :name="$item['icon']" class="nav-icon" :size="17" />
                                            <span class="nav-label">{{ $item['label'] }}</span>
                                            @if (($item['badge'] ?? 0) > 0)
                                                <span class="nav-badge">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>

    <div class="sidebar-footer">
        <a class="profile" href="{{ route('profile.edit') }}" title="{{ __('My profile') }}" data-sidebar-close>
            <span class="avatar avatar-sm" aria-hidden="true">
                @if ($user?->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="">
                @else
                    {{ $user?->initials() ?? 'U' }}
                @endif
            </span>
            <span class="profile-copy">
                <span class="profile-name">{{ $user?->name }}</span>
                <span class="profile-role">{{ $user?->primaryRoleLabel() }}</span>
            </span>
            <x-icon name="arrow-right" class="profile-chevron" :size="14" />
        </a>

        <div class="sidebar-footer-actions">
            <a class="sidebar-action" href="{{ route('home') }}" title="{{ __('School website') }}">
                <x-icon name="globe" :size="16" /><span>{{ __('Website') }}</span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="sidebar-action-form">
                @csrf
                <button type="submit" class="sidebar-action sidebar-action-danger" title="{{ __('Sign out') }}">
                    <x-icon name="log-out" :size="16" /><span>{{ __('Sign out') }}</span>
                </button>
            </form>
        </div>

        <div class="sidebar-footer-note">{{ __(':app v1', ['app' => config('pwa.short_name', 'DRMS')]) }}</div>
    </div>
</aside>
