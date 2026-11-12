@php
    $school = $school ?? \App\Models\School::first();
    $user = $actor ?? auth()->user();
    $unread = $user?->unreadNotifications()->count() ?? 0;
    $brandInitials = $school?->initials() ?? 'DR';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', __('Accountant Dashboard')) · {{ $school?->name ?? 'Dream Roots Millennium School' }}</title>
    
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=12">
    <link rel="stylesheet" href="{{ asset('assets/css/admin-ui.css') }}?v=4">
    
    <style>
        :root {
            --navy-primary: #0a1d3d;
            --navy-dark: #061327;
            --navy-light: #162f5d;
            --emerald-primary: #10b981;
            --emerald-dark: #059669;
            --emerald-light: #34d399;
            --emerald-soft: rgba(16, 185, 129, 0.12);
            --navy-soft: rgba(10, 29, 61, 0.05);
            --card-radius: 16px;
        }

        body.accountant-shell {
            background: #f1f5f9;
            color: #1e293b;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* STRICT UI: Absolutely no sidebar, no left/right menu, no drawer */
        .accountant-shell .app-sidebar,
        .accountant-shell .sidebar-toggle,
        .accountant-shell .app-backdrop,
        .accountant-shell .sidebar-close {
            display: none !important;
        }

        /* Accountant Header */
        .accountant-header {
            background: linear-gradient(135deg, #061327 0%, #0a1d3d 50%, #0c234a 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff;
            position: sticky;
            top: 0;
            z-index: 1020;
            box-shadow: 0 4px 20px rgba(6, 19, 39, 0.25);
        }

        .accountant-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 24px;
            max-width: 1520px;
            margin: 0 auto;
        }

        .accountant-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: #ffffff;
        }

        .accountant-brand:hover {
            color: #ffffff;
        }

        .accountant-logo-box {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 1.15rem;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
            overflow: hidden;
            flex-shrink: 0;
        }

        .accountant-logo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .accountant-school-name {
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: -0.01em;
            line-height: 1.2;
            display: block;
        }

        .accountant-sub-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.72rem;
            font-weight: 600;
            color: #a7f3d0;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .accountant-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Profile card in header */
        .accountant-profile-pill {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 14px 6px 8px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 999px;
            color: #ffffff;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .accountant-profile-pill:hover {
            background: rgba(255, 255, 255, 0.15);
            color: #ffffff;
        }

        .accountant-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #10b981;
            color: #ffffff;
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .accountant-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .accountant-meta {
            line-height: 1.15;
            text-align: left;
        }

        .accountant-meta-name {
            font-size: 0.84rem;
            font-weight: 600;
            display: block;
        }

        .accountant-meta-role {
            font-size: 0.68rem;
            color: #94a3b8;
            display: block;
        }

        /* Header Notification Button */
        .accountant-notify-btn {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #ffffff;
            display: grid;
            place-items: center;
            font-size: 1.1rem;
            transition: all 0.2s;
        }

        .accountant-notify-btn:hover {
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff;
        }

        .accountant-badge-count {
            position: absolute;
            top: -3px;
            right: -3px;
            background: #ef4444;
            color: #fff;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 2px 6px;
            min-width: 18px;
            height: 18px;
            display: grid;
            place-items: center;
            border: 2px solid #061327;
        }

        /* Logout button */
        .accountant-logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(239, 68, 68, 0.14);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            border-radius: 10px;
            padding: 8px 14px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .accountant-logout-btn:hover {
            background: #ef4444;
            color: #ffffff;
            border-color: #ef4444;
        }

        /* Main Container */
        .accountant-container {
            max-width: 1520px;
            margin: 0 auto;
            padding: 24px 20px 48px;
        }

        /* Modern KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 16px;
            margin-bottom: 26px;
        }

        .kpi-card {
            background: #ffffff;
            border-radius: var(--card-radius);
            padding: 18px 20px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
            border-color: #cbd5e1;
        }

        .kpi-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #10b981, #059669);
            opacity: 0;
            transition: opacity 0.2s;
        }

        .kpi-card:hover::before {
            opacity: 1;
        }

        .kpi-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 10px;
        }

        .kpi-label {
            font-size: 0.76rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin: 0;
        }

        .kpi-icon-wrap {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #f1f5f9;
            color: #0a1d3d;
            display: grid;
            place-items: center;
            font-size: 1.15rem;
            flex-shrink: 0;
        }

        .kpi-icon-wrap.is-emerald {
            background: rgba(16, 185, 129, 0.12);
            color: #059669;
        }

        .kpi-icon-wrap.is-navy {
            background: rgba(10, 29, 61, 0.1);
            color: #0a1d3d;
        }

        .kpi-icon-wrap.is-amber {
            background: rgba(245, 158, 11, 0.12);
            color: #d97706;
        }

        .kpi-icon-wrap.is-purple {
            background: rgba(139, 92, 246, 0.12);
            color: #7c3aed;
        }

        .kpi-icon-wrap.is-rose {
            background: rgba(244, 63, 94, 0.12);
            color: #e11d48;
        }

        .kpi-value {
            font-size: 1.45rem;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            line-height: 1.15;
            letter-spacing: -0.02em;
        }

        .kpi-subtext {
            font-size: 0.72rem;
            color: #64748b;
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Action Cards Section */
        .section-heading-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .section-title-tag {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
        }

        .section-title-tag i {
            color: #10b981;
            font-size: 1.1rem;
        }

        .action-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }

        .action-card-btn {
            background: #ffffff;
            border-radius: var(--card-radius);
            padding: 20px 22px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
            text-align: left;
            cursor: pointer;
            transition: all 0.22s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 130px;
            width: 100%;
        }

        .action-card-btn:hover {
            transform: translateY(-3px);
            border-color: #10b981;
            box-shadow: 0 12px 30px rgba(16, 185, 129, 0.15);
        }

        .action-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .action-icon-badge {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #0a1d3d 0%, #162f5d 100%);
            color: #ffffff;
            display: grid;
            place-items: center;
            font-size: 1.3rem;
            box-shadow: 0 4px 12px rgba(10, 29, 61, 0.2);
            transition: transform 0.2s;
        }

        .action-card-btn:hover .action-icon-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            transform: scale(1.05);
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        }

        .action-card-chevron {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #f8fafc;
            color: #94a3b8;
            display: grid;
            place-items: center;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .action-card-btn:hover .action-card-chevron {
            background: #10b981;
            color: #ffffff;
            transform: translateX(3px);
        }

        .action-card-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px;
            display: block;
        }

        .action-card-desc {
            font-size: 0.78rem;
            color: #64748b;
            margin: 0;
            line-height: 1.35;
        }

        /* Bottom Tables & Chart Grid */
        .bottom-grid {
            display: grid;
            grid-template-columns: 7fr 5fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .dashboard-panel {
            background: #ffffff;
            border-radius: var(--card-radius);
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04);
            padding: 22px;
        }

        .panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5f9;
        }

        .panel-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-title i {
            color: #059669;
        }

        /* Modern Table */
        .dash-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.82rem;
        }

        .dash-table th {
            background: #f8fafc;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.04em;
            padding: 9px 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .dash-table td {
            padding: 11px 12px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .dash-table tr:hover td {
            background: #f8fafc;
        }

        /* Badges */
        .badge-emerald {
            background: rgba(16, 185, 129, 0.14);
            color: #047857;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-navy {
            background: rgba(10, 29, 61, 0.1);
            color: #0a1d3d;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-amber {
            background: rgba(245, 158, 11, 0.14);
            color: #b45309;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1200px) {
            .kpi-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .action-cards-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .bottom-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .action-cards-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            .accountant-header-inner {
                padding: 12px 16px;
            }
            .accountant-school-name {
                font-size: 0.98rem;
            }
            .accountant-meta {
                display: none;
            }
            .accountant-container {
                padding: 16px 12px 32px;
            }
        }
    </style>
    @stack('styles')
</head>
<body class="accountant-shell">

    {{-- Strict UI Header --}}
    <header class="accountant-header">
        <div class="accountant-header-inner">
            <a href="{{ route('dashboard') }}" class="accountant-brand">
                <div class="accountant-logo-box">
                    @if ($school?->logoUrl())
                        <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}">
                    @else
                        {{ $brandInitials }}
                    @endif
                </div>
                <div>
                    <span class="accountant-school-name">{{ $school?->name ?? 'Dream Roots Millennium School' }}</span>
                    <span class="accountant-sub-badge"><i class="bi bi-shield-check"></i> {{ __('School Accounts Desk') }}</span>
                </div>
            </a>

            <div class="accountant-header-actions">
                {{-- Accountant Profile --}}
                <button type="button" class="accountant-profile-pill" data-bs-toggle="modal" data-bs-target="#modalMyProfile">
                    <div class="accountant-avatar">
                        @if ($user?->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="">
                        @else
                            {{ $user?->initials() ?? 'A' }}
                        @endif
                    </div>
                    <div class="accountant-meta">
                        <span class="accountant-meta-name">{{ $user?->name }}</span>
                        <span class="accountant-meta-role">{{ __('Accountant') }}</span>
                    </div>
                </button>

                {{-- Notifications Menu --}}
                <div class="dropdown">
                    <button class="accountant-notify-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('Notifications') }}">
                        <i class="bi bi-bell"></i>
                        @if ($unread > 0)
                            <span class="accountant-badge-count">{{ $unread > 9 ? '9+' : $unread }}</span>
                        @endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-end shadow-lg p-0" style="width: 320px; border-radius: 14px; overflow: hidden; border: 1px solid #e2e8f0;">
                        <div class="p-3 bg-light d-flex align-items-center justify-content-between border-bottom">
                            <span class="fw-bold small">{{ __('Notifications') }}</span>
                            <span class="badge bg-primary rounded-pill">{{ $unread }}</span>
                        </div>
                        <div class="p-2" style="max-height: 280px; overflow-y: auto;">
                            @if ($unread === 0)
                                <div class="text-center py-4 text-muted small">
                                    <i class="bi bi-check2-circle fs-3 text-success d-block mb-1"></i>
                                    {{ __('All caught up! No unread notifications.') }}
                                </div>
                            @else
                                <div class="p-2 text-muted small">
                                    {{ __('You have :count unread notifications.', ['count' => $unread]) }}
                                </div>
                            @endif
                        </div>
                        <div class="p-2 border-top bg-light text-center">
                            <a href="{{ route('notifications.index') }}" class="small text-decoration-none fw-semibold">{{ __('View all notices') }}</a>
                        </div>
                    </div>
                </div>

                {{-- Sign Out / Logout --}}
                <form method="POST" action="{{ route('logout') }}" class="m-0">
                    @csrf
                    <button type="submit" class="accountant-logout-btn" title="{{ __('Sign out') }}">
                        <i class="bi bi-box-arrow-right"></i>
                        <span class="d-none d-sm-inline">{{ __('Logout') }}</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    {{-- Main Accountant Workspace --}}
    <main class="accountant-container">
        @include('partials.flash')
        @yield('content')
    </main>

    <footer class="text-center py-4 text-muted small border-top bg-white">
        <span>{{ $school?->name ?? 'Dream Roots Millennium School' }} · {{ __('Financial Workspace') }} · {{ now()->format('Y') }}</span>
    </footer>

    <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
