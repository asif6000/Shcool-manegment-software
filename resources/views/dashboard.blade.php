@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
    @php
        $user = auth()->user();
        $kpiCount = 2
            + ($attendance ? 1 : 0)
            + ($admissions ? 1 : 0)
            + ($fees ? 2 : 0);
        $chartCount = ($fees ? 1 : 0) + ($attendance ? 1 : 0) + ($user->can('students.view') ? 1 : 0);
        $listsCount = ($attendance ? 1 : 0) + ($fees ? 1 : 0) + ($schedule !== null ? 1 : 0) + ($notices ? 1 : 0);
        $eventIcons = [
            'login' => 'bi-box-arrow-in-right',
            'logout' => 'bi-box-arrow-right',
            'login_failed' => 'bi-shield-exclamation',
            'user.created' => 'bi-person-plus',
            'user.updated' => 'bi-pencil-square',
            'user.deleted' => 'bi-person-dash',
            'user.status_changed' => 'bi-person-check',
            'role.permissions_updated' => 'bi-shield-lock',
            'school.updated' => 'bi-building',
            'password_changed' => 'bi-key',
            'password_reset' => 'bi-key',
            'password_reset_requested' => 'bi-envelope-check',
        ];
        $percentLabel = fn (?float $percent): string => $percent === null ? '—' : number_format($percent, 0).'%';
    @endphp

    <div class="dash-head">
        <nav class="dash-crumbs" aria-label="{{ __('Breadcrumb') }}">
            <span class="is-current">{{ __('Dashboard') }}</span>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
            <a href="{{ route('home') }}">{{ __('Home') }}</a>
        </nav>
        @if ($session)
            <span class="dash-session">
                <i class="bi bi-calendar2-week"></i>
                <strong>{{ $session->name }}</strong>
                <span class="text-muted-2">{{ __('Current academic year') }}</span>
            </span>
        @endif
    </div>

    <section class="kpi-grid" style="--kpi-cols: {{ $kpiCount }};" aria-label="{{ __('Key statistics') }}">
        <x-stat-card
            :label="__('Total Students')"
            :formatted="number_format($stats['students'])"
            icon="bi-mortarboard"
            tone="brand"
            :trend="$growth['students']"
            :trend-label="__('vs last month')"
        />
        <x-stat-card
            :label="__('Teachers & Staff')"
            :formatted="number_format($stats['team'])"
            icon="bi-person-workspace"
            tone="teal"
            :trend="$growth['team']"
            :trend-label="__('vs last month')"
        />

        @if ($attendance)
            <x-stat-card
                :label="__('Today Attendance')"
                :formatted="$percentLabel($attendance['percent'])"
                icon="bi-clipboard2-check"
                tone="amber"
                :trend="$attendance['growth']"
                :trend-label="$attendance['percent'] === null ? __('Attendance not marked yet today') : __('vs yesterday')"
            />
        @endif

        @if ($fees)
            <x-stat-card
                :label="__('Monthly Fee Collection')"
                :formatted="Money::format($fees['collected'])"
                icon="bi-cash-stack"
                tone="brand"
                :trend="$fees['growth']"
                :trend-label="__('vs last month')"
            />
            <x-stat-card
                :label="__('Outstanding Fees')"
                :formatted="Money::format($fees['outstanding'])"
                icon="bi-receipt-cutoff"
                tone="danger"
                :trend="$fees['outstandingGrowth']"
                :trend-label="__('vs last month')"
            />
        @endif

        @if ($admissions)
            <x-stat-card
                :label="__('Pending Admissions')"
                :formatted="number_format($admissions['pending'])"
                icon="bi-person-lines-fill"
                tone="purple"
                :trend="$admissions['growth']"
                :trend-label="__('vs last month')"
            />
        @endif
    </section>

    @if ($collection)
        <section class="ui-card p-lg mb-3" aria-label="{{ __('Collection summary') }}">
            <div class="card-head">
                <h2 class="card-title">{{ __('Collection summary') }}</h2>
                <a href="{{ route('accountants.index') }}" class="card-link">{{ __('Accountant list') }}</a>
            </div>
            <div class="mini-stats">
                <div class="mini-stat">
                    <span class="value">{{ Money::format($collection['total']) }}</span>
                    <span class="label">{{ __('Total collected') }}</span>
                </div>
                <div class="mini-stat">
                    <span class="value">{{ Money::format($collection['today']) }}</span>
                    <span class="label">{{ __('Today') }}</span>
                </div>
                <div class="mini-stat">
                    <span class="value">{{ Money::format($collection['month']) }}</span>
                    <span class="label">{{ __('This month') }}</span>
                </div>
                <div class="mini-stat">
                    <span class="value">{{ Money::format($collection['year']) }}</span>
                    <span class="label">{{ __('This year') }}</span>
                </div>
                <div class="mini-stat">
                    <span class="value">{{ Money::format($collection['due']) }}</span>
                    <span class="label">{{ __('Total due on open invoices') }}</span>
                </div>
                <div class="mini-stat">
                    <span class="value">{{ number_format($collection['transactions']) }}</span>
                    <span class="label">{{ __('Total transactions') }}</span>
                </div>
                <div class="mini-stat">
                    <span class="value">{{ number_format($collection['active_accountants']) }}</span>
                    <span class="label">{{ __('Active accountants') }}</span>
                </div>
            </div>
            <p class="dash-note mt-2 mb-0">{{ __('Posted receipts only. A pending, held, cancelled or reversed receipt is not counted as collected, and the due figure is the unpaid total on invoices still open — it is not carried between reports.') }}</p>
        </section>
    @endif

    @if ($fees)
        <section class="ui-card p-lg mb-3" aria-label="{{ __('Invoice summary') }}">
            <div class="card-head">
                <h2 class="card-title">{{ __('Invoice summary') }}</h2>
                <a href="{{ route('fees.index') }}" class="card-link">{{ __('All invoices') }}</a>
            </div>
            <div class="mini-stats">
                <div class="mini-stat">
                    <span class="value">{{ number_format($fees['invoices']['count']) }}</span>
                    <span class="label">{{ __('Total invoices') }}</span>
                </div>
                <div class="mini-stat">
                    <span class="value">{{ number_format($fees['invoices']['unpaid']) }}</span>
                    <span class="label">{{ __('Unpaid invoices') }}</span>
                </div>
                <div class="mini-stat">
                    <span class="value">{{ number_format($fees['invoices']['partial']) }}</span>
                    <span class="label">{{ __('Partially paid invoices') }}</span>
                </div>
                <div class="mini-stat">
                    <span class="value">{{ number_format($fees['invoices']['paid']) }}</span>
                    <span class="label">{{ __('Paid invoices') }}</span>
                </div>
                <div class="mini-stat">
                    <span class="value">{{ Money::format($fees['invoices']['invoiced']) }}</span>
                    <span class="label">{{ __('Total invoiced amount') }}</span>
                </div>
                <div class="mini-stat">
                    <span class="value">{{ Money::format($fees['invoices']['collected']) }}</span>
                    <span class="label">{{ __('Total collected amount') }}</span>
                </div>
            </div>
            <p class="dash-note mt-2 mb-0">{{ __('Cancelled invoices are excluded. Paid invoices include settlements, so both figures follow the money actually attached to the bills.') }}</p>
        </section>
    @endif

    @if ($chartCount > 0)
        <div class="dash-row {{ $chartCount === 3 ? 'dash-row-3' : 'dash-row-3 is-'.$chartCount }}">
            @if ($fees)
                <section class="ui-card p-lg dash-card">
                    <div class="card-head">
                        <h2 class="card-title">{{ __('Fee Collection vs Expenses') }}</h2>
                        <span class="dash-pill">{{ __('This Year') }}</span>
                    </div>
                    <div class="chart-legend mb-2">
                        <span><span class="legend-dot fees"></span>{{ __('Fee Collection (BDT)') }}</span>
                        <span><span class="legend-dot expenses"></span>{{ __('Expenses (BDT)') }}</span>
                    </div>
                    <div class="chart-wrap dash-chart">
                        <canvas id="feeYearChart" aria-label="{{ __('Fee collection and expenses by month') }}" role="img"></canvas>
                    </div>
                    @if (array_sum($fees['monthExpense']) === 0.0)
                        <p class="dash-note">{{ __('Expense entries will appear here once they are recorded in the accounts module.') }}</p>
                    @endif
                </section>
            @endif

            @if ($attendance)
                <section class="ui-card p-lg dash-card">
                    <div class="card-head">
                        <h2 class="card-title">{{ __('Attendance Overview') }}</h2>
                        @if ($attendance_approvals !== null && $attendance_approvals > 0)
                            <a href="{{ route('attendance.submissions.index', ['status' => 'submitted']) }}" class="badge-soft is-warning text-decoration-none">
                                <i class="bi bi-clipboard-check"></i>{{ trans_choice(':count register awaiting review|:count registers awaiting review', $attendance_approvals, ['count' => $attendance_approvals]) }}
                            </a>
                        @endif
                        <span class="dash-pill">{{ __('Today') }}</span>
                    </div>
                    @if ($attendance['percent'] === null)
                        <p class="dash-empty">{{ __('Attendance not marked yet today') }}</p>
                    @else
                        <div class="donut-body">
                            <div class="donut-wrap">
                                <canvas id="attendanceChart" aria-label="{{ __('Attendance split for today') }}" role="img"></canvas>
                                <div class="donut-center" aria-hidden="true">
                                    <strong>{{ $percentLabel($attendance['percent']) }}</strong>
                                    <span>{{ __('Present') }}</span>
                                </div>
                            </div>
                            <ul class="donut-legend">
                                <li>
                                    <span class="donut-label"><span class="legend-dot present"></span>{{ __('Present') }}</span>
                                    <strong>{{ number_format($attendance['present']) }} ({{ $percentLabel($attendance['percent']) }})</strong>
                                </li>
                                <li>
                                    <span class="donut-label"><span class="legend-dot absent"></span>{{ __('Absent') }}</span>
                                    <strong>{{ number_format($attendance['absent']) }} ({{ $percentLabel($attendance['marked'] > 0 ? $attendance['absent'] / $attendance['marked'] * 100 : null) }})</strong>
                                </li>
                                <li>
                                    <span class="donut-label"><span class="legend-dot leave"></span>{{ __('Leave') }}</span>
                                    <strong>{{ number_format($attendance['leave']) }} ({{ $percentLabel($attendance['marked'] > 0 ? $attendance['leave'] / $attendance['marked'] * 100 : null) }})</strong>
                                </li>
                            </ul>
                        </div>
                    @endif
                </section>
            @endif

            @if ($user->can('students.view'))
                <section class="ui-card p-lg dash-card">
                    <div class="card-head">
                        <h2 class="card-title">{{ __('Enrollment Trend') }}</h2>
                        <span class="dash-pill">{{ __('This Academic Year') }}</span>
                    </div>
                    <div class="chart-wrap dash-chart">
                        <canvas id="enrollmentChart" aria-label="{{ __('Monthly enrollment chart') }}" role="img"></canvas>
                    </div>
                </section>
            @endif
        </div>
    @endif

    <div class="dash-row {{ $fees ? 'dash-row-2' : '' }}">
        @if ($fees)
            <section class="ui-card p-lg dash-card">
                <div class="card-head">
                    <h2 class="card-title">{{ __('Income vs Expense Summary') }}</h2>
                    <span class="dash-pill">{{ __('This Month') }}</span>
                </div>
                <div class="summary-grid">
                    <div class="summary-box is-income">
                        <div class="summary-box-head">
                            <span class="label">{{ __('Total Income') }}</span>
                            <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
                        </div>
                        <strong>{{ Money::format($fees['collected']) }}</strong>
                        <span class="summary-trend {{ $fees['growth'] < 0 ? 'is-down' : '' }}">
                            <i class="bi {{ $fees['growth'] < 0 ? 'bi-arrow-down-short' : 'bi-arrow-up-short' }}"></i>{{ $fees['growth'] > 0 ? '+' : '' }}{{ number_format($fees['growth'], 1) }}% {{ __('vs last month') }}
                        </span>
                    </div>
                    <div class="summary-box is-expense">
                        <div class="summary-box-head">
                            <span class="label">{{ __('Total Expenses') }}</span>
                            <i class="bi bi-arrow-down-right" aria-hidden="true"></i>
                        </div>
                        <strong>{{ Money::format($fees['expense']) }}</strong>
                        <span class="summary-trend is-neutral">
                            <i class="bi {{ $fees['expenseGrowth'] < 0 ? 'bi-arrow-down-short' : 'bi-arrow-up-short' }}"></i>{{ $fees['expenseGrowth'] > 0 ? '+' : '' }}{{ number_format($fees['expenseGrowth'], 1) }}% {{ __('vs last month') }}
                        </span>
                    </div>
                </div>
                <div class="summary-net">
                    <div>
                        <span class="label">{{ __('Net Income') }}</span>
                        <strong>{{ Money::format($fees['net']) }}</strong>
                    </div>
                    <span class="summary-net-icon" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>
                </div>
            </section>
        @endif

        <section class="ui-card p-lg dash-card">
            <div class="card-head">
                <h2 class="card-title">{{ __('Quick Actions') }}</h2>
            </div>
            <div class="action-tiles">
                @can('students.create')
                    <a class="action-tile" href="{{ route('students.create') }}">
                        <span class="tile-icon is-blue"><i class="bi bi-person-plus"></i></span>
                        <span>{{ __('Add Student') }}</span>
                    </a>
                @endcan
                @can('staff.create')
                    <a class="action-tile" href="{{ route('staff.teachers.create') }}">
                        <span class="tile-icon is-green"><i class="bi bi-person-workspace"></i></span>
                        <span>{{ __('Add Teacher') }}</span>
                    </a>
                @endcan
                @can('fees.collect')
                    <a class="action-tile" href="{{ route('fees.index') }}">
                        <span class="tile-icon is-sky"><i class="bi bi-cash-coin"></i></span>
                        <span>{{ __('Collect Fee') }}</span>
                    </a>
                @endcan
                @can('attendance.mark')
                    <a class="action-tile" href="{{ route('attendance.students.index') }}">
                        <span class="tile-icon is-amber"><i class="bi bi-check2-square"></i></span>
                        <span>{{ __('Take Attendance') }}</span>
                    </a>
                @endcan
                @can('notices.manage')
                    <a class="action-tile" href="{{ route('notices.create') }}">
                        <span class="tile-icon is-purple"><i class="bi bi-megaphone"></i></span>
                        <span>{{ __('Publish Notice') }}</span>
                    </a>
                @endcan
            </div>
        </section>
    </div>

    @if ($listsCount > 0)
        <div class="dash-row dash-row-4">
            @if ($attendance)
                <section class="ui-card p-lg dash-card">
                    <div class="card-head">
                        <h2 class="card-title">{{ __("Today's Attendance") }}</h2>
                        <a href="{{ route('attendance.students.index') }}" class="card-link">{{ __('View All') }}</a>
                    </div>
                    @if ($attendance['classes']->isEmpty())
                        <p class="dash-empty">{{ __('Attendance not marked yet today') }}</p>
                    @else
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Class') }}</th>
                                    <th scope="col" class="num">{{ __('Present') }}</th>
                                    <th scope="col" class="num">{{ __('Absent') }}</th>
                                    <th scope="col" class="num">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($attendance['classes'] as $row)
                                    <tr>
                                        <td>{{ $row['class'] }}</td>
                                        <td class="num is-present">{{ number_format($row['present']) }}</td>
                                        <td class="num is-absent">{{ number_format($row['absent']) }}</td>
                                        <td class="num">{{ $percentLabel($row['percent']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td>{{ __('Total') }}</td>
                                    <td class="num is-present">{{ number_format($attendance['present']) }}</td>
                                    <td class="num is-absent">{{ number_format($attendance['absent']) }}</td>
                                    <td class="num">{{ $percentLabel($attendance['percent']) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif
                </section>
            @endif

            @if ($fees)
                <section class="ui-card p-lg dash-card">
                    <div class="card-head">
                        <h2 class="card-title">{{ __('Recent Fee Payments') }}</h2>
                        <a href="{{ route('fees.index') }}" class="card-link">{{ __('View All') }}</a>
                    </div>
                    @if ($fees['recentPayments']->isEmpty())
                        <p class="dash-empty">{{ __('No fee payment has been recorded yet.') }}</p>
                    @else
                        <table class="dash-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Student') }}</th>
                                    <th scope="col">{{ __('Class') }}</th>
                                    <th scope="col" class="num">{{ __('Amount') }}</th>
                                    <th scope="col" class="num">{{ __('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($fees['recentPayments'] as $payment)
                                    <tr>
                                        <td class="is-strong">{{ $payment->student?->full_name ?? __('Unknown') }}</td>
                                        <td>{{ $payment->student?->currentEnrollment()?->schoolClass?->name ?? '—' }}</td>
                                        <td class="num">{{ Money::format($payment->amount) }}</td>
                                        <td class="is-date">{{ $payment->paid_at?->format('M j') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2">{{ __('Total') }}</td>
                                    <td class="num" colspan="2">{{ Money::format($fees['recentPayments']->sum('amount')) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    @endif
                </section>
            @endif

            @if ($schedule !== null)
                <section class="ui-card p-lg dash-card">
                    <div class="card-head">
                        <h2 class="card-title">{{ __('Upcoming Exams & Events') }}</h2>
                        <div class="d-flex align-items-center gap-2">
                            @if (($marks_approvals ?? 0) > 0)
                                <a href="{{ route('marks.submissions.index', ['status' => 'submitted']) }}" class="badge-soft is-warning text-decoration-none">
                                    <i class="bi bi-clipboard-data"></i>{{ trans_choice(':count sheet awaiting review|:count sheets awaiting review', $marks_approvals, ['count' => $marks_approvals]) }}
                                </a>
                            @endif
                            <a href="{{ route('exams.index') }}" class="card-link">{{ __('View All') }}</a>
                        </div>
                    </div>
                    @if ($schedule->isEmpty())
                        <p class="dash-empty">{{ __('No exam is scheduled ahead.') }}</p>
                    @else
                        <ul class="agenda-list">
                            @foreach ($schedule as $item)
                                <li class="agenda-item">
                                    <span class="agenda-date">
                                        <strong>{{ $item['date']->format('d') }}</strong>
                                        <span>{{ $item['date']->locale(app()->getLocale())->translatedFormat('M') }}</span>
                                    </span>
                                    <div class="agenda-copy">
                                        <p>
                                            @if ($item['url'])
                                                <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                                            @else
                                                {{ $item['title'] }}
                                            @endif
                                        </p>
                                        <span>{{ $item['meta'] }}</span>
                                    </div>
                                    <span class="chip {{ $item['type'] === 'exam' ? 'is-exam' : 'is-event' }}">
                                        {{ $item['type'] === 'exam' ? __('Exam') : __('Event') }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            @if ($notices)
                <section class="ui-card p-lg dash-card">
                    <div class="card-head">
                        <h2 class="card-title">{{ __('Latest Notices') }}</h2>
                        <a href="{{ route('notices.index') }}" class="card-link">{{ __('View All') }}</a>
                    </div>
                    @if ($notices->isEmpty())
                        <p class="dash-empty">{{ __('No notice has been published yet.') }}</p>
                    @else
                        <ul class="notice-list">
                            @foreach ($notices as $notice)
                                <li class="notice-item">
                                    <span class="notice-icon is-{{ $notice['priority'] }}" aria-hidden="true">
                                        <i class="bi bi-megaphone{{ $notice['priority'] === 'high' ? '-fill' : '' }}"></i>
                                    </span>
                                    <div class="notice-copy">
                                        <p><a href="{{ $notice['url'] }}">{{ $notice['title'] }}</a></p>
                                        <span>{{ $notice['published_at']?->format('M j, Y') }}</span>
                                    </div>
                                    <span class="priority-chip is-{{ $notice['priority'] }}">
                                        {{ __(ucfirst($notice['priority'])) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif
        </div>
    @endif

    @if ($recentAdmissions !== null)
        <section class="ui-card p-lg dash-card mt-3 recent-admissions-card">
            <div class="card-head">
                <div>
                    <h2 class="card-title">{{ __('Recent Admissions') }}</h2>
                    <p class="card-subtitle">{{ __('The newest student records added to this school.') }}</p>
                </div>
                <a href="{{ route('students.index') }}" class="card-link">{{ __('View all students') }}<x-icon name="arrow-right" :size="14" /></a>
            </div>
            @if ($recentAdmissions->isEmpty())
                <p class="dash-empty">{{ __('No student has been admitted yet.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Student') }}</th>
                                <th scope="col">{{ __('Class') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                                <th scope="col">{{ __('Admitted') }}</th>
                                <th scope="col" class="text-end">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentAdmissions as $student)
                                @php($enrollment = $student->enrollments->first())
                                <tr>
                                    <td>
                                        <div class="cell-person">
                                            <span class="avatar">
                                                @if ($student->photoUrl())
                                                    <img src="{{ $student->photoUrl() }}" alt="">
                                                @else
                                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($student->first_name, 0, 1).\Illuminate\Support\Str::substr($student->last_name ?? '', 0, 1)) }}
                                                @endif
                                            </span>
                                            <span>
                                                <a href="{{ route('students.show', $student) }}" class="name text-decoration-none">{{ $student->full_name }}</a>
                                                <span class="email">{{ $student->student_id }} · {{ $student->admission_no }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($enrollment)
                                            <div>{{ $enrollment->schoolClass?->name }}</div>
                                            <div class="text-muted-2" style="font-size:.75rem;">{{ $enrollment->section?->name ? __('Section').' '.$enrollment->section->name : __('No section') }}</div>
                                        @else
                                            <span class="text-muted-2">{{ __('Not enrolled') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge-soft {{ $student->status === 'active' ? 'is-success' : ($student->status === 'inactive' ? 'is-warning' : '') }}">{{ __(ucfirst($student->status)) }}</span>
                                    </td>
                                    <td>{{ $student->admission_date?->format('d M Y') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('students.show', $student) }}" class="icon-action" title="{{ __('View student') }}" aria-label="{{ __('View :name', ['name' => $student->full_name]) }}">
                                            <x-icon name="arrow-right" :size="16" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif

    <section class="ui-card p-lg dash-card mt-3">
        <div class="card-head">
            <h2 class="card-title">{{ __('Recent Admin Activities') }}</h2>
            @can('audit.view')
                <a href="{{ route('audit.index') }}" class="card-link">{{ __('View All') }}</a>
            @endcan
        </div>
        @if ($activity->isEmpty())
            <div class="dash-empty">
                {{ __('Admin and financial changes will appear here as your team starts using the system.') }}
            </div>
        @else
            <div class="activity-strip">
                @foreach ($activity as $log)
                    <div class="activity-strip-item">
                        <span class="activity-icon"><i class="bi {{ $eventIcons[$log->event] ?? 'bi-activity' }}"></i></span>
                        <div>
                            <p>
                                <strong>{{ $log->user?->name ?? __('System') }}</strong>
                                {{ $log->description }}
                            </p>
                            <span class="time">{{ $log->created_at?->format('M j, Y g:i A') }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
    <script>
        (function () {
            if (!window.Chart) return;

            const axisFont = { size: 10, family: '"Noto Sans Bengali", "Segoe UI", system-ui, sans-serif' };
            const axisColor = '#8a99b0';
            const gridColor = '#eef3fa';
            const tooltip = {
                backgroundColor: '#0b1f41',
                padding: 10,
                cornerRadius: 10,
                titleFont: { size: 11, weight: '600' },
                bodyFont: { size: 11 },
                usePointStyle: true
            };
            const moneyTick = function (value) {
                return '৳' + Number(value).toLocaleString('en-IN');
            };

            const feeCanvas = document.getElementById('feeYearChart');
            if (feeCanvas) {
                new Chart(feeCanvas, {
                    type: 'bar',
                    data: {
                        labels: @json($fees['monthLabels'] ?? []),
                        datasets: [
                            {
                                label: @json(__('Fee Collection')),
                                data: @json($fees['monthCollection'] ?? []),
                                backgroundColor: '#1d4f91',
                                borderRadius: 5,
                                maxBarThickness: 16
                            },
                            {
                                label: @json(__('Expenses')),
                                data: @json($fees['monthExpense'] ?? []),
                                backgroundColor: '#94a3b8',
                                borderRadius: 5,
                                maxBarThickness: 16
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: tooltip },
                        scales: {
                            x: { grid: { display: false }, border: { display: false }, ticks: { color: axisColor, font: axisFont } },
                            y: {
                                beginAtZero: true,
                                grid: { color: gridColor },
                                border: { display: false },
                                ticks: { color: axisColor, font: axisFont, callback: moneyTick }
                            }
                        }
                    }
                });
            }

            const attendanceCanvas = document.getElementById('attendanceChart');
            if (attendanceCanvas) {
                new Chart(attendanceCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: [@json(__('Present')), @json(__('Absent')), @json(__('Leave'))],
                        datasets: [{
                            data: @json([$attendance['present'] ?? 0, $attendance['absent'] ?? 0, $attendance['leave'] ?? 0]),
                            backgroundColor: ['#22c55e', '#ef4444', '#f59e0b'],
                            borderWidth: 0,
                            hoverOffset: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '74%',
                        plugins: { legend: { display: false }, tooltip: tooltip }
                    }
                });
            }

            const enrollmentCanvas = document.getElementById('enrollmentChart');
            if (enrollmentCanvas) {
                const context = enrollmentCanvas.getContext('2d');
                const fill = context.createLinearGradient(0, 0, 0, 240);
                fill.addColorStop(0, 'rgba(29, 79, 145, 0.16)');
                fill.addColorStop(1, 'rgba(29, 79, 145, 0.01)');

                new Chart(enrollmentCanvas, {
                    type: 'line',
                    data: {
                        labels: @json($chart['labels']),
                        datasets: [{
                            label: @json(__('Enrollment')),
                            data: @json($chart['current']),
                            borderColor: '#2563eb',
                            backgroundColor: fill,
                            borderWidth: 2.5,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#2563eb',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            fill: true,
                            tension: 0.35
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { display: false }, tooltip: tooltip },
                        scales: {
                            x: { grid: { display: false }, border: { display: false }, ticks: { color: axisColor, font: axisFont } },
                            y: {
                                beginAtZero: true,
                                grid: { color: gridColor },
                                border: { display: false },
                                ticks: { precision: 0, color: axisColor, font: axisFont }
                            }
                        }
                    }
                });
            }
        })();
    </script>
@endpush
