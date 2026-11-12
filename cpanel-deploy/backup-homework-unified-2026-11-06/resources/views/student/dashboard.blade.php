@extends('layouts.student')

@section('title', __('Dashboard'))

@section('content')
@php
    $money = fn (float $value): string => '৳ '.number_format($value, 2);

    $attendanceBadge = [
        'present' => 'is-success',
        'absent' => 'is-danger',
        'late' => 'is-warning',
        'leave' => 'is-info',
    ];

    $homeworkRows = $homeworkData['rows']->items();
    $homeworkStatuses = $homeworkData['statuses'];
    $pendingHomework = collect($homeworkRows)->filter(
        fn ($row) => ! in_array($homeworkStatuses[$row->id] ?? null, ['submitted', 'late', 'reviewed'], true)
    )->count();

    $latestResult = $resultsData['results']->first();
    $upcomingToday = $todayClasses->filter(fn ($class) => $class->start_time >= now()->format('H:i:s'));

    $moduleNav = [
        'dashboard' => ['icon' => 'bi-grid-1x2-fill', 'tone' => 'blue', 'label' => __('Dashboard')],
        'profile' => ['icon' => 'bi-person-fill', 'tone' => 'indigo', 'label' => __('Profile')],
        'attendance' => ['icon' => 'bi-calendar2-check-fill', 'tone' => 'green', 'label' => __('Attendance')],
        'homework' => ['icon' => 'bi-clipboard-check-fill', 'tone' => 'orange', 'label' => __('Homework')],
        'classwork' => ['icon' => 'bi-pencil-square', 'tone' => 'purple', 'label' => __('Classwork')],
        'lessons' => ['icon' => 'bi-play-circle-fill', 'tone' => 'cyan', 'label' => __('Lessons')],
        'results' => ['icon' => 'bi-trophy-fill', 'tone' => 'gold', 'label' => __('Results')],
        'routine' => ['icon' => 'bi-calendar3', 'tone' => 'blue', 'label' => __('Routine')],
        'fees' => ['icon' => 'bi-wallet2', 'tone' => 'emerald', 'label' => __('Fees')],
        'notices' => ['icon' => 'bi-megaphone-fill', 'tone' => 'orange', 'label' => __('Notices')],
        'online-classes' => ['icon' => 'bi-camera-video-fill', 'tone' => 'violet', 'label' => __('Online Classes')],
        'materials' => ['icon' => 'bi-journal-text', 'tone' => 'teal', 'label' => __('Study Materials')],
        'notifications' => ['icon' => 'bi-bell-fill', 'tone' => 'red', 'label' => __('Notifications')],
        'settings' => ['icon' => 'bi-gear-fill', 'tone' => 'slate', 'label' => __('Settings')],
    ];
@endphp

