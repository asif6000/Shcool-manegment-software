@extends('layouts.app')

@section('title', __('Attendance report'))

@section('content')
    @php
        $statusLabels = [
            'present' => __('Present'),
            'absent' => __('Absent'),
            'late' => __('Late'),
            'leave' => __('On leave'),
            'excused' => __('Excused'),
        ];
        $exportQuery = array_filter([
            'session_id' => $session?->id,
            'class_id' => $class?->id,
            'section_id' => $section?->id,
            'month' => $month->format('Y-m'),
        ], fn ($value) => filled($value));
        $markedTotal = $report !== null ? array_sum($report['totals']) : 0;
        $rateTone = function (?float $rate): string {
            if ($rate === null) {
                return '';
            }

            return $rate >= 90 ? 'is-success' : ($rate >= 75 ? 'is-warning' : 'is-danger');
        };
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-clipboard-data"></i>{{ __('Daily operations') }}</span>
            <h1 class="page-title">{{ __('Attendance report') }}</h1>
            <p class="page-subtitle">{{ __('Monthly attendance per student. The rate counts present and late days against countable working days, with leave and excused days removed.') }}</p>
        </div>
        <div class="page-actions">
            @if ($class !== null)
                <a href="{{ route('attendance.students.export', $exportQuery) }}" class="btn btn-light-soft">
                    <i class="bi bi-filetype-csv"></i>{{ __('Export CSV') }}
                </a>
            @endif
            <a href="{{ route('attendance.students.index', $exportQuery) }}" class="btn btn-primary">
                <i class="bi bi-calendar2-check"></i>{{ __('Mark attendance') }}
            </a>
        </div>
    </div>

    @include('attendance.partials.tabs', ['active' => 'report'])

    <section class="ui-card p-lg mb-3">
        <div class="card-head mb-3">
            <div>
                <h2 class="card-title mb-1">{{ __('Report filters') }}</h2>
                <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Attendance is reported for active enrollments in the selected session.') }}</p>
            </div>
            <span class="badge-soft is-brand">{{ $month->translatedFormat('F Y') }}</span>
        </div>

        <form method="GET" action="{{ route('attendance.students.report') }}" class="filter-form">
            <div>
                <label class="form-label" for="report-session">{{ __('Session') }}</label>
                <select id="report-session" name="session_id" class="form-select" data-auto-submit>
                    @forelse ($sessions as $option)
                        <option value="{{ $option->id }}" @selected($session?->id === $option->id)>{{ $option->name }}</option>
                    @empty
                        <option value="">{{ __('No sessions') }}</option>
                    @endforelse
                </select>
            </div>

            <div>
                <label class="form-label" for="report-class">{{ __('Class') }}</label>
                <select id="report-class" name="class_id" class="form-select" data-auto-submit>
                    <option value="">{{ __('Select a class') }}</option>
                    @foreach ($classes as $option)
                        <option value="{{ $option->id }}" @selected($class?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label" for="report-section">{{ __('Section') }}</label>
                <select id="report-section" name="section_id" class="form-select" data-auto-submit @disabled($class === null)>
                    <option value="">{{ __('All sections') }}</option>
                    @foreach ($class?->sections ?? [] as $option)
                        <option value="{{ $option->id }}" @selected($section?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label" for="report-month">{{ __('Month') }}</label>
                <input id="report-month" type="month" name="month" class="form-control" value="{{ $month->format('Y-m') }}" data-auto-submit>
            </div>

            <div class="align-self-end d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Show') }}</button>
                @if ($class !== null || request()->filled('month'))
                    <a href="{{ route('attendance.students.report') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </div>
        </form>
    </section>

    @if ($session === null)
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-calendar3"></i></div>
                <h3>{{ __('No academic session yet') }}</h3>
                <p>{{ __('Create an academic session and enroll students before running attendance reports.') }}</p>
                <a href="{{ route('academic.index') }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i>{{ __('Open academic setup') }}</a>
            </div>
        </section>
    @elseif ($class === null)
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-bar-chart"></i></div>
                <h3>{{ __('Select a class to run the report') }}</h3>
                <p>{{ __('Pick a class and month above. The report includes every active student in the class.') }}</p>
            </div>
        </section>
    @elseif ($report === null || $report['rows']->isEmpty())
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-people"></i></div>
                <h3>{{ __('No active enrollments') }}</h3>
                <p>{{ __(':class has no active students for :session.', ['class' => $class->name, 'session' => $session->name]) }}</p>
            </div>
        </section>
    @else
        <section class="stats-grid mb-3" aria-label="{{ __('Attendance summary') }}">
            <article class="stat-card">
                <div class="stat-top">
                    <div>
                        <p class="stat-label">{{ __('Working days') }}</p>
                        <p class="stat-value">{{ number_format($report['working_days']) }}</p>
                    </div>
                    <span class="stat-icon" aria-hidden="true"><i class="bi bi-calendar2-check"></i></span>
                </div>
                <div class="stat-foot"><span>{{ __('Countable working days with a register') }}</span></div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <div>
                        <p class="stat-label">{{ __('Students') }}</p>
                        <p class="stat-value">{{ number_format($report['rows']->count()) }}</p>
                    </div>
                    <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-people"></i></span>
                </div>
                <div class="stat-foot"><span>{{ __('Active enrollments in scope') }}</span></div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <div>
                        <p class="stat-label">{{ __('Average attendance') }}</p>
                        <p class="stat-value">{{ $report['average_rate'] !== null ? number_format($report['average_rate'], 1).'%' : '—' }}</p>
                    </div>
                    <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>
                </div>
                <div class="stat-foot"><span>{{ __('Present + late against :count countable days', ['count' => number_format($markedTotal)]) }}</span></div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <div>
                        <p class="stat-label">{{ __('Absent days') }}</p>
                        <p class="stat-value">{{ number_format($report['totals']['absent']) }}</p>
                    </div>
                    <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-person-x"></i></span>
                </div>
                <div class="stat-foot"><span>{{ __('Recorded absences this month') }}</span></div>
            </article>
        </section>

        <section class="ui-card table-card">
            <div class="table-toolbar">
                <div>
                    <h2 class="card-title mb-1">{{ $class->name }}@if ($section) · {{ $section->name }}@endif</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.78rem;">{{ $month->translatedFormat('F Y') }} · {{ $session->name }}</p>
                </div>
                <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count student|:count students', $report['rows']->count(), ['count' => $report['rows']->count()]) }}</span>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Student') }}</th>
                            <th scope="col">{{ __('Section') }}</th>
                            <th scope="col">{{ __('Roll') }}</th>
                            <th scope="col" class="text-end">{{ __('Present') }}</th>
                            <th scope="col" class="text-end">{{ __('Absent') }}</th>
                            <th scope="col" class="text-end">{{ __('Late') }}</th>
                            <th scope="col" class="text-end">{{ __('Leave') }}</th>
                            <th scope="col" class="text-end">{{ __('Excused') }}</th>
                            <th scope="col" class="text-end">{{ __('Marked') }}</th>
                            <th scope="col" class="text-end">{{ __('Attendance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($report['rows'] as $row)
                            @php($enrollment = $row['enrollment'])
                            @php($student = $enrollment->student)
                            <tr>
                                <td>
                                    <div class="cell-person">
                                        <span class="avatar avatar-sm">
                                            @if ($student?->photoUrl())
                                                <img src="{{ $student->photoUrl() }}" alt="">
                                            @else
                                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($student?->first_name ?? '', 0, 1).\Illuminate\Support\Str::substr($student?->last_name ?? '', 0, 1)) }}
                                            @endif
                                        </span>
                                        <span>
                                            <span class="name">{{ $student?->full_name ?? '—' }}</span>
                                            <span class="email">{{ $student?->student_id }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>{{ $enrollment->section?->name ?? '—' }}</td>
                                <td>{{ $enrollment->roll_no ?: '—' }}</td>
                                <td class="text-end">{{ $row['counts']['present'] }}</td>
                                <td class="text-end">{{ $row['counts']['absent'] }}</td>
                                <td class="text-end">{{ $row['counts']['late'] }}</td>
                                <td class="text-end">{{ $row['counts']['leave'] }}</td>
                                <td class="text-end">{{ $row['counts']['excused'] }}</td>
                                <td class="text-end">{{ $row['marked'] }}</td>
                                <td class="text-end">
                                    @if ($row['rate'] !== null)
                                        <span class="badge-soft {{ $rateTone($row['rate']) }}">{{ number_format($row['rate'], 1) }}%</span>
                                    @else
                                        <span class="text-muted-2">{{ __('Not marked') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th scope="row" colspan="3">{{ __('Total') }}</th>
                            <th class="text-end">{{ $report['totals']['present'] }}</th>
                            <th class="text-end">{{ $report['totals']['absent'] }}</th>
                            <th class="text-end">{{ $report['totals']['late'] }}</th>
                            <th class="text-end">{{ $report['totals']['leave'] }}</th>
                            <th class="text-end">{{ $report['totals']['excused'] }}</th>
                            <th class="text-end">{{ $markedTotal }}</th>
                            <th class="text-end">
                                @if ($report['average_rate'] !== null)
                                    <span class="badge-soft {{ $rateTone($report['average_rate']) }}">{{ number_format($report['average_rate'], 1) }}%</span>
                                @else
                                    —
                                @endif
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="table-footer flex-wrap gap-2">
                <span class="text-muted-2" style="font-size:.78rem;">
                    {{ __('Attendance % = (present + late) ÷ (countable working days − leave − excused).') }}
                    @if (($report['excluded'] ?? 0) > 0)
                        · {{ trans_choice(':count weekend/holiday row is excluded|:count weekend/holiday rows are excluded', $report['excluded'], ['count' => $report['excluded']]) }}
                    @endif
                </span>
                <a href="{{ route('attendance.students.export', $exportQuery) }}" class="btn btn-sm btn-light-soft">
                    <i class="bi bi-download"></i>{{ __('Download this report') }}
                </a>
            </div>
        </section>
    @endif
@endsection
