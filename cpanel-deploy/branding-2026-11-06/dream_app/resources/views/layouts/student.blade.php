@php
    $school = $shellSchool ?? null;
    $user = $shellUser ?? auth()->user();
    $unread = $shellUnreadCount ?? 0;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('Student Portal')) · {{ $school?->short_name ?: config('app.name') }}</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=15">
    <link rel="stylesheet" href="{{ asset('assets/css/student-portal.css') }}?v=6">
    <link rel="stylesheet" href="{{ asset('assets/css/bengali.css') }}?v=1">
    @include('partials.pwa-head')
    <script>
        (function () {
            var theme = 'light';
            try {
                theme = localStorage.getItem('edunest.theme')
                    || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            } catch (error) {}
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>
    @stack('head')
    @stack('styles')
</head>
<body class="student-shell @yield('body-class')">
    <a class="visually-hidden-focusable position-absolute top-0 start-0 bg-white p-2" href="#main-content">{{ __('Skip to content') }}</a>

    @include('partials.student-topbar')

    <main class="student-main" id="main-content">
        @include('partials.student-flash')
        @yield('content')
    </main>

    <footer class="app-footer">
        <div class="container-fluid">
            <span>{{ $school?->name ?? config('app.name') }} · {{ __('Student portal') }}</span>
            <span>{{ now()->format('Y') }}</span>
        </div>
    </footer>

    <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}?v=7"></script>
    <script src="{{ asset('assets/js/notifications.js') }}?v=1" defer></script>
    <script src="{{ asset('assets/js/student-theme.js') }}?v=3"></script>
    <script src="{{ asset('assets/js/student-form.js') }}?v=2"></script>
    @stack('scripts')
    <script src="{{ asset('assets/js/student-dashboard.js') }}?v=6"></script>
    @include('partials.pwa-ui')
</body>
</html>
