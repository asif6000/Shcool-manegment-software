@php
    $school = $shellSchool ?? null;
    $user = $shellUser ?? auth()->user();
    $unread = $shellUnreadCount ?? 0;
    $brandInitials = $school?->initials() ?? 'EN';
    $isTeacherOnly = $user?->hasRole('teacher') && ! $user?->hasAnyRole(['school_admin', 'principal', 'super_admin']);
    // The cash desk gets no navigation column at all: the sidebar, its backdrop
    // and the button that opens it are simply not rendered for an accountant,
    // the same rule the teacher portal already follows.
    $isAccountantOnly = $user?->hasRole('accountant') && ! $user?->hasAnyRole(['school_admin', 'principal', 'super_admin']);
    $noSidebar = $isTeacherOnly || $isAccountantOnly;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('Dashboard')) · {{ config('app.name') }}</title>
    <script>
        (function () {
            try {
                var theme = localStorage.getItem('edunest.theme');
                if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                }
            } catch (error) {}
        })();
    </script>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=16">
    <link rel="stylesheet" href="{{ asset('assets/css/bengali.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-ui.css') }}?v=4">
    @include('partials.pwa-head')
    @stack('head')
    @stack('styles')
</head>
<body class="admin-shell {{ $isTeacherOnly ? 'is-teacher' : '' }}{{ $isAccountantOnly ? ' is-accountant accountant-shell' : '' }} @yield('body-class')">
    <a class="visually-hidden-focusable position-absolute top-0 start-0 bg-white p-2" href="#main-content">{{ __('Skip to content') }}</a>

    <div class="app-shell {{ $noSidebar ? 'no-sidebar' : '' }} {{ $isTeacherOnly ? 'is-teacher' : '' }} {{ $isAccountantOnly ? 'is-accountant' : '' }}">
        @unless ($noSidebar)
            @include('partials.sidebar')
            <div class="app-backdrop" data-sidebar-close></div>
        @endunless

        <div class="app-main {{ $noSidebar ? 'w-100 ms-0' : '' }}">
            @include('partials.topbar')

            <main class="app-content" id="main-content">
                @include('partials.flash')
                @yield('content')
            </main>

            <footer class="app-footer">
                <span>{{ config('app.name') }} · {{ __('School management for Bangladesh') }}</span>
                <span>{{ __('Admin workspace') }} · {{ now()->format('Y') }}</span>
            </footer>
        </div>
    </div>

    @include('partials.confirm-dialog')

    <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}?v=9"></script>
    <script src="{{ asset('assets/js/notifications.js') }}?v=1" defer></script>
    @stack('scripts')
    @include('partials.pwa-ui')
</body>
</html>

