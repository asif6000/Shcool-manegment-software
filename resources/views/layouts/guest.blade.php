@php
    $school = $shellSchool ?? null;
    $schoolName = $school?->short_name ?: ($school?->name ?? config('app.name'));
    $brandInitials = $school?->initials() ?? 'EN';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('Sign in')) · {{ $schoolName }}</title>
    @include('partials.favicon')
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=6">
    <link rel="stylesheet" href="{{ asset('assets/css/bengali.css') }}?v=1">
    @include('partials.pwa-head')
</head>
<body class="auth-page">
    <aside class="auth-aside" aria-hidden="true">
        <a class="auth-brand" href="{{ route('home') }}">
            <span class="brand-mark">
                @if ($school?->logoUrl())
                    <img src="{{ $school->logoUrl() }}" alt="{{ $schoolName }}">
                @else
                    {{ $brandInitials }}
                @endif
            </span>
            <span class="brand-copy">
                <span class="brand-name">{{ $schoolName }}</span>
                <span class="brand-meta">{{ __('School management for Bangladesh') }}</span>
            </span>
        </a>

        <div class="auth-copy">
            <span class="eyebrow"><i class="bi bi-stars"></i>{{ __('Built for modern schools') }}</span>
            <h1>{{ __('One calm place to run your whole school.') }}</h1>
            <p>{{ __('Admissions, attendance and fees — connected and secure, with a dedicated portal for every learner. English and বাংলা supported.') }}</p>
            <div class="auth-features">
                <span class="auth-feature"><i class="bi bi-shield-check"></i>{{ __('Role-based access') }}</span>
                <span class="auth-feature"><i class="bi bi-building"></i>{{ __('One school, one workspace') }}</span>
                <span class="auth-feature"><i class="bi bi-journal-text"></i>{{ __('Audit trail') }}</span>
            </div>
        </div>

        <div class="auth-illustration">
            <svg viewBox="0 0 560 340" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ __('Illustration of a school campus') }}">
                <defs>
                    <linearGradient id="building" x1="180" y1="120" x2="380" y2="300" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#F8FBFF"/>
                        <stop offset="1" stop-color="#DCE9FF"/>
                    </linearGradient>
                    <linearGradient id="roof" x1="190" y1="100" x2="370" y2="170" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#60A5FA"/>
                        <stop offset="1" stop-color="#2563EB"/>
                    </linearGradient>
                    <linearGradient id="card" x1="0" y1="0" x2="1" y2="1">
                        <stop stop-color="#FFFFFF" stop-opacity=".98"/>
                        <stop offset="1" stop-color="#EAF3FF" stop-opacity=".92"/>
                    </linearGradient>
                </defs>

                <ellipse cx="280" cy="300" rx="210" ry="24" fill="#07162F" fill-opacity=".22"/>
                <circle cx="120" cy="96" r="52" fill="#60A5FA" fill-opacity=".14"/>
                <circle cx="455" cy="132" r="70" fill="#38BDF8" fill-opacity=".12"/>
                <circle cx="470" cy="72" r="5" fill="#BAE6FD" fill-opacity=".8"/>
                <circle cx="88" cy="210" r="4" fill="#BFDBFE" fill-opacity=".7"/>
                <circle cx="416" cy="228" r="6" fill="#BFDBFE" fill-opacity=".55"/>

                <rect x="176" y="154" width="208" height="138" rx="12" fill="url(#building)"/>
                <rect x="176" y="154" width="208" height="138" rx="12" stroke="#BFDBFE" stroke-opacity=".7"/>
                <path d="M160 158 280 92l120 66H160Z" fill="url(#roof)"/>
                <path d="M280 92 160 158h240L280 92Z" fill="#93C5FD" fill-opacity=".28"/>
                <circle cx="280" cy="128" r="14" fill="#EFF6FF"/>
                <path d="M280 121v8l5 3" stroke="#1D4ED8" stroke-width="2.4" stroke-linecap="round"/>
                <rect x="197" y="184" width="26" height="70" rx="5" fill="#93C5FD" fill-opacity=".55"/>
                <rect x="239" y="184" width="26" height="70" rx="5" fill="#93C5FD" fill-opacity=".55"/>
                <rect x="337" y="184" width="26" height="70" rx="5" fill="#93C5FD" fill-opacity=".55"/>
                <rect x="296" y="184" width="26" height="70" rx="5" fill="#93C5FD" fill-opacity=".55"/>
                <rect x="258" y="213" width="44" height="79" rx="8" fill="#1D4ED8"/>
                <rect x="258" y="213" width="44" height="79" rx="8" fill="#2563EB" fill-opacity=".4"/>
                <circle cx="291" cy="255" r="2.6" fill="#DBEAFE"/>
                <rect x="176" y="292" width="208" height="8" rx="4" fill="#BFDBFE" fill-opacity=".7"/>

                <path d="M96 292c0-28 15-49 33-49s33 21 33 49" fill="#34D399" fill-opacity=".5"/>
                <rect x="122" y="270" width="14" height="30" rx="6" fill="#0F766E" fill-opacity=".55"/>
                <path d="M426 292c0-24 13-42 29-42s29 18 29 42" fill="#34D399" fill-opacity=".42"/>
                <rect x="450" y="274" width="12" height="26" rx="5" fill="#0F766E" fill-opacity=".5"/>

                <g>
                    <rect x="52" y="106" width="126" height="68" rx="14" fill="url(#card)" stroke="#BFDBFE"/>
                    <circle cx="76" cy="132" r="12" fill="#DBEAFE"/>
                    <path d="m70 132 4 4 8-9" stroke="#2563EB" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>
                    <rect x="96" y="124" width="58" height="6" rx="3" fill="#94A3B8"/>
                    <rect x="96" y="138" width="40" height="5" rx="2.5" fill="#CBD5E1"/>
                    <rect x="68" y="154" width="86" height="5" rx="2.5" fill="#CBD5E1"/>
                </g>

                <g>
                    <rect x="392" y="88" width="112" height="82" rx="14" fill="url(#card)" stroke="#BFDBFE"/>
                    <circle cx="420" cy="114" r="14" fill="#D1FAE5"/>
                    <text x="420" y="119.5" text-anchor="middle" font-family="Segoe UI, sans-serif" font-size="13" font-weight="700" fill="#047857">A+</text>
                    <rect x="444" y="105" width="44" height="6" rx="3" fill="#94A3B8"/>
                    <rect x="444" y="118" width="30" height="5" rx="2.5" fill="#CBD5E1"/>
                    <rect x="412" y="142" width="74" height="5" rx="2.5" fill="#CBD5E1"/>
                    <rect x="412" y="153" width="52" height="5" rx="2.5" fill="#E2E8F0"/>
                </g>

                <g>
                    <rect x="344" y="246" width="96" height="52" rx="12" fill="url(#card)" stroke="#BFDBFE"/>
                    <circle cx="368" cy="272" r="10" fill="#EDE9FE"/>
                    <i></i>
                    <path d="M363 272c2-5 8-5 10 0" stroke="#7C3AED" stroke-width="2" stroke-linecap="round"/>
                    <rect x="386" y="263" width="38" height="5" rx="2.5" fill="#94A3B8"/>
                    <rect x="386" y="275" width="26" height="4" rx="2" fill="#CBD5E1"/>
                </g>

                <g opacity=".9">
                    <circle cx="206" cy="70" r="3" fill="#DBEAFE"/>
                    <circle cx="352" cy="62" r="2.4" fill="#BAE6FD"/>
                    <path d="M310 44v10M305 49h10" stroke="#DBEAFE" stroke-width="2" stroke-linecap="round"/>
                    <path d="M132 56v8M128 60h8" stroke="#BAE6FD" stroke-width="1.8" stroke-linecap="round"/>
                </g>
            </svg>
        </div>

        <p class="auth-quote">“{{ __('A secure, single source of truth for every learner and every taka.') }}”</p>
    </aside>

    <main class="auth-panel">
        <div class="auth-form-wrap">
            <a class="auth-mobile-brand" href="{{ route('home') }}">
                <span class="brand-mark" style="width:38px;height:38px;border-radius:12px;font-size:.9rem;">
                    @if ($school?->logoUrl())
                        <img src="{{ $school->logoUrl() }}" alt="{{ $schoolName }}">
                    @else
                        {{ $brandInitials }}
                    @endif
                </span>
                <span>{{ $schoolName }}</span>
            </a>

            @if (session('success') || session('status') || session('warning') || session('info') || $errors->any())
                <div class="flash-stack">
                    @if (session('success'))
                        <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i><div>{{ session('success') }}</div></div>
                    @endif
                    @if (session('status'))
                        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i><div>{{ session('status') }}</div></div>
                    @endif
                    @if (session('warning'))
                        <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i><div>{{ session('warning') }}</div></div>
                    @endif
                    @if (session('info'))
                        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i><div>{{ session('info') }}</div></div>
                    @endif
                    @if ($errors->any() && ! $errors->has('email') && ! $errors->has('password'))
                        <div class="alert alert-danger"><i class="bi bi-exclamation-octagon-fill"></i><div>{{ $errors->first() }}</div></div>
                    @endif
                </div>
            @endif

            @yield('panel')

            <p class="auth-footer">
                {{ __('Need access? Contact your school administrator.') }}<br>
                &copy; {{ now()->year }} {{ $schoolName }}. {{ __('All rights reserved.') }}
            </p>
        </div>
    </main>
    @include('partials.pwa-ui')
</body>
</html>
