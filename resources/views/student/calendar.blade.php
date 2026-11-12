@extends('layouts.student')

@section('title', __('Academic calendar'))

@section('content')
    @php
        $schoolWeek = array_keys(\App\Models\ClassRoutine::DAYS);
        $lead = \App\Models\ClassRoutine::dayIndexOf($month);
        $daysInMonth = (int) $month->daysInMonth;
        $previousMonth = $month->copy()->subMonthNoOverflow();
        $nextMonth = $month->copy()->addMonthNoOverflow();
        $today = now()->toDateString();
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-calendar-event"></i>{{ __('Academics') }}</span>
            <h1 class="page-title">{{ __('Academic calendar') }}</h1>
            <p class="page-subtitle">{{ __('Holidays, examinations, homework deadlines and notices in one month.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('student.routine') }}" class="btn btn-light-soft">
                <i class="bi bi-calendar2-week"></i>{{ __('Class routine') }}
            </a>
        </div>
    </div>

    <div class="ui-card table-card mb-3">
        <div class="table-toolbar">
            <div>
                <h2 class="card-title mb-1">{{ $month->translatedFormat('F Y') }}</h2>
                <p class="text-muted-2 mb-0" style="font-size:.8rem;">
                    {{ $session?->name ?? __('Session not configured') }}
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('student.calendar', ['month' => $previousMonth->format('Y-m')]) }}"
                   class="btn btn-sm btn-light-soft" aria-label="{{ __('Previous month') }}"><i class="bi bi-chevron-left"></i></a>
                <a href="{{ route('student.calendar', ['month' => now()->format('Y-m')]) }}" class="btn btn-sm btn-light-soft">{{ __('This month') }}</a>
                <a href="{{ route('student.calendar', ['month' => $nextMonth->format('Y-m')]) }}"
                   class="btn btn-sm btn-light-soft" aria-label="{{ __('Next month') }}"><i class="bi bi-chevron-right"></i></a>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <section class="ui-card p-lg">
                <div class="calendar-grid" role="list">
                    @foreach ($schoolWeek as $dayIndex)
                        <div class="calendar-weekday">{{ \App\Models\ClassRoutine::dayLabel($dayIndex) }}</div>
                    @endforeach

                    @for ($offset = 0; $offset < $lead; $offset++)
                        <div class="calendar-cell is-blank" aria-hidden="true"></div>
                    @endfor

                    @for ($day = 1; $day <= $daysInMonth; $day++)
                        @php
                            $date = $month->copy()->setDay($day);
                            $key = $date->toDateString();
                            $dayEvents = $events[$key] ?? [];
                            $attendanceStatus = $attendance[$key] ?? null;
                        @endphp
                        <div class="calendar-cell {{ $key === $today ? 'is-today' : '' }} {{ \App\Models\ClassRoutine::isWeekend($date) ? 'is-weekend' : '' }} {{ collect($dayEvents)->contains('type', 'holiday') ? 'is-holiday' : '' }}">
                            <div class="d-flex align-items-center justify-content-between gap-1">
                                <span class="calendar-day-number">{{ $day }}</span>
                                @if ($attendanceStatus !== null)
                                    <span class="calendar-attendance is-{{ $attendanceStatus }}" title="{{ __(ucfirst($attendanceStatus)) }}">
                                        {{ \Illuminate\Support\Str::of($attendanceStatus)->upper()->substr(0, 1) }}
                                    </span>
                                @endif
                            </div>

                            @if ($dayEvents !== [])
                                <div class="calendar-events">
                                    @foreach (array_slice($dayEvents, 0, 3) as $event)
                                        @php $label = $event['label'].($event['meta'] ? ' · '.$event['meta'] : ''); @endphp
                                        @if ($event['url'])
                                            <a href="{{ $event['url'] }}" class="calendar-event is-{{ $event['type'] }}"
                                               data-bs-toggle="tooltip" title="{{ $label }}">{{ $label }}</a>
                                        @else
                                            <span class="calendar-event is-{{ $event['type'] }}"
                                                  data-bs-toggle="tooltip" title="{{ $label }}">{{ $label }}</span>
                                        @endif
                                    @endforeach
                                    @if (count($dayEvents) > 3)
                                        <span class="calendar-event">+{{ count($dayEvents) - 3 }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endfor
                </div>

                <div class="chart-legend mt-3">
                    <span><span class="legend-dot" style="background:#be123c;"></span>{{ __('Holiday') }}</span>
                    <span><span class="legend-dot" style="background:var(--purple);"></span>{{ __('Examination') }}</span>
                    <span><span class="legend-dot" style="background:#b45309;"></span>{{ __('Homework due') }}</span>
                    <span><span class="legend-dot" style="background:#067a54;"></span>{{ __('Notice') }}</span>
                </div>
            </section>
        </div>

        <div class="col-xl-4">
            <section class="ui-card table-card mb-3">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Holidays') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('In :month', ['month' => $month->translatedFormat('F')]) }}</p>
                    </div>
                </div>

                @if ($holidays->isEmpty())
                    <div class="empty-state py-4">
                        <div class="empty-icon"><i class="bi bi-sun"></i></div>
                        <h3>{{ __('No holidays') }}</h3>
                        <p>{{ __('The school has not declared a holiday in this month.') }}</p>
                    </div>
                @else
                    <div class="notice-list p-3">
                        @foreach ($holidays as $holiday)
                            <div class="notice-item">
                                <span class="notice-icon"><i class="bi bi-sunrise"></i></span>
                                <div>
                                    <div class="notice-title">{{ $holiday->name }}</div>
                                    <div class="notice-meta">
                                        {{ $holiday->start_date?->format('d M') }}
                                        @if ($holiday->end_date && $holiday->end_date->ne($holiday->start_date))
                                            – {{ $holiday->end_date->format('d M Y') }}
                                        @endif
                                        @if ($holiday->type) · {{ __(ucfirst($holiday->type)) }} @endif
                                    </div>
                                    @if ($holiday->description)
                                        <p class="notice-body">{{ $holiday->description }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="ui-card table-card mb-3">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Examinations') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Published papers only.') }}</p>
                    </div>
                </div>

                @if ($exams->isEmpty())
                    <div class="empty-state py-4">
                        <div class="empty-icon"><i class="bi bi-journal-x"></i></div>
                        <h3>{{ __('No exams this month') }}</h3>
                        <p>{{ __('Your examination dates appear here once published.') }}</p>
                    </div>
                @else
                    <div class="notice-list p-3">
                        @foreach ($exams as $exam)
                            <a href="{{ route('student.results.index') }}" class="notice-item text-decoration-none">
                                <span class="notice-icon"><i class="bi bi-journal-check"></i></span>
                                <div>
                                    <div class="notice-title">{{ $exam->name }}</div>
                                    <div class="notice-meta">
                                        {{ $exam->start_date?->format('d M Y') }} – {{ $exam->end_date?->format('d M Y') }}
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Attendance legend') }}</h2>
                <p class="text-muted-2" style="font-size:.78rem;">
                    {{ __('The letter on a day is the status your teacher marked: P present, A absent, L late, V leave, E excused.') }}
                </p>
                @if ($leaves->isNotEmpty())
                    <div class="detail-list mt-2 mb-0">
                        @foreach ($leaves as $leave)
                            <div>
                                <dt>{{ $leave->from_date?->format('d M') }}@if ($leave->to_date?->ne($leave->from_date)) – {{ $leave->to_date?->format('d M') }}@endif</dt>
                                <dd>
                                    <span class="badge-soft {{ $leave->status === 'approved' ? 'is-success' : ($leave->status === 'rejected' ? 'is-danger' : 'is-warning') }}">
                                        {{ __(ucfirst($leave->status)) }}
                                    </span>
                                </dd>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
