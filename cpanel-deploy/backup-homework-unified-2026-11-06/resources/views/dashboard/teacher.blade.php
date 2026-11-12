@extends('layouts.teacher')

@section('title', __('Teacher Dashboard'))

@section('content')
    @php
        $nextPeriod = $timetable['today']->first(fn ($slot): bool => \Illuminate\Support\Carbon::parse($slot->start_time)->gte(now()));

        // The 5 teacher modules, strictly ordered:
        // Attendance, Homework, Classwork, Lesson, Result Management
        $modules = collect([
            [
                'key' => 'attendance',
                'label' => __('Attendance Management'),
                'short_label' => __('Attendance'),
                'note' => __('Daily register for the classes on your timetable.'),
                'icon' => 'bi-check2-square',
                'tone' => 'is-sky',
                'url' => route('attendance.students.index', ['date' => $today->toDateString()]),
                'count' => $attendance['countable'] ? $attendance['marked'].'/'.$attendance['total'] : '—',
                'count_label' => __('marked today'),
                'can' => auth()->user()->can('attendance.view') || auth()->user()->can('attendance.mark'),
            ],
            [
                'key' => 'homework',
                'label' => __('Homework Management'),
                'short_label' => __('Homework'),
                'note' => __('Assign work, publish a draft, review student answers.'),
                'icon' => 'bi-journal-text',
                'tone' => 'is-amber',
                'url' => route('teacher.homework.index'),
                'count' => number_format($homework['pending_reviews']),
                'count_label' => trans_choice('answer to review|answers to review', $homework['pending_reviews']),
                'can' => auth()->user()->can('homework.view'),
            ],
            [
                'key' => 'classwork',
                'label' => __('Classwork Management'),
                'short_label' => __('Classwork'),
                'note' => __('Chapter work, exercises and shared study files.'),
                'icon' => 'bi-journal-bookmark',
                'tone' => 'is-teal',
                'url' => route('materials.index', ['kind' => \App\Models\StudyMaterial::CLASSWORK]),
                'count' => null,
                'count_label' => __('Classwork materials'),
                'can' => auth()->user()->can('materials.view'),
            ],
            [
                'key' => 'lessons',
                'label' => __('Lesson Management'),
                'short_label' => __('Lesson'),
                'note' => __('Write a lesson for your period, draft or publish.'),
                'icon' => 'bi-easel2',
                'tone' => 'is-navy',
                'url' => route('lessons.index'),
                'count' => number_format($lessons['draft']),
                'count_label' => trans_choice('draft waiting|drafts waiting', $lessons['draft']),
                'can' => auth()->user()->can('lessons.view'),
            ],
            [
                'key' => 'results',
                'label' => __('Result Management'),
                'short_label' => __('Result Management'),
                'note' => __('Enter or import marks and evaluate student exams.'),
                'icon' => 'bi-pencil-square',
                'tone' => 'is-purple',
                'url' => route('dashboard.results'),
                'count' => number_format($marks['papers']),
                'count_label' => trans_choice('paper to enter|papers to enter', $marks['papers']),
                'can' => auth()->user()->can('marks.enter'),
            ],
        ])->filter(fn (array $module): bool => $module['can'])->values();
    @endphp

    <!-- Dashboard Welcome Header -->
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <span class="page-eyebrow"><i class="bi bi-mortarboard me-1"></i>{{ __('Teacher Workspace') }}</span>
            <h1 class="page-title mb-1">{{ __('Teacher Dashboard') }}</h1>
            <p class="page-subtitle mb-0">
                {{ $profile?->full_name ?? auth()->user()->name }} · {{ $today->translatedFormat('l, d M Y') }}
                @if ($session)
                    <span class="text-muted-2">({{ $session->name }})</span>
                @endif
            </p>
        </div>
        <div class="page-actions d-flex align-items-center gap-2">
            <span class="badge-soft is-brand">{{ trans_choice(':count class|:count classes', $cards['classes'], ['count' => $cards['classes']]) }}</span>
            <span class="badge-soft">{{ number_format($cards['students']) }} {{ __('students') }}</span>
            @if ($nextPeriod)
                <span class="badge-soft is-info"><i class="bi bi-clock me-1"></i>{{ __('Next') }}: {{ \App\Models\ClassRoutine::timeLabel($nextPeriod->start_time) }}</span>
            @endif
        </div>
    </div>

    @if ($slots->isEmpty())
        <div class="alert alert-warning mb-4" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <div>{{ __('No class is assigned to you yet. Attendance, homework, classwork and marks all follow the periods on your timetable, so ask the administrator to add your classes and subjects there.') }}</div>
        </div>
    @endif

    <!-- Single Page Teacher Dashboard Container -->
    <div class="td" id="teacher-dashboard"
         data-td-active="{{ $activePanel }}"
         data-td-reopen="{{ $reopenUrl }}"
         data-td-panels="{{ $modules->pluck('key')->implode(' ') }}">

        <!-- Module Horizontal Tabs / Cards -->
        <section class="td-modules mb-4" aria-label="{{ __('Teacher Modules') }}">
            @foreach ($modules as $module)
                <button type="button"
                        class="td-card{{ $activePanel === $module['key'] ? ' is-active' : '' }}"
                        data-td-open="{{ $module['key'] }}"
                        data-td-url="{{ $module['url'] }}"
                        aria-label="{{ $module['label'] }}">
                    <span class="td-card-icon {{ $module['tone'] }}"><i class="bi {{ $module['icon'] }}"></i></span>
                    <span class="td-card-label">{{ $module['label'] }}</span>
                    <span class="td-card-note">{{ $module['note'] }}</span>
                    <span class="td-card-foot">
                        @if ($module['count'] !== null)
                            <strong>{{ $module['count'] }}</strong>
                        @endif
                        <span>{{ $module['count_label'] }}</span>
                    </span>
                </button>
            @endforeach
        </section>

        <!-- Dynamic Module Panels (Content switches in-place via AJAX) -->
        @foreach ($modules as $module)
            <section class="td-panel{{ $activePanel === $module['key'] ? ' is-open' : '' }}"
                     data-td-panel="{{ $module['key'] }}"
                     @if ($activePanel !== $module['key']) hidden style="display:none;" @endif
                     aria-label="{{ $module['label'] }}">
                <div class="td-panel-bar d-flex align-items-center justify-content-between p-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="td-panel-icon"><i class="bi {{ $module['icon'] }}"></i></span>
                        <h2 class="td-panel-title m-0">{{ $module['label'] }}</h2>
                    </div>
                    <div class="td-panel-tools d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-light-soft" data-td-reload title="{{ __('Reload panel') }}">
                            <i class="bi bi-arrow-clockwise me-1"></i>{{ __('Reload') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-primary-soft" data-td-close title="{{ __('Back to Dashboard overview') }}">
                            <i class="bi bi-grid me-1"></i>{{ __('Dashboard') }}
                        </button>
                    </div>
                </div>
                <div class="td-panel-body" data-td-url="{{ $module['url'] }}"></div>
            </section>
        @endforeach

        <p class="td-error" data-td-error hidden></p>
    </div>

    <!-- Dashboard Main Overview Section (Shown when no module panel is active) -->
    <div class="td-overview" id="td-overview" @if($activePanel) hidden style="display:none;" @endif>
        <div class="row g-4">
            <!-- Today's Class Routine -->
            <div class="col-lg-7">
                <section class="ui-card p-4 h-100 shadow-sm border-0">
                    <div class="card-head d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <h2 class="card-title m-0 d-flex align-items-center gap-2">
                            <i class="bi bi-calendar2-week text-primary"></i>
                            <span>{{ __("Today's Class Routine") }}</span>
                        </h2>
                        @can('timetable.view')
                            <a href="{{ route('teacher.routine') }}" class="btn btn-sm btn-light-soft">
                                <span>{{ __('Full week') }}</span>
                                <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        @endcan
                    </div>

                    @if ($timetable['today']->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-calendar-check fs-1 text-muted-2 d-block mb-2"></i>
                            <p class="mb-0">{{ __('No periods are scheduled today. Your weekly timetable still has :total periods.', ['total' => $timetable['week']->sum()]) }}</p>
                        </div>
                    @else
                        <ul class="agenda-list p-0 m-0 list-unstyled">
                            @foreach ($timetable['today'] as $slot)
                                @php
                                    $isPast = \Illuminate\Support\Carbon::parse($slot->end_time)->lt(now());
                                @endphp
                                <li class="agenda-item {{ $isPast ? 'is-muted' : '' }} d-flex align-items-center justify-content-between p-3 mb-2 rounded border">
                                    <div class="d-flex align-items-center gap-3">
                                        <span class="agenda-date text-center px-2 py-1 bg-light rounded">
                                            <strong class="d-block fs-6 text-dark">{{ \Illuminate\Support\Carbon::parse($slot->start_time)->translatedFormat('h:i') }}</strong>
                                            <span class="small text-muted">{{ \Illuminate\Support\Carbon::parse($slot->start_time)->translatedFormat('A') }}</span>
                                        </span>
                                        <div class="agenda-copy">
                                            <h4 class="fs-6 fw-bold mb-1 text-dark">{{ $slot->subject?->name ?? __('Subject') }}</h4>
                                            <div class="text-muted small">
                                                <span class="fw-medium text-secondary">{{ $slot->schoolClass?->name ?? __('Class') }}</span>
                                                @if ($slot->section) · {{ $slot->section->name }} @endif
                                                · {{ \App\Models\ClassRoutine::timeLabel($slot->start_time) }}–{{ \App\Models\ClassRoutine::timeLabel($slot->end_time) }}
                                            </div>
                                        </div>
                                    </div>
                                    @can('attendance.mark')
                                        <a class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1"
                                           href="{{ route('attendance.students.index', [
                                               'session_id' => $slot->academic_session_id,
                                               'class_id' => $slot->class_id,
                                               'section_id' => $slot->section_id,
                                               'date' => $today->toDateString(),
                                           ]) }}"
                                           data-td-open="attendance"
                                           title="{{ __('Mark attendance') }}"
                                           aria-label="{{ __('Mark attendance') }}">
                                            <i class="bi bi-check2-square"></i>
                                            <span class="d-none d-sm-inline">{{ __('Mark Attendance') }}</span>
                                        </a>
                                    @endcan
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>

            <!-- Notices & Announcements -->
            <div class="col-lg-5">
                <section class="ui-card p-4 h-100 shadow-sm border-0">
                    <div class="card-head d-flex align-items-center justify-content-between pb-3 mb-3 border-bottom">
                        <h2 class="card-title m-0 d-flex align-items-center gap-2">
                            <i class="bi bi-bell text-warning"></i>
                            <span>{{ __('School Notices') }}</span>
                        </h2>
                        @can('notices.view')
                            <a href="{{ route('notices.index') }}" class="btn btn-sm btn-light-soft">
                                <span>{{ __('All notices') }}</span>
                                <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        @endcan
                    </div>

                    @if ($notices->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 text-muted-2 d-block mb-2"></i>
                            <p class="mb-0">{{ __('No notices have been published yet.') }}</p>
                        </div>
                    @else
                        <ul class="agenda-list p-0 m-0 list-unstyled">
                            @foreach ($notices as $notice)
                                <li class="agenda-item d-flex align-items-center gap-3 p-3 mb-2 rounded border">
                                    <span class="agenda-date text-center px-2 py-1 bg-light rounded" style="min-width: 50px;">
                                        <strong class="d-block fs-6 text-dark">{{ $notice->published_at?->format('d') }}</strong>
                                        <span class="small text-muted">{{ $notice->published_at?->translatedFormat('M') }}</span>
                                    </span>
                                    <div class="agenda-copy flex-grow-1">
                                        <h4 class="fs-6 fw-bold mb-1 text-dark">{{ $notice->title }}</h4>
                                        <span class="badge bg-light text-secondary border">{{ __(ucfirst($notice->audience)) }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/teacher-dashboard.js') }}?v=2" defer></script>
@endpush