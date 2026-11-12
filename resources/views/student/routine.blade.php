@extends('layouts.student')

@section('title', __('Class routine'))

@section('content')
    @php
        $weekStart = $days[0]['date'];
        $weekEnd = $days[6]['date'];
        $hasAnyClass = collect($days)->contains(fn (array $day): bool => $day['classes']->isNotEmpty());
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-calendar2-week"></i>{{ __('Academics') }}</span>
            <h1 class="page-title">{{ __('Class routine') }}</h1>
            <p class="page-subtitle">
                @if ($enrollment)
                    {{ $enrollment->schoolClass?->name }} · {{ __('Section') }} {{ $enrollment->section?->name }}
                    · {{ $session?->name ?? __('Session not configured') }}
                @else
                    {{ __('Your class has not been recorded for this session yet.') }}
                @endif
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('student.calendar') }}" class="btn btn-light-soft">
                <i class="bi bi-calendar-event"></i>{{ __('Academic calendar') }}
            </a>
            <a href="{{ route('student.dashboard') }}" class="btn btn-light-soft">
                <i class="bi bi-house"></i>{{ __('Overview') }}
            </a>
        </div>
    </div>

    <div class="ui-card table-card mb-3">
        <div class="table-toolbar">
            <div>
                <h2 class="card-title mb-1">{{ $weekStart->translatedFormat('j F Y') }} – {{ $weekEnd->translatedFormat('j F Y') }}</h2>
                <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Your lessons for the week, Saturday to Friday.') }}</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('student.routine', ['date' => $weekStart->copy()->subWeek()->toDateString()]) }}"
                   class="btn btn-sm btn-light-soft" aria-label="{{ __('Previous week') }}">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <a href="{{ route('student.routine') }}" class="btn btn-sm btn-light-soft">{{ __('This week') }}</a>
                <a href="{{ route('student.routine', ['date' => $weekStart->copy()->addWeek()->toDateString()]) }}"
                   class="btn btn-sm btn-light-soft" aria-label="{{ __('Next week') }}">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>

    @if ($enrollment === null)
        <section class="ui-card p-lg">
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-person-x"></i></div>
                <h3>{{ __('No routine yet') }}</h3>
                <p>{{ __('The school office records your class and section before the timetable opens. Please contact them if this looks wrong.') }}</p>
            </div>
        </section>
    @elseif (! $hasAnyClass)
        <section class="ui-card p-lg">
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-calendar2-x"></i></div>
                <h3>{{ __('No classes scheduled') }}</h3>
                <p>{{ __('Your class timetable has not been published for this week yet. Holidays and exams are shown on the academic calendar.') }}</p>
                <a href="{{ route('student.calendar') }}" class="btn btn-primary btn-sm mt-2">{{ __('Open the calendar') }}</a>
            </div>
        </section>
    @else
        <div class="timetable-week">
            @foreach ($days as $day)
                <section class="timetable-day {{ $day['is_today'] ? 'is-today' : '' }} {{ $day['is_holiday'] ? 'is-holiday' : '' }}">
                    <div class="timetable-day-head">
                        <span class="day-name">{{ $day['label'] }}</span>
                        <span class="day-date">{{ $day['date']->translatedFormat('j M') }}</span>
                    </div>
                    <div class="timetable-day-body">
                        @if ($day['is_holiday'])
                            <span class="badge-soft is-danger"><span class="dot"></span>{{ $day['holiday']->name }}</span>
                        @endif

                        @forelse ($day['classes'] as $slot)
                            <div class="portal-slot">
                                <span class="portal-slot-time">{{ \App\Models\ClassRoutine::timeLabel($slot->start_time) }}</span>
                                <div>
                                    <span class="portal-slot-title">{{ $slot->subject?->name ?? __('Subject') }}</span>
                                    <span class="portal-slot-meta">
                                        {{ \App\Models\ClassRoutine::timeLabel($slot->end_time) }}
                                        @if ($slot->teacher) · {{ $slot->teacher->full_name }} @endif
                                    </span>
                                </div>
                                <span class="portal-room"><i class="bi bi-geo-alt"></i>{{ $slot->room ?: '—' }}</span>
                            </div>
                        @empty
                            @if (! $day['is_holiday'])
                                <span class="timetable-empty-day">{{ __('No classes') }}</span>
                            @endif
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    @endif
@endsection
