@extends(auth()->user()?->hasRole('teacher') && ! auth()->user()?->hasAnyRole(['school_admin', 'principal', 'super_admin']) ? 'layouts.teacher' : 'layouts.app')

@section('title', __('My routine'))

@section('content')
    @php
        $weekHref = fn (string $date): string => route('teacher.routine', ['mode' => 'week', 'date' => $date]);
        $dayHref = fn (string $date): string => route('teacher.routine', ['mode' => 'day', 'date' => $date]);
        $steps = $mode === 'day' ? 1 : 7;
        $back = $mode === 'day' ? $dayHref : $weekHref;
        $periodRow = function (array $day, \App\Models\ClassRoutine $slot): string {
            return route('attendance.students.index', [
                'date' => $day['date']->toDateString(),
                'class_id' => $slot->class_id,
                'section_id' => $slot->section_id,
            ]);
        };
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-calendar2-week"></i>{{ __('Teaching') }}</span>
            <h1 class="page-title">{{ $isOwn ? __('My routine') : __('School routine') }}</h1>
            <p class="page-subtitle">
                {{ $session?->name ?? __('No academic session is open') }}
                · {{ $isOwn ? __('The periods the timetable gives you.') : __('Every period on the grid for this session.') }}
            </p>
        </div>
        <div class="page-actions">
            <div class="btn-group" role="group" aria-label="{{ __('Switch routine view') }}">
                <a href="{{ $weekHref($date->toDateString()) }}"
                   class="btn btn-sm {{ $mode === 'week' ? 'btn-primary' : 'btn-light-soft' }}">{{ __('Week') }}</a>
                <a href="{{ $dayHref($date->toDateString()) }}"
                   class="btn btn-sm {{ $mode === 'day' ? 'btn-primary' : 'btn-light-soft' }}">{{ __('Day') }}</a>
            </div>
            @can('timetable.manage')
                <a href="{{ route('timetable.index') }}" class="btn btn-light-soft">
                    <i class="bi bi-grid-3x3-gap"></i>{{ __('Edit the grid') }}
                </a>
            @endcan
            <a href="{{ route('teacher.classes') }}" class="btn btn-light-soft">
                <i class="bi bi-diagram-3"></i>{{ __('My classes') }}
            </a>
        </div>
    </div>

    <section class="ui-card table-card mb-3">
        <div class="table-toolbar">
            <div>
                <h2 class="card-title mb-1">
                    @if ($mode === 'day')
                        {{ $date->translatedFormat('l, j F Y') }}
                    @else
                        {{ $weekStart->translatedFormat('j F Y') }} – {{ $weekEnd->translatedFormat('j F Y') }}
                    @endif
                </h2>
                <p class="text-muted-2 mb-0" style="font-size:.8rem;">
                    @if ($mode === 'day')
                        {{ trans_choice('One period.|:count periods in this day.', $periods->count(), ['count' => $periods->count()]) }}
                    @else
                        {{ trans_choice('One period across the week.|:count periods across the week.', $periodCount, ['count' => $periodCount]) }}
                        · {{ trans_choice(':count subject|:count subjects', $subjectCount, ['count' => $subjectCount]) }}
                    @endif
                </p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ $back($date->copy()->subDays($steps)->toDateString()) }}"
                   class="btn btn-sm btn-light-soft" aria-label="{{ __('Previous') . ' ' . ($mode === 'day' ? __('day') : __('week')) }}">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <a href="{{ $mode === 'day' ? route('teacher.routine', ['mode' => 'day']) : route('teacher.routine') }}"
                   class="btn btn-sm btn-light-soft">{{ $mode === 'day' ? __('Today') : __('This week') }}</a>
                <a href="{{ $back($date->copy()->addDays($steps)->toDateString()) }}"
                   class="btn btn-sm btn-light-soft" aria-label="{{ __('Next') . ' ' . ($mode === 'day' ? __('day') : __('week')) }}">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </section>

    @if ($session === null)
        <section class="ui-card p-lg">
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-calendar2-x"></i></div>
                <h3>{{ __('No session is open') }}</h3>
                <p>{{ __('The office creates the academic session before any period can be placed on the timetable.') }}</p>
            </div>
        </section>
    @elseif ($mode === 'week')
        @php $hasAnyClass = collect($days)->contains(fn (array $day): bool => $day['classes']->isNotEmpty()); @endphp

        @if (! $hasAnyClass)
            <section class="ui-card p-lg">
                <div class="empty-state py-4">
                    <div class="empty-icon"><i class="bi bi-clock-history"></i></div>
                    <h3>{{ __('Nothing on the timetable') }}</h3>
                    <p>
                        {{ $isOwn
                            ? __('No period names you for this session yet. Your classes, homework and marks all follow the grid, so ask the office to add your periods.')
                            : __('No periods have been placed on the grid for this session.') }}
                    </p>
                    @if (auth()->user()?->can('timetable.manage'))
                        <a href="{{ route('timetable.index') }}" class="btn btn-primary btn-sm mt-2">{{ __('Open the grid') }}</a>
                    @endif
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
                                <a href="{{ $periodRow($day, $slot) }}" class="portal-slot text-decoration-none">
                                    <span class="portal-slot-time">{{ \App\Models\ClassRoutine::timeLabel($slot->start_time) }}</span>
                                    <div>
                                        <span class="portal-slot-title">{{ $slot->subject?->name ?? __('Subject') }}</span>
                                        <span class="portal-slot-meta">
                                            {{ $slot->schoolClass?->name }} · {{ __('Section') }} {{ $slot->section?->name }}
                                            @if (! $isOwn && $slot->teacher) · {{ $slot->teacher->full_name }} @endif
                                        </span>
                                        <span class="portal-slot-meta">
                                            {{ \App\Models\ClassRoutine::timeLabel($slot->end_time) }}
                                            · <i class="bi bi-geo-alt"></i>{{ $slot->room ?: '—' }}
                                        </span>
                                    </div>
                                </a>
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
    @else
        <section class="ui-card p-lg">
            <div class="card-head mb-3">
                <div>
                    <h2 class="card-title">{{ $date->translatedFormat('l') }}</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.78rem;">
                        @if ($holiday !== null)
                            <span class="badge-soft is-danger"><span class="dot"></span>{{ $holiday->name }}</span>
                        @elseif ($isWeekend)
                            {{ __('The school is closed on this day.') }}
                        @else
                            {{ __('Periods run in the order the grid lists them.') }}
                        @endif
                    </p>
                </div>
                <a href="{{ $weekHref($date->toDateString()) }}" class="btn btn-sm btn-light-soft">
                    <i class="bi bi-calendar2-week"></i>{{ __('See the week') }}
                </a>
            </div>

            @forelse ($periods as $row)
                @php $slot = $row['period']; @endphp
                <div class="portal-slot mb-2">
                    <span class="portal-slot-time">{{ \App\Models\ClassRoutine::timeLabel($slot->start_time) }}</span>
                    <div>
                        <span class="portal-slot-title">
                            {{ $slot->subject?->name ?? __('Subject') }}
                            @if ($row['state'] === 'now')
                                <span class="badge-soft is-success ms-1"><span class="dot"></span>{{ __('Running now') }}</span>
                            @endif
                        </span>
                        <span class="portal-slot-meta">
                            {{ $slot->schoolClass?->name }} · {{ __('Section') }} {{ $slot->section?->name }}
                            · {{ \App\Models\ClassRoutine::timeLabel($slot->start_time) }} – {{ \App\Models\ClassRoutine::timeLabel($slot->end_time) }}
                            · <i class="bi bi-geo-alt"></i>{{ $slot->room ?: '—' }}
                            @if (! $isOwn && $slot->teacher) · {{ $slot->teacher->full_name }} @endif
                        </span>
                    </div>
                    <span class="d-flex align-items-center gap-2">
                        @if ($row['state'] === 'past')
                            <span class="badge-soft"><span class="dot"></span>{{ __('Over') }}</span>
                        @endif
                        <a href="{{ route('attendance.students.index', [
                                'date' => $date->toDateString(),
                                'class_id' => $slot->class_id,
                                'section_id' => $slot->section_id,
                            ]) }}" class="btn btn-sm btn-light-soft">
                            <i class="bi bi-check2-square"></i>{{ __('Attendance') }}
                        </a>
                    </span>
                </div>
            @empty
                <div class="empty-state py-4">
                    <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                    <h3>{{ __('No period today') }}</h3>
                    <p>{{ $isOwn
                        ? __('Nothing is booked for you on this day. Use the arrows to move through the week.')
                        : __('The grid has no periods for this day of the week.') }}</p>
                </div>
            @endforelse
        </section>
    @endif
@endsection