<div class="student-portal" @if ($errors->has('current_password') || $errors->has('password')) data-open-module="settings" @endif>
    @include('partials.student-module-nav', ['navItems' => $moduleNav])

    {{-- ============================== DASHBOARD ============================== --}}
    <section class="portal-module" data-module="dashboard">
        <div class="dash-hero ui-card">
            <div class="dash-hero-copy">
                <h1 class="dash-hero-title">{{ __('Welcome back, :name!', ['name' => $student->full_name]) }}</h1>
                <p class="dash-hero-sub">{{ __('Have a great learning journey at :school.', ['school' => $shellSchool?->name ?? config('app.name')]) }}</p>
                @if ($enrollment?->schoolClass)
                    <span class="dash-hero-class">
                        <i class="bi bi-mortarboard-fill"></i>
                        {{ $enrollment->schoolClass->name }}
                        @if ($enrollment->section) · {{ $enrollment->section->name }} @endif
                    </span>
                @endif
            </div>
            <div class="dash-hero-art" aria-hidden="true">
                <i class="bi bi-mortarboard-fill"></i>
                <i class="bi bi-book-fill"></i>
                <i class="bi bi-pencil-fill"></i>
            </div>
        </div>

        <div class="row g-3 stat-row">
            <div class="col-6 col-xl-3">
                <div class="stat-card is-brand">
                    <div class="stat-top">
                        <span class="stat-label">{{ __('Attendance') }} · {{ now()->format('M') }}</span>
                        <span class="stat-icon"><i class="bi bi-calendar2-check"></i></span>
                    </div>
                    <p class="stat-value">
                        {{ $monthSummary['percentage'] !== null ? number_format($monthSummary['percentage'], 1).'%' : '—' }}
                    </p>
                    <p class="stat-foot">{{ trans_choice(':count day|:count days', $monthSummary['recorded'], ['count' => $monthSummary['recorded']]) }}</p>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card is-emerald">
                    <div class="stat-top">
                        <span class="stat-label">{{ __('Pending homework') }}</span>
                        <span class="stat-icon"><i class="bi bi-journal-bookmark"></i></span>
                    </div>
                    <p class="stat-value">{{ $pendingHomework }}</p>
                    <p class="stat-foot">
                        <button type="button" class="stat-link" data-module-target="homework">{{ __('View') }} <i class="bi bi-arrow-right"></i></button>
                    </p>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card is-purple">
                    <div class="stat-top">
                        <span class="stat-label">{{ __('Latest result') }}</span>
                        <span class="stat-icon"><i class="bi bi-trophy"></i></span>
                    </div>
                    @if ($latestResult)
                        <p class="stat-value stat-value-sm">{{ $latestResult->exam?->name }}</p>
                        <p class="stat-foot">
                            <button type="button" class="stat-link" data-module-target="results">{{ __('View') }} <i class="bi bi-arrow-right"></i></button>
                        </p>
                    @else
                        <p class="stat-value">—</p>
                        <p class="stat-foot">{{ __('No result published yet') }}</p>
                    @endif
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="stat-card is-amber">
                    <div class="stat-top">
                        <span class="stat-label">{{ __('Due fees') }}</span>
                        <span class="stat-icon"><i class="bi bi-cash-stack"></i></span>
                    </div>
                    <p class="stat-value">{{ $money((float) $fees['outstanding']) }}</p>
                    <p class="stat-foot">
                        <button type="button" class="stat-link" data-module-target="fees">{{ __('View details') }} <i class="bi bi-arrow-right"></i></button>
                    </p>
                </div>
            </div>
        </div>

        <div class="portal-grid">
            <article class="ui-card p-0">
                <div class="portal-card-head is-brand">
                    <span><i class="bi bi-calendar3"></i> {{ __("Today's routine") }}</span>
                    <button type="button" class="portal-card-link" data-module-target="routine">{{ __('View all') }} <i class="bi bi-arrow-right"></i></button>
                </div>
                <div class="portal-card-body">
                    @forelse ($todayClasses as $class)
                        <div class="portal-list-item">
                            <div class="portal-list-main">
                                <span class="portal-list-title">{{ $class->subject?->name ?? __('Class') }}</span>
                                <span class="portal-list-meta">
                                    {{ \Illuminate\Support\Carbon::parse($class->start_time)->format('h:i A') }}
                                    – {{ \Illuminate\Support\Carbon::parse($class->end_time)->format('h:i A') }}
                                    · {{ $class->teacher?->name ?? __('Teacher') }}
                                    · {{ __('Room') }} {{ $class->room ?: '—' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">
                            <i class="bi bi-calendar2-x empty-icon"></i>
                            <p class="mb-0">{{ __('No classes scheduled today.') }}</p>
                            <button type="button" class="portal-card-cta" data-module-target="routine">
                                <i class="bi bi-calendar-week"></i>{{ __('Routine') }}
                            </button>
                        </div>
                    @endforelse
                </div>
            </article>

            <article class="ui-card p-0">
                <div class="portal-card-head is-emerald">
                    <span><i class="bi bi-megaphone"></i> {{ __('Recent notices') }}</span>
                    <button type="button" class="portal-card-link" data-module-target="notices">{{ __('View all') }} <i class="bi bi-arrow-right"></i></button>
                </div>
                <div class="portal-card-body">
                    @forelse ($notices as $notice)
                        <div class="portal-list-item">
                            <div class="portal-list-main">
                                <span class="portal-list-title">
                                    {{ $notice->title }}
                                    @if ($notice->published_at?->greaterThanOrEqualTo(now()->subDays(7)))
                                        <span class="badge-soft is-danger">{{ __('New') }}</span>
                                    @endif
                                </span>
                                <span class="portal-list-meta">{{ __('Published: :date', ['date' => $notice->published_at?->format('M j, Y') ?? '—']) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="empty-state">
                            <i class="bi bi-megaphone empty-icon"></i>
                            <p class="mb-0">{{ __('No notices yet.') }}</p>
                            <button type="button" class="portal-card-cta" data-module-target="notices">
                                <i class="bi bi-megaphone"></i>{{ __('Notices') }}
                            </button>
                        </div>
                    @endforelse
                </div>
            </article>

            <article class="ui-card p-0">
                <div class="portal-card-head is-navy">
                    <span><i class="bi bi-camera-video"></i> {{ __('Upcoming classes') }}</span>
                    <button type="button" class="portal-card-link" data-module-target="lessons">{{ __('View all') }} <i class="bi bi-arrow-right"></i></button>
                </div>
                <div class="portal-card-body">
                    @forelse ($upcomingToday as $class)
                        <div class="portal-list-item">
                            <div class="portal-list-main">
                                <span class="portal-list-title">{{ $class->subject?->name ?? __('Class') }}</span>
                                <span class="portal-list-meta">
                                    {{ __('Today') }}, {{ \Illuminate\Support\Carbon::parse($class->start_time)->format('h:i A') }}
                                    – {{ \Illuminate\Support\Carbon::parse($class->end_time)->format('h:i A') }}
                                </span>
                            </div>
                            <span class="badge-soft is-info">{{ __('Upcoming') }}</span>
                        </div>
                    @empty
                        <div class="empty-state">
                            <i class="bi bi-clock-history empty-icon"></i>
                            <p class="mb-0">{{ __('No more classes today.') }}</p>
                            <button type="button" class="portal-card-cta" data-module-target="routine">
                                <i class="bi bi-calendar-week"></i>{{ __('Routine') }}
                            </button>
                        </div>
                    @endforelse
                </div>
            </article>

            <article class="ui-card p-0">
                <div class="portal-card-head is-brand">
                    <span><i class="bi bi-bar-chart-fill"></i> {{ __('Attendance overview') }}</span>
                    <button type="button" class="portal-card-link" data-module-target="attendance">{{ __('View all') }} <i class="bi bi-arrow-right"></i></button>
                </div>
                <div class="portal-card-body">
                    <p class="att-period">{{ now()->format('F Y') }}</p>
                    @if ($monthSummary['recorded'] > 0)
                        <div class="att-donut-wrap">
                            <canvas id="attendanceDonut" width="180" height="180"
                                    data-values='{{ json_encode([$monthSummary['counts']['present'], $monthSummary['counts']['absent'], $monthSummary['counts']['late'], $monthSummary['counts']['leave']]) }}'></canvas>
                            <div class="att-donut-center">
                                <strong>{{ $monthSummary['percentage'] !== null ? number_format($monthSummary['percentage'], 1).'%' : '—' }}</strong>
                                <span>{{ __('Present') }}</span>
                            </div>
                        </div>
                        <ul class="att-legend">
                            @foreach (['present' => 'is-success', 'absent' => 'is-danger', 'late' => 'is-warning', 'leave' => 'is-info'] as $status => $badgeClass)
                                <li><span class="att-dot {{ $badgeClass }}"></span> {{ __(ucfirst($status)) }} <strong>{{ $monthSummary['counts'][$status] }}</strong></li>
                            @endforeach
                        </ul>
                    @else
                        <div class="empty-state">
                            <i class="bi bi-calendar2-x empty-icon"></i>
                            <p class="mb-0">{{ __('No attendance recorded this month.') }}</p>
                            <button type="button" class="portal-card-cta" data-module-target="attendance">
                                <i class="bi bi-calendar2-check"></i>{{ __('Attendance') }}
                            </button>
                        </div>
                    @endif
                </div>
            </article>

            <article class="ui-card p-0">
                <div class="portal-card-head is-emerald">
                    <span><i class="bi bi-journal-bookmark"></i> {{ __('Recent homework') }}</span>
                    <button type="button" class="portal-card-link" data-module-target="homework">{{ __('View all') }} <i class="bi bi-arrow-right"></i></button>
                </div>
                <div class="portal-card-body">
                    @forelse ($homeworkDue['rows'] as $homework)
                        @php
                            $hwStatus = $homeworkDue['statuses'][$homework->id] ?? null;
                        @endphp
                        <div class="portal-list-item">
                            <div class="portal-list-main">
                                <span class="portal-list-title">{{ $homework->title }}</span>
                                <span class="portal-list-meta">
                                    {{ $homework->subject?->name ?? __('Subject') }}
                                    · {{ __('Due: :date', ['date' => $homework->due_date?->format('M j, Y') ?? '—']) }}
                                </span>
                            </div>
                            @if ($hwStatus === 'reviewed')
                                <span class="badge-soft is-success">{{ __('Reviewed') }}</span>
                            @elseif ($hwStatus !== null)
                                <span class="badge-soft is-info">{{ __('Submitted') }}</span>
                            @elseif ($homework->due_date?->isPast())
                                <span class="badge-soft is-danger">{{ __('Overdue') }}</span>
                            @else
                                <span class="badge-soft is-warning">{{ __('Pending') }}</span>
                            @endif
                        </div>
                    @empty
                        <div class="empty-state">
                            <i class="bi bi-clipboard-check empty-icon"></i>
                            <p class="mb-0">{{ __('No homework due in the next week.') }}</p>
                            <button type="button" class="portal-card-cta" data-module-target="homework">
                                <i class="bi bi-journal-bookmark"></i>{{ __('Homework') }}
                            </button>
                        </div>
                    @endforelse
                </div>
            </article>

            <article class="ui-card p-0">
                <div class="portal-card-head is-navy">
                    <span><i class="bi bi-receipt"></i> {{ __('Fee summary') }}</span>
                    <button type="button" class="portal-card-link" data-module-target="fees">{{ __('View details') }} <i class="bi bi-arrow-right"></i></button>
                </div>
                <div class="portal-card-body">
                    <div class="fee-mini-grid">
                        <div class="fee-mini">
                            <span class="fee-mini-label">{{ __('Total fees') }}</span>
                            <span class="fee-mini-value">{{ $money((float) $feesData['summary']['total']) }}</span>
                        </div>
                        <div class="fee-mini">
                            <span class="fee-mini-label">{{ __('Paid') }}</span>
                            <span class="fee-mini-value is-success">{{ $money((float) $feesData['summary']['paid']) }}</span>
                        </div>
                        <div class="fee-mini">
                            <span class="fee-mini-label">{{ __('Due') }}</span>
                            <span class="fee-mini-value is-danger">{{ $money((float) $feesData['summary']['due']) }}</span>
                        </div>
                    </div>
                </div>
            </article>
        </div>
    </section>

    {{-- ============================== PROFILE ============================== --}}
    <section class="portal-module" data-module="profile" hidden>
        <div class="ui-card profile-summary">
            <span class="avatar profile-avatar">
                @if ($student->photo_path)
                    <img src="{{ route('student.photo', ['v' => $student->photoVersion()]) }}" alt="{{ $student->full_name }}">
                @else
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($student->first_name, 0, 1).\Illuminate\Support\Str::substr($student->last_name ?? '', 0, 1)) }}
                @endif
            </span>
            <div class="profile-summary-main">
                <h2 class="profile-summary-name">{{ $student->full_name }}</h2>
                <div class="profile-summary-meta">
                    <span class="profile-chip"><i class="bi bi-person-vcard"></i>{{ $student->student_id }}</span>
                    <span class="profile-chip is-navy"><i class="bi bi-mortarboard-fill"></i>{{ __('Class') }} {{ $enrollment?->schoolClass?->name ?? '—' }}</span>
                    <span class="profile-chip is-navy"><i class="bi bi-diagram-3-fill"></i>{{ __('Section') }} {{ $enrollment?->section?->name ?? '—' }}</span>
                    <span class="profile-chip is-navy"><i class="bi bi-list-ol"></i>{{ __('Roll') }} {{ $enrollment?->roll_no ?? '—' }}</span>
                </div>
            </div>
            <div class="profile-actions">
                <a href="{{ route('student.profile') }}" class="btn btn-light-soft btn-sm">
                    <i class="bi bi-person-badge"></i>{{ __('Full profile') }}
                </a>
            </div>
        </div>

        <div class="ui-card p-0">
            <div class="portal-card-head is-purple">
                <span><i class="bi bi-camera"></i> {{ __('Profile photo') }}</span>
                <span class="portal-card-date">{{ __('Only you and the school office can see it.') }}</span>
            </div>
            <div class="portal-card-body">
                @include('partials.profile-photo', [
                    'fullName' => $student->full_name,
                    'initials' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($student->first_name, 0, 1).\Illuminate\Support\Str::substr($student->last_name ?? '', 0, 1)),
                    'photoSrc' => $student->photo_path !== null ? route('student.photo', ['v' => $student->photoVersion()]) : null,
                    'uploadUrl' => route('student.photo.store'),
                    'removeUrl' => route('student.photo.destroy'),
                    'photoMaxKb' => $photoMaxKb,
                    'photoAccept' => $photoAccept,
                ])
            </div>
        </div>

        <div class="ui-card p-0">
            <div class="portal-card-head is-brand">
                <span><i class="bi bi-person-lines-fill"></i> {{ __('Student information') }}</span>
            </div>
            <div class="portal-card-body">
                <div class="profile-grid">
                    <div class="profile-item"><span>{{ __('Student ID') }}</span><strong>{{ $student->student_id }}</strong></div>
                    <div class="profile-item"><span>{{ __('Admission no') }}</span><strong>{{ $student->admission_no ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Class') }}</span><strong>{{ $enrollment?->schoolClass?->name ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Section') }}</span><strong>{{ $enrollment?->section?->name ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Roll') }}</span><strong>{{ $enrollment?->roll_no ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Date of birth') }}</span><strong>{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Gender') }}</span><strong>{{ $student->gender ? __(ucfirst($student->gender)) : '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Blood group') }}</span><strong>{{ $student->blood_group ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Phone') }}</span><strong>{{ $student->phone ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Email') }}</span><strong>{{ $student->email ?? '—' }}</strong></div>
                    <div class="profile-item profile-item-wide"><span>{{ __('Address') }}</span><strong>{{ $student->present_address ?? '—' }}</strong></div>
                </div>
            </div>
        </div>

        <div class="ui-card p-0">
            <div class="portal-card-head is-emerald">
                <span><i class="bi bi-people-fill"></i> {{ __('Guardian information') }}</span>
            </div>
            <div class="portal-card-body">
                @if ($student->guardians->isNotEmpty())
                    <div class="guardian-grid">
                        @foreach ($student->guardians as $guardian)
                            <div class="guardian-card">
                                <span class="guardian-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($guardian->name, 0, 1)) }}</span>
                                <div class="guardian-body">
                                    <span class="guardian-name">
                                        {{ $guardian->name }}
                                        @if ($guardian->pivot?->is_primary)<span class="badge-soft is-brand">{{ __('Primary') }}</span>@endif
                                    </span>
                                    <div class="guardian-meta">
                                        <span>{{ $guardian->relation ? __(ucfirst($guardian->relation)) : __('Guardian') }}</span>
                                        @if ($guardian->phone)<span><i class="bi bi-telephone"></i> {{ $guardian->phone }}</span>@endif
                                        @if ($guardian->email)<span><i class="bi bi-envelope"></i> {{ $guardian->email }}</span>@endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state">
                        <i class="bi bi-people empty-icon"></i>
                        <p class="mb-0">{{ __('No guardian record on file.') }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="ui-card p-0 table-card">
            <div class="portal-card-head is-navy">
                <span><i class="bi bi-clock-history"></i> {{ __('Enrollment history') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table responsive-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Session') }}</th>
                            <th>{{ __('Class') }}</th>
                            <th>{{ __('Section') }}</th>
                            <th>{{ __('Roll') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($student->enrollments as $enrollmentRow)
                            <tr>
                                <td data-label="{{ __('Session') }}">{{ $enrollmentRow->academicSession?->name ?? '—' }}</td>
                                <td data-label="{{ __('Class') }}">{{ $enrollmentRow->schoolClass?->name ?? '—' }}</td>
                                <td data-label="{{ __('Section') }}">{{ $enrollmentRow->section?->name ?? '—' }}</td>
                                <td data-label="{{ __('Roll') }}">{{ $enrollmentRow->roll_no ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state py-4">
                                        <i class="bi bi-journal-x empty-icon"></i>
                                        <p class="mb-0">{{ __('No enrollment history yet.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ============================== ATTENDANCE ============================== --}}
    <section class="portal-module" data-module="attendance" hidden>
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="ui-card h-100">
                    <h2 class="portal-section-title"><i class="bi bi-calendar2-check"></i> {{ __('This month') }}</h2>
                    @if ($monthSummary['recorded'] > 0)
                        <div class="att-donut-wrap">
                            <canvas id="attendanceDonutModule" width="180" height="180"
                                    data-values='{{ json_encode([$monthSummary['counts']['present'], $monthSummary['counts']['absent'], $monthSummary['counts']['late'], $monthSummary['counts']['leave']]) }}'></canvas>
                            <div class="att-donut-center">
                                <strong>{{ $monthSummary['percentage'] !== null ? number_format($monthSummary['percentage'], 1).'%' : '—' }}</strong>
                                <span>{{ __('Present') }}</span>
                            </div>
                        </div>
                        <ul class="att-legend">
                            @foreach (['present' => 'is-success', 'absent' => 'is-danger', 'late' => 'is-warning', 'leave' => 'is-info'] as $status => $class)
                                <li><span class="att-dot {{ $class }}"></span> {{ __(ucfirst($status)) }} <strong>{{ $monthSummary['counts'][$status] }}</strong></li>
                            @endforeach
                        </ul>
                    @else
                        <div class="empty-state py-4">
                            <i class="bi bi-calendar2-x empty-icon"></i>
                            <p class="mb-0">{{ __('No attendance recorded this month.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
            <div class="col-lg-7">
                <div class="ui-card h-100">
                    <h2 class="portal-section-title"><i class="bi bi-bar-chart"></i> {{ __('Monthly trend') }}</h2>
                    @if (collect($attendanceSeries)->contains(fn ($m) => $m['recorded'] > 0))
                        <canvas id="attendanceTrend" height="150"
                                data-labels='{{ json_encode(collect($attendanceSeries)->pluck('label')) }}'
                                data-values='{{ json_encode(collect($attendanceSeries)->map(fn ($m) => $m['percentage'])) }}'></canvas>
                    @else
                        <div class="empty-state py-4">
                            <i class="bi bi-bar-chart empty-icon"></i>
                            <p class="mb-0">{{ __('No attendance recorded yet.') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="ui-card p-0">
            <div class="portal-card-head is-brand">
                <span><i class="bi bi-list-check"></i> {{ __('Daily records') }} · {{ $attendanceMonth['month']->format('F Y') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-card mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Class') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($attendanceMonth['rows'] as $record)
                            <tr>
                                <td>{{ $record->attendance_date?->format('d M Y, l') ?? '—' }}</td>
                                <td>{{ $record->schoolClass?->name ?? '—' }}@if ($record->section) · {{ $record->section->name }} @endif</td>
                                <td><span class="badge-soft {{ $attendanceBadge[$record->status] ?? 'is-brand' }}">{{ __(ucfirst($record->status)) }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">
                                    <div class="empty-state py-4">
                                        <i class="bi bi-calendar2-x empty-icon"></i>
                                        <p class="mb-0">{{ __('No attendance records this month.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ============================== HOMEWORK ============================== --}}
    <section class="portal-module" data-module="homework" hidden>
        <div class="ui-card p-0">
            <div class="portal-card-head is-brand">
                <span><i class="bi bi-clipboard-check"></i> {{ __('Homework') }}</span>
                <select class="form-select form-select-sm w-auto" data-homework-filter aria-label="{{ __('Filter homework') }}">
                    <option value="all">{{ __('All') }}</option>
                    <option value="pending">{{ __('Pending') }}</option>
                    <option value="submitted">{{ __('Submitted') }}</option>
                </select>
            </div>
            <div class="table-responsive">
                <table class="table table-card mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Teacher') }}</th>
                            <th>{{ __('Due') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($homeworkRows as $homework)
                            @php
                                $hwStatus = $homeworkStatuses[$homework->id] ?? null;
                                $isPending = ! in_array($hwStatus, ['submitted', 'late', 'reviewed'], true);
                                $isOverdue = $isPending && $homework->due_date?->isPast();
                                $filterKey = $isPending ? 'pending' : 'submitted';
                            @endphp
                            <tr data-hw-row data-hw-status="{{ $filterKey }}">
                                <td class="fw-600">{{ $homework->title }}</td>
                                <td>{{ $homework->subject?->name ?? '—' }}</td>
                                <td>{{ $homework->teacher?->name ?? '—' }}</td>
                                <td>{{ $homework->due_date?->format('d M Y') ?? '—' }}</td>
                                <td>
                                    @if ($hwStatus === 'reviewed')
                                        <span class="badge-soft is-success">{{ __('Reviewed') }}</span>
                                    @elseif ($hwStatus === 'late')
                                        <span class="badge-soft is-warning">{{ __('Late') }}</span>
                                    @elseif ($hwStatus === 'submitted')
                                        <span class="badge-soft is-info">{{ __('Submitted') }}</span>
                                    @elseif ($isOverdue)
                                        <span class="badge-soft is-danger">{{ __('Overdue') }}</span>
                                    @else
                                        <span class="badge-soft is-warning">{{ __('Pending') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('student.homework.show', $homework) }}" class="btn btn-sm btn-light-soft">
                                        <i class="bi bi-eye"></i> {{ $isPending ? __('Submit') : __('View') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state py-5">
                                        <i class="bi bi-clipboard-check empty-icon"></i>
                                        <p class="mb-0">{{ __('No homework has been assigned yet.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ============================== CLASSWORK ============================== --}}
    <section class="portal-module" data-module="classwork" hidden>
        <div class="ui-card p-0">
            <div class="portal-card-head is-emerald">
                <span><i class="bi bi-pencil-square"></i> {{ __('Classwork') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-card mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Description') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($classworkRows as $material)
                            <tr>
                                <td class="fw-600">{{ $material->title }}</td>
                                <td>{{ $material->subject?->name ?? '—' }}</td>
                                <td>{{ $material->work_date?->format('d M Y') ?? '—' }}</td>
                                <td class="text-muted-2">{{ \Illuminate\Support\Str::limit(strip_tags((string) $material->description), 60) ?: '—' }}</td>
                                <td class="text-end">
                                    @if ($material->file_path)
                                        <a href="{{ route('student.materials.file', $material) }}" class="btn btn-sm btn-light-soft">
                                            <i class="bi bi-download"></i> {{ __('Download') }}
                                        </a>
                                    @else
                                        <span class="text-muted-2">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state py-5">
                                        <i class="bi bi-pencil-square empty-icon"></i>
                                        <p class="mb-0">{{ __('No classwork has been posted yet.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ============================== LESSONS ============================== --}}
    <section class="portal-module" data-module="lessons" hidden>
        <div class="ui-card p-0">
            <div class="portal-card-head is-brand">
                <span><i class="bi bi-play-circle"></i> {{ __('Lessons') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-card mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Teacher') }}</th>
                            <th>{{ __('Chapter') }}</th>
                            <th>{{ __('Materials') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($lessonsRows as $lesson)
                            <tr>
                                <td class="fw-600">{{ $lesson->title }}</td>
                                <td>{{ $lesson->subject?->name ?? '—' }}</td>
                                <td>{{ $lesson->teacher?->name ?? '—' }}</td>
                                <td>{{ $lesson->chapter_number ? __('Chapter :n', ['n' => $lesson->chapter_number]) : '—' }}</td>
                                <td>{{ trans_choice(':count file|:count files', $lesson->materials_count, ['count' => $lesson->materials_count]) }}</td>
                                <td class="text-end">
                                    <a href="{{ route('student.lessons.show', $lesson) }}" class="btn btn-sm btn-light-soft">
                                        <i class="bi bi-eye"></i> {{ __('View') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state py-5">
                                        <i class="bi bi-play-circle empty-icon"></i>
                                        <p class="mb-0">{{ __('No lessons published yet.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ============================== RESULTS ============================== --}}
    <section class="portal-module" data-module="results" hidden>
        <div class="ui-card p-0">
            <div class="portal-card-head is-purple">
                <span><i class="bi bi-trophy"></i> {{ __('Results') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-card mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Exam') }}</th>
                            <th>{{ __('Session') }}</th>
                            <th>{{ __('Published') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($resultsData['results'] as $result)
                            <tr>
                                <td class="fw-600">{{ $result->exam?->name ?? __('Exam') }}</td>
                                <td>{{ $result->exam?->academicSession?->name ?? '—' }}</td>
                                <td>{{ $result->published_at?->format('d M Y') ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('student.results.show', $result->exam_id) }}" class="btn btn-sm btn-light-soft">
                                        <i class="bi bi-eye"></i> {{ __('View marksheet') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state py-5">
                                        <i class="bi bi-trophy empty-icon"></i>
                                        <p class="mb-0">{{ __('No result has been published yet. Results appear here after the school office publishes them.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ============================== ROUTINE ============================== --}}
    <section class="portal-module" data-module="routine" hidden>
        <div class="row g-3">
            @foreach ($weekDays as $day)
                <div class="col-md-6 col-xl-4">
                    <div class="ui-card p-0 h-100 {{ $day['is_today'] ? 'is-today' : '' }}">
                        <div class="portal-card-head {{ $day['is_today'] ? 'is-brand' : 'is-plain' }}">
                            <span><i class="bi bi-calendar-day"></i> {{ $day['label'] }} <span class="portal-card-date">{{ $day['date']->format('d M') }}</span></span>
                            @if ($day['is_today'])<span class="badge-soft is-brand">{{ __('Today') }}</span>@endif
                        </div>
                        <div class="portal-card-body">
                            @if ($day['is_holiday'])
                                <div class="holiday-banner">
                                    <i class="bi bi-emoji-sunglasses"></i>
                                    {{ $day['holiday']?->name ?? __('Holiday') }}
                                </div>
                            @endif
                            @forelse ($day['classes'] as $class)
                                <div class="portal-list-item">
                                    <div class="portal-list-main">
                                        <span class="portal-list-title">{{ $class->subject?->name ?? __('Class') }}</span>
                                        <span class="portal-list-meta">
                                            {{ \Illuminate\Support\Carbon::parse($class->start_time)->format('h:i A') }}
                                            – {{ \Illuminate\Support\Carbon::parse($class->end_time)->format('h:i A') }}
                                            · {{ $class->teacher?->name ?? __('Teacher') }}
                                            · {{ __('Room') }} {{ $class->room ?: '—' }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                @unless ($day['is_holiday'])
                                    <div class="empty-state py-3">
                                        <p class="mb-0 small">{{ __('No classes.') }}</p>
                                    </div>
                                @endunless
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ============================== FEES ============================== --}}
    <section class="portal-module" data-module="fees" hidden>
        <div class="row g-3 stat-row">
            <div class="col-6 col-lg-4">
                <div class="stat-card is-brand">
                    <div class="stat-top"><span class="stat-label">{{ __('Total fees') }}</span><span class="stat-icon"><i class="bi bi-wallet2"></i></span></div>
                    <p class="stat-value">{{ $money((float) $feesData['summary']['total']) }}</p>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="stat-card is-emerald">
                    <div class="stat-top"><span class="stat-label">{{ __('Paid') }}</span><span class="stat-icon"><i class="bi bi-check2-circle"></i></span></div>
                    <p class="stat-value">{{ $money((float) $feesData['summary']['paid']) }}</p>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="stat-card is-amber">
                    <div class="stat-top"><span class="stat-label">{{ __('Due') }}</span><span class="stat-icon"><i class="bi bi-exclamation-circle"></i></span></div>
                    <p class="stat-value">{{ $money((float) $feesData['summary']['due']) }}</p>
                    @if ($feesData['summary']['overdue'] > 0)
                        <p class="stat-foot">{{ trans_choice(':count invoice overdue|:count invoices overdue', $feesData['summary']['overdue'], ['count' => $feesData['summary']['overdue']]) }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="ui-card p-0">
            <div class="portal-card-head is-navy">
                <span><i class="bi bi-receipt"></i> {{ __('Invoices') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-card mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Invoice') }}</th>
                            <th>{{ __('Session') }}</th>
                            <th>{{ __('Issued') }}</th>
                            <th>{{ __('Due date') }}</th>
                            <th class="text-end">{{ __('Total') }}</th>
                            <th class="text-end">{{ __('Due') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $invoiceBadges = ['paid' => 'is-success', 'partial' => 'is-warning', 'unpaid' => 'is-danger', 'overdue' => 'is-danger', 'cancelled' => 'is-purple'];
                        @endphp
                        @forelse ($feesData['invoices'] as $invoice)
                            <tr>
                                <td class="fw-600">{{ $invoice->invoice_no }}</td>
                                <td>{{ $invoice->academicSession?->name ?? '—' }}</td>
                                <td>{{ $invoice->issue_date?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td>
                                <td class="text-end">{{ $money((float) $invoice->total) }}</td>
                                <td class="text-end">{{ $money((float) $invoice->due_total) }}</td>
                                <td><span class="badge-soft {{ $invoiceBadges[$invoice->status] ?? 'is-brand' }}">{{ __(ucfirst($invoice->status)) }}</span></td>
                                <td class="text-end">
                                    <a href="{{ route('student.fees.show', $invoice) }}" class="btn btn-sm btn-light-soft">
                                        <i class="bi bi-eye"></i> {{ __('View') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state py-5">
                                        <i class="bi bi-receipt empty-icon"></i>
                                        <p class="mb-0">{{ __('No invoices yet.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ui-card p-0">
            <div class="portal-card-head is-emerald">
                <span><i class="bi bi-cash-stack"></i> {{ __('Payment history') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-card mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Receipt') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($feesData['payments'] as $payment)
                            <tr>
                                <td class="fw-600">{{ $payment->receipt?->receipt_no ?? '—' }}</td>
                                <td>{{ $payment->paid_at?->format('d M Y') ?? '—' }}</td>
                                <td class="text-end">{{ $money((float) $payment->amount) }}</td>
                                <td class="text-end">
                                    @if ($payment->receipt)
                                        <a href="{{ route('student.fees.receipt', $payment) }}" class="btn btn-sm btn-light-soft">
                                            <i class="bi bi-printer"></i> {{ __('Receipt') }}
                                        </a>
                                    @else
                                        <span class="text-muted-2">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state py-5">
                                        <i class="bi bi-cash-stack empty-icon"></i>
                                        <p class="mb-0">{{ __('No payments recorded yet.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ============================== NOTICES ============================== --}}
    <section class="portal-module" data-module="notices" hidden>
        <div class="ui-card p-0">
            <div class="portal-card-head is-emerald">
                <span><i class="bi bi-megaphone"></i> {{ __('Notices') }}</span>
            </div>
            <div class="portal-card-body">
                @forelse ($noticesList as $notice)
                    <div class="portal-list-item">
                        <div class="portal-list-main">
                            <span class="portal-list-title">
                                {{ $notice->title }}
                                @if ($notice->is_pinned)<span class="badge-soft is-brand"><i class="bi bi-pin-angle-fill"></i> {{ __('Pinned') }}</span>@endif
                                @if ($notice->published_at?->greaterThanOrEqualTo(now()->subDays(7)))<span class="badge-soft is-danger">{{ __('New') }}</span>@endif
                            </span>
                            <span class="portal-list-meta">{{ __('Published: :date', ['date' => $notice->published_at?->format('M j, Y') ?? '—']) }}</span>
                        </div>
                        <a href="{{ route('student.notices.show', $notice) }}" class="btn btn-sm btn-light-soft">
                            <i class="bi bi-eye"></i> {{ __('View') }}
                        </a>
                    </div>
                @empty
                    <div class="empty-state py-5">
                        <i class="bi bi-megaphone empty-icon"></i>
                        <p class="mb-0">{{ __('No notices have been published yet.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ============================== ONLINE CLASSES ============================== --}}
    <section class="portal-module" data-module="online-classes" hidden>
        <div class="ui-card p-0">
            <div class="portal-card-head is-navy">
                <span><i class="bi bi-camera-video"></i> {{ __('Online classes') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-card mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($onlineClasses as $video)
                            <tr>
                                <td class="fw-600">{{ $video->title }}</td>
                                <td>{{ $video->subject?->name ?? '—' }}</td>
                                <td>{{ $video->isYoutube() ? __('YouTube') : __('Recorded') }}</td>
                                <td class="text-end">
                                    @if ($video->isYoutube() && $video->youtube_url)
                                        <a href="{{ $video->youtube_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light-soft">
                                            <i class="bi bi-box-arrow-up-right"></i> {{ __('Open') }}
                                        </a>
                                    @else
                                        <span class="text-muted-2">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state py-5">
                                        <i class="bi bi-camera-video empty-icon"></i>
                                        <p class="mb-0">{{ __('No online classes have been scheduled yet.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ============================== STUDY MATERIALS ============================== --}}
    <section class="portal-module" data-module="materials" hidden>
        <div class="ui-card p-0">
            <div class="portal-card-head is-brand">
                <span><i class="bi bi-journal-text"></i> {{ __('Study materials') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-card mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($studyMaterials as $material)
                            <tr>
                                <td class="fw-600">{{ $material->title }}</td>
                                <td>{{ $material->subject?->name ?? '—' }}</td>
                                <td>{{ $material->work_date?->format('d M Y') ?? '—' }}</td>
                                <td class="text-end">
                                    @if ($material->file_path)
                                        <a href="{{ route('student.materials.file', $material) }}" class="btn btn-sm btn-light-soft">
                                            <i class="bi bi-download"></i> {{ __('Download') }}
                                        </a>
                                    @else
                                        <span class="text-muted-2">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state py-5">
                                        <i class="bi bi-journal-text empty-icon"></i>
                                        <p class="mb-0">{{ __('No study materials have been shared yet.') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ============================== NOTIFICATIONS ============================== --}}
    <section class="portal-module" data-module="notifications" hidden>
        <div class="ui-card p-0">
            <div class="portal-card-head is-navy">
                <span><i class="bi bi-bell"></i> {{ __('Notifications') }}</span>
                <a href="{{ route('notifications.index') }}" class="portal-card-link">{{ __('View all') }} <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="portal-card-body">
                @forelse ($notificationsList as $notification)
                    @php
                        $data = $notification->data ?? [];
                    @endphp
                    <div class="portal-list-item">
                        @if ($notification->read_at === null)
                            <span class="notif-unread-dot" aria-label="{{ __('Unread') }}"></span>
                        @endif
                        <span class="notif-icon"><i class="bi {{ $data['icon'] ?? 'bi-bell' }}"></i></span>
                        <div class="portal-list-main">
                            <span class="portal-list-title">{{ $data['title'] ?? __('Notification') }}</span>
                            @if (! empty($data['message']))
                                <span class="portal-list-meta">{{ $data['message'] }}</span>
                            @endif
                            <span class="portal-list-meta">{{ $notification->created_at?->diffForHumans() }}</span>
                        </div>
                    </div>
                @empty
                    <div class="empty-state py-5">
                        <i class="bi bi-bell-slash empty-icon"></i>
                        <p class="mb-0">{{ __('No notifications yet.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ============================== SETTINGS ============================== --}}
    <section class="portal-module" data-module="settings" hidden>
        <div class="settings-grid">
            <article class="ui-card p-0">
                <div class="portal-card-head is-brand">
                    <span><i class="bi bi-key-fill"></i> {{ __('Change password') }}</span>
                </div>
                <div class="portal-card-body">
                    <p class="settings-note">{{ __('Enter your current password first, then choose a new one.') }}</p>

                    <form method="POST" action="{{ route('student.password.update') }}" data-password-form
                          data-remember-module="settings" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="pw-field">
                            <label for="current_password" class="form-label">{{ __('Current password') }}</label>
                            <div class="pw-input-wrap">
                                <input id="current_password" type="password" name="current_password"
                                       class="form-control @error('current_password') is-invalid @enderror"
                                       required autocomplete="current-password">
                                <button type="button" class="pw-toggle" data-password-toggle="current_password"
                                        data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}"
                                        aria-pressed="false" aria-label="{{ __('Show password') }}"
                                        title="{{ __('Show password') }}">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            @error('current_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="pw-field">
                            <label for="password" class="form-label">{{ __('New password') }}</label>
                            <div class="pw-input-wrap">
                                <input id="password" type="password" name="password"
                                       class="form-control @error('password') is-invalid @enderror"
                                       required autocomplete="new-password" data-password-new>
                                <button type="button" class="pw-toggle" data-password-toggle="password"
                                        data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}"
                                        aria-pressed="false" aria-label="{{ __('Show password') }}"
                                        title="{{ __('Show password') }}">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <p class="pw-hint" data-password-hint>{{ __('At least 8 characters with letters and numbers.') }}</p>
                        </div>

                        <div class="pw-field">
                            <label for="password_confirmation" class="form-label">{{ __('Confirm new password') }}</label>
                            <div class="pw-input-wrap">
                                <input id="password_confirmation" type="password" name="password_confirmation"
                                       class="form-control" required autocomplete="new-password" data-password-confirm
                                       aria-describedby="passwordMismatch">
                                <button type="button" class="pw-toggle" data-password-toggle="password_confirmation"
                                        data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}"
                                        aria-pressed="false" aria-label="{{ __('Show password') }}"
                                        title="{{ __('Show password') }}">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <p class="pw-mismatch" id="passwordMismatch" data-password-mismatch hidden>{{ __('The two passwords do not match.') }}</p>
                        </div>

                        <button type="submit" class="btn btn-primary pw-submit">
                            <span class="spinner-border" role="status" aria-hidden="true"></span>
                            <span>{{ __('Change password') }}</span>
                        </button>
                    </form>

                    <p class="settings-foot">
                        <i class="bi bi-shield-lock"></i>
                        <span>{{ __('Changing your password signs you out of other devices.') }}</span>
                    </p>
                </div>
            </article>

            <article class="ui-card p-0">
                <div class="portal-card-head is-navy">
                    <span><i class="bi bi-sliders2"></i> {{ __('Preferences') }}</span>
                </div>
                <div class="portal-card-body">
                    <div class="pref-row">
                        <div class="pref-copy">
                            <span class="fw-600"><i class="bi bi-moon-stars"></i> {{ __('Theme') }}</span>
                            <p class="text-muted-2 small">{{ __('Choose how the portal looks. Saved on this device and applied instantly.') }}</p>
                        </div>
                        <div class="segment" role="group" aria-label="{{ __('Theme') }}">
                            <button type="button" class="segment-btn" data-theme-set="light">
                                <i class="bi bi-sun"></i>
                                <span>{{ __('Light') }}</span>
                            </button>
                            <button type="button" class="segment-btn" data-theme-set="dark">
                                <i class="bi bi-moon-stars"></i>
                                <span>{{ __('Dark') }}</span>
                            </button>
                        </div>
                    </div>

                    <div class="pref-row">
                        <div class="pref-copy">
                            <span class="fw-600"><i class="bi bi-translate"></i> {{ __('Language') }}</span>
                            <p class="text-muted-2 small">{{ __('Choose your language. It saves to your account as soon as you pick one.') }}</p>
                        </div>
                        <div class="segment" role="group" aria-label="{{ __('Language') }}">
                            @foreach (config('school.locales') as $localeCode)
                                <form method="POST" action="{{ route('student.locale.update') }}" data-remember-module="settings">
                                    @csrf
                                    <input type="hidden" name="locale" value="{{ $localeCode }}">
                                    <button type="submit"
                                            class="segment-btn {{ app()->getLocale() === $localeCode ? 'is-active' : '' }}"
                                            aria-pressed="{{ app()->getLocale() === $localeCode ? 'true' : 'false' }}">
                                        <i class="bi {{ $localeCode === 'bn' ? 'bi-fonts' : 'bi-type' }}"></i>
                                        <span>{{ $localeCode === 'bn' ? 'বাংলা' : 'English' }}</span>
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>

                    <p class="settings-foot">
                        <i class="bi bi-info-circle"></i>
                        <span>{{ __('Theme and language are remembered the moment you change them — there is nothing else to save.') }}</span>
                    </p>
                </div>
            </article>
        </div>
    </section>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
@endpush
