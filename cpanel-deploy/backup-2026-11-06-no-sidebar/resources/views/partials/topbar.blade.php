@use('Illuminate\Support\Str')
@php
    $user = $shellUser ?? auth()->user();
    $unread = $shellUnreadCount ?? 0;
    $locale = app()->getLocale();
    $isTeacherOnly = $user?->hasRole('teacher') && ! $user?->hasAnyRole(['school_admin', 'principal', 'super_admin']);
    $routeName = request()->route()?->getName() ?? 'dashboard';
    $currentLabel = [
        'dashboard' => __('Dashboard'),
        'dashboard.results' => __('Results'),
        'students' => __('Students'),
        'guardians' => __('Guardians'),
        'admissions' => __('Admissions'),
        'promotions' => __('Student promotion'),
        'student_leaves' => __('Student leave'),
        'staff' => __('Teachers & staff'),
        'academic' => __('Academic setup'),
        'timetable' => __('Class routine'),
        'attendance' => __('Attendance'),
        'homework' => __('Homework'),
        'lessons' => __('Lessons'),
        'materials' => __('Study materials'),
        'videos' => __('Class videos'),
        'exams' => __('Exams & results'),
        'marks' => __('Marks submissions'),
        'fees' => __('Fees & payments'),
        'cashbook' => __('Cashbook'),
        'expenses' => __('Expenses'),
        'vouchers' => __('Vouchers'),
        'bank' => __('Bank accounts'),
        'reports' => __('Reports'),
        'accountants' => __('Accountants'),
        'notices' => __('Notices'),
        'notifications' => __('Notifications'),
        'users' => __('Users'),
        'roles' => __('Roles & permissions'),
        'audit' => __('Audit trail'),
        'schools' => __('School profile'),
        'banner' => __('Homepage banner'),
        'profile' => __('My profile'),
        'search' => __('Search'),
    ][Str::before($routeName, '.')] ?? __(Str::headline(Str::before($routeName, '.')));
@endphp
<header class="app-topbar">
    @unless ($isTeacherOnly)
        <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="{{ __('Toggle navigation') }}">
            <x-icon name="menu" :size="21" />
        </button>
    @endunless

    <nav class="topbar-breadcrumb" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
        @if ($routeName !== 'dashboard')
            <x-icon name="arrow-right" :size="13" />
            <span aria-current="page">{{ $currentLabel }}</span>
        @endif
    </nav>

    <form class="topbar-search" method="GET" action="{{ route('search') }}" role="search">
        <x-icon name="search" class="search-icon" :size="17" />
        <label for="global-search" class="visually-hidden">{{ __('Search') }}</label>
        <input id="global-search" type="search" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="{{ __('Search students, staff or records') }}" autocomplete="off">
        <span class="search-hint">Ctrl K</span>
    </form>

    <div class="topbar-actions">
        <button class="icon-button theme-toggle" type="button" data-theme-toggle
                data-theme-dark-label="{{ __('Switch to light mode') }}"
                data-theme-light-label="{{ __('Switch to dark mode') }}"
                aria-label="{{ __('Switch to dark mode') }}" title="{{ __('Switch to dark mode') }}">
            <x-icon name="moon" class="theme-icon theme-icon-light" :size="19" />
            <x-icon name="sun" class="theme-icon theme-icon-dark" :size="19" />
        </button>

        <div class="dropdown">
            <button class="icon-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Language') }}" title="{{ __('Language') }}">
                <x-icon name="globe" :size="19" />
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="px-2 py-1 small-caps">{{ __('Language') }}</li>
                @foreach (config('school.locales') as $code)
                    <li>
                        <form method="POST" action="{{ route('locale.update') }}">
                            @csrf
                            <input type="hidden" name="locale" value="{{ $code }}">
                            <button type="submit" class="dropdown-item {{ $locale === $code ? 'active' : '' }}">
                                <x-icon :name="$locale === $code ? 'check' : 'circle'" :size="16" />
                                {{ $code === 'bn' ? 'বাংলা' : __('English') }}
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>

        @include('partials.notifications-menu', ['unread' => $unread])

        <div class="dropdown">
            <button class="user-menu" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar" aria-hidden="true">
                    @if ($user?->avatarUrl())
                        <img src="{{ $user->avatarUrl() }}" alt="">
                    @else
                        {{ $user?->initials() ?? 'U' }}
                    @endif
                </span>
                <span class="user-menu-copy">
                    <span class="user-menu-name">{{ $user?->name }}</span>
                    <span class="user-menu-role">{{ $user?->primaryRoleLabel() }}</span>
                </span>
                <x-icon name="chevron-down" class="text-muted-2" :size="15" />
            </button>
            <ul class="dropdown-menu dropdown-menu-end" style="min-width:230px;">
                <li class="px-2 py-1">
                    <div class="fw-600" style="font-size:.84rem;">{{ $user?->name }}</div>
                    <div class="text-muted-2" style="font-size:.72rem;">{{ $user?->email }}</div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><x-icon name="user-round" :size="16" />{{ __('My profile') }}</a></li>
                <li><a class="dropdown-item" href="{{ route('profile.password.change') }}"><x-icon name="shield" :size="16" />{{ __('Change password') }}</a></li>
                @can('school.view')
                    <li><a class="dropdown-item" href="{{ route('schools.show') }}"><x-icon name="building-2" :size="16" />{{ __('School profile') }}</a></li>
                @endcan
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger"><x-icon name="log-out" :size="16" />{{ __('Sign out') }}</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
