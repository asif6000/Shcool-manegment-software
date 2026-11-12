@extends(auth()->user()?->hasRole('teacher') && ! auth()->user()?->hasAnyRole(['school_admin', 'principal', 'super_admin']) ? 'layouts.teacher' : 'layouts.app')

@section('title', __('My classes'))

@section('content')
    @php
        $subjectCount = $classes->flatMap(fn (array $row): \Illuminate\Support\Collection => $row['subjects'])->unique('id')->count();
        $sectionCount = $classes->count();
        $classCount = $classes->pluck('class')->filter()->pluck('id')->unique()->count();
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-diagram-3"></i>{{ __('Teaching') }}</span>
            <h1 class="page-title">{{ $isOwn ? __('My classes') : __('Classes on the grid') }}</h1>
            <p class="page-subtitle">
                {{ $session?->name ?? __('No academic session is open') }}
                · {{ $isOwn
                    ? __('These are the sections your timetable names you for. Homework, marks and attendance follow the same list.')
                    : __('Every section that holds a period this session, with the teachers assigned to it.') }}
            </p>
        </div>
        <div class="page-actions">
            @can('homework.manage')
                <a href="{{ route('teacher.homework.create') }}" class="btn btn-primary">
                    <i class="bi bi-journal-plus"></i>{{ __('Assign homework') }}
                </a>
            @endcan
            <a href="{{ route('teacher.routine') }}" class="btn btn-light-soft">
                <i class="bi bi-calendar2-week"></i>{{ __('My routine') }}
            </a>
        </div>
    </div>

    <div class="stats-grid mb-3">
        <article class="stat-card">
            <div class="stat-top">
                <span class="stat-label">{{ $isOwn ? __('My classes') : __('Classes') }}</span>
                <span class="stat-icon is-teal"><i class="bi bi-collection"></i></span>
            </div>
            <p class="stat-value">{{ $classCount }}</p>
            <span class="stat-foot">{{ trans_choice(':count section|:count sections', $sectionCount, ['count' => $sectionCount]) }}</span>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <span class="stat-label">{{ __('Subjects') }}</span>
                <span class="stat-icon is-purple"><i class="bi bi-book"></i></span>
            </div>
            <p class="stat-value">{{ $subjectCount }}</p>
            <span class="stat-foot">{{ trans_choice(':count period a week|:count periods a week', $periodCount, ['count' => $periodCount]) }}</span>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <span class="stat-label">{{ __('Students on my roll') }}</span>
                <span class="stat-icon is-amber"><i class="bi bi-people"></i></span>
            </div>
            <p class="stat-value">{{ $studentCount }}</p>
            <span class="stat-foot">{{ __('Active enrollments in these sections') }}</span>
        </article>
    </div>

    @if ($session === null)
        <section class="ui-card p-lg">
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-calendar2-x"></i></div>
                <h3>{{ __('No session is open') }}</h3>
                <p>{{ __('An academic session has to be created before classes can be scheduled.') }}</p>
            </div>
        </section>
    @elseif ($classes->isEmpty())
        <section class="ui-card p-lg">
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-person-question"></i></div>
                <h3>{{ __('No classes assigned') }}</h3>
                <p>{{ __('The timetable has not named you for any period this session, so there are no classes, students or marks to open. The office keeps the grid under Class routine.') }}</p>
                @if (auth()->user()?->can('timetable.view'))
                    <a href="{{ route('timetable.index') }}" class="btn btn-primary btn-sm mt-2">{{ __('Open the grid') }}</a>
                @endif
            </div>
        </section>
    @else
        <div class="timetable-week mb-3">
            @foreach ($classes as $row)
                <section class="timetable-day">
                    <div class="timetable-day-head">
                        <span class="day-name">{{ $row['class']->name ?? __('Class') }}</span>
                        <span class="day-date">{{ __('Section') }} {{ $row['section']->name ?? '—' }}</span>
                    </div>
                    <div class="timetable-day-body">
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            @foreach ($row['subjects'] as $subject)
                                <span class="chip">{{ $subject->name }}</span>
                            @endforeach
                        </div>
                        <dl class="detail-list">
                            <div><dt>{{ __('Students') }}</dt><dd>{{ $row['students'] }}</dd></div>
                            <div><dt>{{ __('Periods a week') }}</dt><dd>{{ $row['periods'] }}</dd></div>
                            <div><dt>{{ __('Teaching days') }}</dt><dd>{{ $row['days'] }}</dd></div>
                        </dl>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            @can('students.view')
                                <a href="{{ route('students.index', ['class_id' => $row['class']->id ?? '', 'section_id' => $row['section']->id ?? '']) }}"
                                   class="btn btn-sm btn-light-soft">
                                    <i class="bi bi-people"></i>{{ __('Roll list') }}
                                </a>
                            @endcan
                            @can('attendance.view')
                                <a href="{{ route('attendance.students.index', ['class_id' => $row['class']->id ?? '', 'section_id' => $row['section']->id ?? '']) }}"
                                   class="btn btn-sm btn-light-soft">
                                    <i class="bi bi-check2-square"></i>{{ __('Attendance') }}
                                </a>
                            @endcan
                            @canany(['homework.view', 'lessons.view'])
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="bi bi-journal-plus"></i>{{ __('Assign Work') }}
                                    </button>
                                    <ul class="dropdown-menu">
                                        @can('homework.view')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('teacher.homework.index', ['class' => $row['class']->id ?? '', 'section' => $row['section']->id ?? '']) }}">
                                                    <i class="bi bi-journal-text me-2"></i>{{ __('Homework') }}
                                                </a>
                                            </li>
                                        @endcan
                                        @can('homework.manage')
                                            <li>
                                                <a class="dropdown-item" href="{{ route('teacher.homework.create') }}">
                                                    <i class="bi bi-journal-plus me-2"></i>{{ __('Class Work + Homework') }}
                                                </a>
                                            </li>
                                        @endcan
                                    </ul>
                                </div>
                            @endcanany
                        </div>
                    </div>
                </section>
            @endforeach
        </div>

        <section class="ui-card table-card">
            <div class="table-toolbar">
                <div>
                    <h2 class="card-title mb-1">{{ __('Subjects I take') }}</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Each row adds up the periods the grid gives you for that subject.') }}</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Classes') }}</th>
                            <th class="text-end">{{ __('Periods a week') }}</th>
                            <th class="text-end">{{ __('Teaching hours') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subjects as $row)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $row['subject']->name ?? __('Subject') }}</span>
                                    @if ($row['subject']->type !== null)
                                        <span class="d-block text-muted-2" style="font-size:.72rem;">{{ ucfirst((string) $row['subject']->type) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach ($row['groups'] as $group)
                                            <span class="chip">{{ $group }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="text-end">{{ $row['periods'] }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($row['minutes'] / 60, 1), '0'), '.') }} {{ __('h') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
