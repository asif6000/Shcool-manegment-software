@php
    $shellSchool = $shellSchool ?? \App\Models\School::query()->first();
    $shellUser = $shellUser ?? auth()->user();
    $shellUnread = (int) ($shellUnreadCount ?? 0);
    $portalStudent = $student ?? null;
    $portalEnrollment = $enrollment ?? $portalStudent?->currentEnrollment();
    $shellSession = \App\Models\AcademicSession::query()
        ->where('is_current', true)
        ->orderByDesc('start_date')
        ->first();
    $brandInitials = $shellSchool?->initials() ?? 'EN';
@endphp

<header class="student-topbar student-portal-header">
    <div class="student-header-inner">
        <div class="topbar-start">
            <a class="topbar-brand" href="{{ route('student.dashboard') }}">
                <span class="brand-mark">
                    @if ($shellSchool?->logoUrl())
                        <img src="{{ $shellSchool->logoUrl() }}" alt="{{ $shellSchool->name }}">
                    @else
                        {{ $brandInitials }}
                    @endif
                </span>
                <span class="topbar-brand-copy">
                    <span class="topbar-brand-name">{{ $shellSchool?->name ?? config('app.name') }}</span>
                    @if ($shellSchool?->motto)
                        <span class="topbar-brand-motto">{{ $shellSchool->motto }}</span>
                    @endif
                </span>
            </a>
        </div>

        <div class="topbar-end">
            @if ($shellSession)
                <span class="session-pill" title="{{ __('Academic session') }}">
                    <i class="bi bi-calendar3"></i>
                    <span>{{ $shellSession->name }}</span>
                </span>
            @endif

            @include('partials.notifications-menu', ['unread' => $shellUnread])

            <div class="dropdown">
                <button class="btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Language') }}">
                    <i class="bi bi-globe2"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-2 py-1 small-caps">{{ __('Language') }}</li>
                    @foreach (config('school.locales') as $localeCode)
                        <li>
                            <form method="POST" action="{{ route('student.locale.update') }}">
                                @csrf
                                <input type="hidden" name="locale" value="{{ $localeCode }}">
                                <button type="submit" class="dropdown-item {{ app()->getLocale() === $localeCode ? 'active' : '' }}">
                                    <i class="bi {{ app()->getLocale() === $localeCode ? 'bi-check2-circle' : 'bi-circle' }}"></i>
                                    {{ $localeCode === 'bn' ? __('বাংলা') : __('English') }}
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>

            <button type="button" class="btn-icon" data-theme-toggle aria-label="{{ __('Switch theme') }}">
                <i class="bi bi-moon-stars theme-icon-dark"></i>
                <i class="bi bi-sun theme-icon-light"></i>
            </button>

            <div class="student-header-user">
                <div class="student-avatar">
                    @if ($portalStudent?->photo_path)
                        <img src="{{ route('student.photo', ['v' => $portalStudent->photoVersion()]) }}" alt="{{ $portalStudent->full_name }}">
                    @elseif ($shellUser?->avatarUrl())
                        <img src="{{ $shellUser->avatarUrl() }}" alt="{{ $shellUser->name }}">
                    @else
                        <span>{{ strtoupper(substr($shellUser?->name ?? 'S', 0, 1)) }}</span>
                    @endif
                </div>
                <div class="student-header-id">
                    <span class="student-header-name">{{ $shellUser?->name }}</span>
                    <span class="student-header-meta">
                        @if ($portalEnrollment?->schoolClass)
                            {{ __('Class') }} {{ $portalEnrollment->schoolClass->name }}
                        @endif
                        @if ($portalEnrollment?->section)
                            · {{ __('Sec: :section', ['section' => $portalEnrollment->section->name]) }}
                        @endif
                        @if ($portalEnrollment?->roll_no)
                            · {{ __('Roll: :roll', ['roll' => $portalEnrollment->roll_no]) }}
                        @endif
                    </span>
                </div>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="student-header-logout">
                @csrf
                <button type="submit" class="btn student-logout-btn" title="{{ __('Sign out') }}">
                    <i class="bi bi-box-arrow-right d-none d-sm-inline"></i>
                    <span>{{ __('Logout') }}</span>
                </button>
            </form>
        </div>
    </div>
</header>
