@php
    $school = $shellSchool ?? null;
    $user = $shellUser ?? auth()->user();
    $brandInitials = $school?->initials() ?? 'EN';
    $locale = app()->getLocale();
    $navItems = [
        ['route' => 'applicant.dashboard', 'pattern' => 'applicant.dashboard', 'icon' => 'bi-clipboard-check', 'label' => __('My applications')],
        ['route' => 'applicant.apply', 'pattern' => 'applicant.apply', 'icon' => 'bi-pencil-square', 'label' => __('New application')],
        ['route' => 'applicant.password.change', 'pattern' => 'applicant.password.*', 'icon' => 'bi-key', 'label' => __('Password')],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('Admission applicant')) · {{ $school?->short_name ?: config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=6">
    <link rel="stylesheet" href="{{ asset('assets/css/bengali.css') }}?v=1">
    @include('partials.pwa-head')
    @stack('head')
</head>
<body class="portal-body">
    <a class="visually-hidden-focusable position-absolute top-0 start-0 bg-white p-2" href="#portal-content">{{ __('Skip to content') }}</a>

    <header class="portal-header">
        <div class="portal-header-inner">
            <a href="{{ route('applicant.dashboard') }}" class="portal-brand">
                <span class="portal-brand-mark">
                    @if ($school?->logoUrl())
                        <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}">
                    @else
                        {{ $brandInitials }}
                    @endif
                </span>
                <span class="portal-brand-copy">
                    <span class="portal-brand-name">{{ $school?->short_name ?: ($school?->name ?? config('app.name')) }}</span>
                    <span class="portal-brand-meta">{{ __('Admission application') }}</span>
                </span>
            </a>

            <nav class="portal-nav" aria-label="{{ __('Applicant navigation') }}">
                @foreach ($navItems as $item)
                    <a href="{{ route($item['route']) }}" class="{{ request()->routeIs($item['pattern']) ? 'active' : '' }}">
                        <i class="bi {{ $item['icon'] }}"></i>{{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="portal-actions">
                @include('partials.notifications-menu', ['unread' => $shellUnreadCount ?? 0])

                <div class="dropdown">
                    <button class="icon-button" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Language') }}">
                        <i class="bi bi-translate"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-2 py-1 small-caps">{{ __('Language') }}</li>
                        @foreach (config('school.locales') as $code)
                            <li>
                                <form method="POST" action="{{ route('applicant.locale.update') }}">
                                    @csrf
                                    <input type="hidden" name="locale" value="{{ $code }}">
                                    <button type="submit" class="dropdown-item {{ $locale === $code ? 'active' : '' }}">
                                        <i class="bi {{ $locale === $code ? 'bi-check2-circle' : 'bi-circle' }}"></i>
                                        {{ $code === 'bn' ? __('বাংলা') : __('English') }}
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="dropdown">
                    <button class="user-menu" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar" aria-hidden="true">
                            @if ($user?->avatarUrl())
                                <img src="{{ $user->avatarUrl() }}" alt="">
                            @else
                                {{ $user?->initials() ?? 'A' }}
                            @endif
                        </span>
                        <span class="user-menu-copy">
                            <span class="user-menu-name">{{ $user?->name }}</span>
                            <span class="user-menu-role">{{ __('Applicant') }}</span>
                        </span>
                        <i class="bi bi-chevron-down text-muted-2" style="font-size:.7rem;"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" style="min-width:230px;">
                        <li class="px-2 py-1">
                            <div class="fw-600" style="font-size:.84rem;">{{ $user?->name }}</div>
                            <div class="text-muted-2" style="font-size:.72rem;">{{ $user?->email }}</div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('home') }}"><i class="bi bi-globe"></i>{{ __('School website') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('applicant.password.change') }}"><i class="bi bi-key"></i>{{ __('Change password') }}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right"></i>{{ __('Sign out') }}</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <main class="portal-main" id="portal-content">
        <div class="portal-container">
            @include('partials.flash')
            @yield('content')
        </div>
    </main>

    <footer class="portal-footer">
        <span>{{ $school?->name ?? config('app.name') }} · {{ __('Admission application') }}</span>
        <span>{{ now()->format('Y') }}</span>
    </footer>

    <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/app.js') }}?v=7"></script>
    <script src="{{ asset('assets/js/notifications.js') }}?v=1" defer></script>
    @stack('scripts')
    @include('partials.pwa-ui')
</body>
</html>
