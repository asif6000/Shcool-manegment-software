@php
    $school = $school ?? $shellSchool ?? null;
    $user = $shellUser ?? auth()->user();
    $unread = $shellUnreadCount ?? 0;
    $brandInitials = $school?->initials() ?? 'EN';
    $currentPanel = request()->query('panel', '');
    // The teacher's own staff record: the header avatar and the profile link
    // follow it, because that is the photo the teacher changes in this portal.
    $portalTeacher = $user?->teacher;
    $teacherProfileRoute = $portalTeacher ? route('teacher.profile') : route('profile.edit');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('Teacher Dashboard')) · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=12">
    <link rel="stylesheet" href="{{ asset('assets/css/bengali.css') }}?v=1">
    @include('partials.pwa-head')
    @stack('head')
    @stack('styles')
</head>
<body class="teacher-shell @yield('body-class')">
    <a class="visually-hidden-focusable position-absolute top-0 start-0 bg-white p-2" href="#main-content">{{ __('Skip to content') }}</a>

    <header class="teacher-header" id="teacher-header">
        <div class="container-fluid px-3 px-xl-4">
            <div class="teacher-header-main d-flex align-items-center justify-content-between gap-3">
                <!-- 1. School brand / logo -->
                <div class="teacher-brand-wrap d-flex align-items-center">
                    <a href="{{ route('dashboard') }}" class="teacher-logo" data-td-open="dashboard" title="{{ config('app.name') }}">
                        @if($school && $school->logo_path)
                            <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}">
                        @else
                            <span class="logo-initials">{{ $brandInitials }}</span>
                        @endif
                        <span class="logo-text">{{ $school?->short_name ?: ($school?->name ?? config('pwa.short_name', 'DRMS')) }}</span>
                    </a>
                </div>

                <!-- 2. Desktop Top Navigation Bar (Dashboard, Attendance, Homework & Classwork, Materials, Lesson, Result Management) -->
                <nav class="teacher-nav d-none d-lg-flex align-items-center" aria-label="{{ __('Teacher navigation') }}">
                    <ul class="teacher-nav-list d-flex align-items-center m-0 p-0 list-unstyled gap-1">
                        <li>
                            <a href="{{ route('dashboard') }}"
                               class="teacher-nav-link {{ $currentPanel === '' && request()->routeIs('dashboard') ? 'is-active' : '' }}"
                               data-td-open="dashboard"
                               title="{{ __('Dashboard') }}">
                                <i class="bi bi-grid-1x2"></i>
                                <span>{{ __('Dashboard') }}</span>
                            </a>
                        </li>
                        @if(!auth()->user() || auth()->user()->can('attendance.view') || auth()->user()->can('attendance.mark'))
                        <li>
                            <a href="{{ route('dashboard', ['panel' => 'attendance']) }}"
                               class="teacher-nav-link {{ $currentPanel === 'attendance' ? 'is-active' : '' }}"
                               data-td-open="attendance"
                               title="{{ __('Attendance') }}">
                                <i class="bi bi-check2-square"></i>
                                <span>{{ __('Attendance') }}</span>
                            </a>
                        </li>
                        @endif
                        @if(!auth()->user() || auth()->user()->can('homework.view'))
                        <li>
                            <a href="{{ route('dashboard', ['panel' => 'homework']) }}"
                               class="teacher-nav-link {{ $currentPanel === 'homework' ? 'is-active' : '' }}"
                               data-td-open="homework"
                               title="{{ __('Homework & Classwork') }}">
                                <i class="bi bi-journal-text"></i>
                                <span>{{ __('Homework & Classwork') }}</span>
                            </a>
                        </li>
                        @endif
                        @if(!auth()->user() || auth()->user()->can('materials.view'))
                        <li>
                            <a href="{{ route('dashboard', ['panel' => 'materials']) }}"
                               class="teacher-nav-link {{ $currentPanel === 'materials' ? 'is-active' : '' }}"
                               data-td-open="materials"
                               title="{{ __('Study materials') }}">
                                <i class="bi bi-journal-bookmark"></i>
                                <span>{{ __('Materials') }}</span>
                            </a>
                        </li>
                        @endif
                        @if(!auth()->user() || auth()->user()->can('lessons.view'))
                        <li>
                            <a href="{{ route('dashboard', ['panel' => 'lessons']) }}"
                               class="teacher-nav-link {{ $currentPanel === 'lessons' ? 'is-active' : '' }}"
                               data-td-open="lessons"
                               title="{{ __('Lesson') }}">
                                <i class="bi bi-easel2"></i>
                                <span>{{ __('Lesson') }}</span>
                            </a>
                        </li>
                        @endif
                        @if(!auth()->user() || auth()->user()->can('marks.enter'))
                        <li>
                            <a href="{{ route('dashboard', ['panel' => 'results']) }}"
                               class="teacher-nav-link {{ $currentPanel === 'results' ? 'is-active' : '' }}"
                               data-td-open="results"
                               title="{{ __('Result Management') }}">
                                <i class="bi bi-pencil-square"></i>
                                <span>{{ __('Result Management') }}</span>
                            </a>
                        </li>
                        @endif
                    </ul>
                </nav>

                <!-- 3. Header Actions (Notification Icon, Teacher Profile, Logout) -->
                <div class="teacher-actions d-flex align-items-center gap-2 gap-sm-3">
                    <!-- Notification Icon -->
                    <a href="{{ route('notifications.index') }}" class="btn-icon position-relative" title="{{ __('Notifications') }}" aria-label="{{ __('Notifications') }}">
                        <i class="bi bi-bell"></i>
                        @if($unread > 0)
                            <span class="notification-badge">{{ $unread }}</span>
                        @endif
                    </a>

                    <!-- Teacher Profile Dropdown -->
                    <div class="dropdown">
                        <button class="teacher-profile-trigger d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Teacher Profile') }}">
                            <div class="teacher-avatar">
                                @if($portalTeacher?->photo_path)
                                    <img src="{{ route('teacher.profile.photo', ['v' => $portalTeacher->photoVersion()]) }}" alt="{{ $portalTeacher->full_name }}">
                                @elseif($user?->avatar_path)
                                    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}">
                                @else
                                    <span>{{ strtoupper(substr($user?->name ?? 'T', 0, 1)) }}</span>
                                @endif
                            </div>
                            <div class="teacher-info text-start d-none d-md-block">
                                <div class="teacher-name">{{ $user?->name }}</div>
                                <div class="teacher-role">{{ __('Teacher') }}</div>
                            </div>
                            <i class="bi bi-chevron-down d-none d-md-inline-block text-white-50" style="font-size: .75rem;"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 teacher-profile-menu">
                            <li class="px-3 py-2 border-bottom">
                                <div class="fw-bold text-dark text-truncate" style="max-width: 200px;">{{ $user?->name }}</div>
                                <div class="text-muted small text-truncate" style="max-width: 200px;">{{ $user?->email }}</div>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="{{ $teacherProfileRoute }}">
                                    <i class="bi bi-person me-2 text-primary"></i>{{ __('My Profile') }}
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="{{ route('profile.password.change') }}">
                                    <i class="bi bi-key me-2 text-primary"></i>{{ __('Change Password') }}
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="{{ route('teacher.routine') }}">
                                    <i class="bi bi-calendar2-week me-2 text-primary"></i>{{ __('My Routine') }}
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item py-2" href="{{ route('teacher.leave.index') }}">
                                    <i class="bi bi-calendar2-x me-2 text-primary"></i>{{ __('My Leave') }}
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item py-2 text-danger">
                                        <i class="bi bi-box-arrow-right me-2"></i>{{ __('Logout') }}
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>

                    <!-- Logout Button -->
                    <form action="{{ route('logout') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn-icon btn-logout" title="{{ __('Logout') }}" aria-label="{{ __('Logout') }}">
                            <i class="bi bi-box-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Responsive Horizontal Navigation for Tablet & Mobile -->
            <div class="teacher-subnav d-lg-none mt-2 pt-2 border-top border-white-10">
                <nav class="teacher-nav-scroll" aria-label="{{ __('Compact Navigation Tabs') }}">
                    <div class="teacher-nav-scroll-track d-flex align-items-center gap-1">
                        <a href="{{ route('dashboard') }}"
                           class="teacher-nav-pill {{ $currentPanel === '' && request()->routeIs('dashboard') ? 'is-active' : '' }}"
                           data-td-open="dashboard"
                           title="{{ __('Dashboard') }}">
                            <i class="bi bi-grid-1x2"></i>
                            <span>{{ __('Dashboard') }}</span>
                        </a>
                        @if(!auth()->user() || auth()->user()->can('attendance.view') || auth()->user()->can('attendance.mark'))
                        <a href="{{ route('dashboard', ['panel' => 'attendance']) }}"
                           class="teacher-nav-pill {{ $currentPanel === 'attendance' ? 'is-active' : '' }}"
                           data-td-open="attendance"
                           title="{{ __('Attendance') }}">
                            <i class="bi bi-check2-square"></i>
                            <span>{{ __('Attendance') }}</span>
                        </a>
                        @endif
                        @if(!auth()->user() || auth()->user()->can('homework.view'))
                        <a href="{{ route('dashboard', ['panel' => 'homework']) }}"
                           class="teacher-nav-pill {{ $currentPanel === 'homework' ? 'is-active' : '' }}"
                           data-td-open="homework"
                           title="{{ __('Homework & Classwork') }}">
                            <i class="bi bi-journal-text"></i>
                            <span>{{ __('Homework & Classwork') }}</span>
                        </a>
                        @endif
                        @if(!auth()->user() || auth()->user()->can('materials.view'))
                        <a href="{{ route('dashboard', ['panel' => 'materials']) }}"
                           class="teacher-nav-pill {{ $currentPanel === 'materials' ? 'is-active' : '' }}"
                           data-td-open="materials"
                           title="{{ __('Study materials') }}">
                            <i class="bi bi-journal-bookmark"></i>
                            <span>{{ __('Materials') }}</span>
                        </a>
                        @endif
                        @if(!auth()->user() || auth()->user()->can('lessons.view'))
                        <a href="{{ route('dashboard', ['panel' => 'lessons']) }}"
                           class="teacher-nav-pill {{ $currentPanel === 'lessons' ? 'is-active' : '' }}"
                           data-td-open="lessons"
                           title="{{ __('Lesson') }}">
                            <i class="bi bi-easel2"></i>
                            <span>{{ __('Lesson') }}</span>
                        </a>
                        @endif
                        @if(!auth()->user() || auth()->user()->can('marks.enter'))
                        <a href="{{ route('dashboard', ['panel' => 'results']) }}"
                           class="teacher-nav-pill {{ $currentPanel === 'results' ? 'is-active' : '' }}"
                           data-td-open="results"
                           title="{{ __('Result Management') }}">
                            <i class="bi bi-pencil-square"></i>
                            <span>{{ __('Result Management') }}</span>
                        </a>
                        @endif
                    </div>
                </nav>
            </div>
        </div>
    </header>

    <main class="teacher-content" id="main-content">
        <div class="container-fluid px-3 px-xl-4">
            @include('partials.flash')
            @yield('content')
        </div>
    </main>

    <footer class="teacher-footer">
        <div class="container-fluid px-3 px-xl-4 d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span>{{ config('app.name') }} · {{ __('School Management System') }}</span>
            <span>{{ __('Teacher Workspace') }} · {{ now()->format('Y') }}</span>
        </div>
    </footer>

    <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}?v=7"></script>
    <script src="{{ asset('assets/js/notifications.js') }}?v=1" defer></script>
    @stack('scripts')
    @include('partials.pwa-ui')
</body>
</html>