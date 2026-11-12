@extends('layouts.app')

@section('title', __('Staff attendance'))

@section('content')
    @php
        $canMark = auth()->user()->can('attendance.mark');
        $statusLabels = [
            'present' => __('Present'),
            'absent' => __('Absent'),
            'late' => __('Late'),
            'leave' => __('On leave'),
        ];
        $holidayLocked = $holiday !== null && ! old('override_holiday');
        $locked = ! $canMark || $holidayLocked;

        $teachers = $sheet['teachers'];
        $employees = $sheet['employees'];
        $teacherAttendance = $sheet['teacherAttendance'];
        $employeeAttendance = $sheet['employeeAttendance'];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-workspace"></i>{{ __('Daily operations') }}</span>
            <h1 class="page-title">{{ __('Staff attendance') }}</h1>
            <p class="page-subtitle">{{ __('Mark daily attendance for teachers and non-teaching staff. Re-saving a date updates the same records.') }}</p>
        </div>
        <div class="page-actions">
            @can('staff.view')
                <a href="{{ route('staff.index') }}" class="btn btn-light-soft">
                    <i class="bi bi-people"></i>{{ __('Staff directory') }}
                </a>
            @endcan
            <a href="{{ route('attendance.students.index') }}" class="btn btn-primary">
                <i class="bi bi-mortarboard"></i>{{ __('Student attendance') }}
            </a>
        </div>
    </div>

    @include('attendance.partials.tabs', ['active' => 'staff'])

    <section class="ui-card p-lg mb-3">
        <div class="card-head mb-3">
            <div>
                <h2 class="card-title mb-1">{{ __('Choose a date') }}</h2>
                <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Only active teachers and staff are listed. Holidays require an explicit override.') }}</p>
            </div>
            <span class="badge-soft is-brand">{{ $date->translatedFormat('l, d F Y') }}</span>
        </div>

        <form method="GET" action="{{ route('attendance.staff.index') }}" class="filter-form">
            <div>
                <label class="form-label" for="staff-attendance-date">{{ __('Date') }}</label>
                <input id="staff-attendance-date" type="date" name="date" class="form-control @error('attendance_date') is-invalid @enderror" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" data-auto-submit>
            </div>
            <div class="align-self-end d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Show') }}</button>
                @if (request()->filled('date') || $errors->has('attendance_date'))
                    <a href="{{ route('attendance.staff.index') }}" class="btn btn-sm btn-link">{{ __('Today') }}</a>
                @endif
            </div>
        </form>
    </section>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">{{ __('Staff attendance could not be saved.') }}</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="stats-grid mb-3" aria-label="{{ __('Monthly staff attendance summary') }}">
        @foreach ($statusLabels as $value => $label)
            <article class="stat-card">
                <div class="stat-top">
                    <div>
                        <p class="stat-label">{{ $label }}</p>
                        <p class="stat-value">{{ number_format($summary[$value] ?? 0) }}</p>
                    </div>
                    <span class="stat-icon {{ $value === 'present' ? 'is-teal' : ($value === 'absent' ? '' : ($value === 'late' ? 'is-amber' : 'is-purple')) }}" aria-hidden="true">
                        <i class="bi {{ $value === 'present' ? 'bi-person-check' : ($value === 'absent' ? 'bi-person-x' : ($value === 'late' ? 'bi-clock-history' : 'bi-calendar2-week')) }}"></i>
                    </span>
                </div>
                <div class="stat-foot"><span>{{ $date->translatedFormat('F Y') }}</span></div>
            </article>
        @endforeach
    </section>

    @if ($teachers->isEmpty() && $employees->isEmpty())
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-person-workspace"></i></div>
                <h3>{{ __('No active staff') }}</h3>
                <p>{{ __('Add teachers or staff members before marking attendance.') }}</p>
                @can('staff.create')
                    <a href="{{ route('staff.teachers.create') }}" class="btn btn-primary mt-3"><i class="bi bi-person-plus"></i>{{ __('Add teacher') }}</a>
                @endcan
            </div>
        </section>
    @else
        <section class="ui-card table-card">
            <form method="POST" action="{{ route('attendance.staff.store') }}" id="staff-attendance-form">
                @csrf
                <input type="hidden" name="attendance_date" value="{{ $date->toDateString() }}">

                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Mark staff attendance') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.78rem;">
                            {{ $date->translatedFormat('l, d F Y') }} ·
                            {{ trans_choice(':count teacher|:count teachers', $teachers->count(), ['count' => $teachers->count()]) }} ·
                            {{ trans_choice(':count staff member|:count staff members', $employees->count(), ['count' => $employees->count()]) }}
                            @if ($holiday)
                                · <span class="badge-soft is-warning"><i class="bi bi-calendar-x"></i>{{ __('Holiday') }}</span>
                            @endif
                        </p>
                    </div>
                    @if ($canMark)
                        <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Bulk status') }}">
                            <button type="button" class="btn btn-light-soft" data-staff-bulk="present"><i class="bi bi-check2-all"></i>{{ __('All present') }}</button>
                            <button type="button" class="btn btn-light-soft" data-staff-bulk="absent"><i class="bi bi-x-circle"></i>{{ __('All absent') }}</button>
                        </div>
                    @endif
                </div>

                @if ($holiday)
                    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3 m-3 mb-0" role="status">
                        <i class="bi bi-calendar-x fs-5"></i>
                        <div class="flex-grow-1">
                            <strong>{{ __('Holiday') }}:</strong> {{ $holiday->name }}
                            <div style="font-size:.8rem;">{{ __('Attendance is normally not marked on this date. Confirm the override to continue.') }}</div>
                        </div>
                        @if ($canMark)
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="override_holiday" value="1" id="staff-attendance-override" @checked((bool) old('override_holiday'))>
                                <label class="form-check-label" for="staff-attendance-override">{{ __('Override holiday') }}</label>
                            </div>
                        @endif
                    </div>
                @endif

                <fieldset id="staff-attendance-fieldset" class="border-0 p-0 m-0" @disabled($locked)>
                    @if ($teachers->isNotEmpty())
                        <div class="px-3 pt-3 pb-1">
                            <h3 class="card-title mb-1">{{ __('Teachers') }}</h3>
                            <p class="text-muted-2 mb-0" style="font-size:.76rem;">{{ __('Teaching staff with an active record.') }}</p>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Teacher') }}</th>
                                        <th scope="col">{{ __('Designation') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col">{{ __('Marked') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($teachers as $teacher)
                                        @php($record = $teacherAttendance->get($teacher->id))
                                        @php($current = (string) old('teachers.'.$teacher->id, $record?->status ?? 'present'))
                                        @php($current = array_key_exists($current, $statusLabels) ? $current : 'present')
                                        <tr>
                                            <td>
                                                <div class="cell-person">
                                                    <span class="avatar avatar-sm">
                                                        @if ($teacher->photoUrl())
                                                            <img src="{{ $teacher->photoUrl() }}" alt="">
                                                        @else
                                                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($teacher->first_name, 0, 1).\Illuminate\Support\Str::substr($teacher->last_name ?? '', 0, 1)) }}
                                                        @endif
                                                    </span>
                                                    <span>
                                                        <span class="name">{{ $teacher->full_name }}</span>
                                                        <span class="email">{{ $teacher->employee_code }}</span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div>{{ $teacher->designation ?: '—' }}</div>
                                                <div class="text-muted-2" style="font-size:.76rem;">{{ $teacher->specialization ?: __('No specialization') }}</div>
                                            </td>
                                            <td style="min-width:150px;">
                                                <label class="visually-hidden" for="teacher-status-{{ $teacher->id }}">{{ __('Attendance status for :name', ['name' => $teacher->full_name]) }}</label>
                                                <select id="teacher-status-{{ $teacher->id }}" name="teachers[{{ $teacher->id }}]" class="form-select form-select-sm" data-staff-status>
                                                    @foreach ($statusLabels as $value => $label)
                                                        <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="text-muted-2" style="font-size:.78rem;">
                                                {{ $record?->updated_at?->format('d M, H:i') ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if ($employees->isNotEmpty())
                        <div class="px-3 pt-3 pb-1">
                            <h3 class="card-title mb-1">{{ __('Staff members') }}</h3>
                            <p class="text-muted-2 mb-0" style="font-size:.76rem;">{{ __('Non-teaching employees with an active record.') }}</p>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Staff member') }}</th>
                                        <th scope="col">{{ __('Designation') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col">{{ __('Marked') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($employees as $employee)
                                        @php($record = $employeeAttendance->get($employee->id))
                                        @php($current = (string) old('employees.'.$employee->id, $record?->status ?? 'present'))
                                        @php($current = array_key_exists($current, $statusLabels) ? $current : 'present')
                                        <tr>
                                            <td>
                                                <div class="cell-person">
                                                    <span class="avatar avatar-sm">
                                                        @if ($employee->photoUrl())
                                                            <img src="{{ $employee->photoUrl() }}" alt="">
                                                        @else
                                                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($employee->first_name, 0, 1).\Illuminate\Support\Str::substr($employee->last_name ?? '', 0, 1)) }}
                                                        @endif
                                                    </span>
                                                    <span>
                                                        <span class="name">{{ $employee->full_name }}</span>
                                                        <span class="email">{{ $employee->employee_code }}</span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div>{{ $employee->designation ?: '—' }}</div>
                                                <div class="text-muted-2" style="font-size:.76rem;">{{ $employee->department ?: __('No department') }}</div>
                                            </td>
                                            <td style="min-width:150px;">
                                                <label class="visually-hidden" for="employee-status-{{ $employee->id }}">{{ __('Attendance status for :name', ['name' => $employee->full_name]) }}</label>
                                                <select id="employee-status-{{ $employee->id }}" name="employees[{{ $employee->id }}]" class="form-select form-select-sm" data-staff-status>
                                                    @foreach ($statusLabels as $value => $label)
                                                        <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td class="text-muted-2" style="font-size:.78rem;">
                                                {{ $record?->updated_at?->format('d M, H:i') ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="table-footer">
                        <span class="text-muted-2" style="font-size:.78rem;">
                            @if ($canMark)
                                {{ __('The monthly summary above counts every marked record for :month.', ['month' => $date->translatedFormat('F Y')]) }}
                            @else
                                {{ __('You can review attendance but not change it.') }}
                            @endif
                        </span>
                        @if ($canMark)
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i>{{ __('Save attendance') }}</button>
                        @endif
                    </div>
                </fieldset>
            </form>
        </section>
    @endif
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('staff-attendance-form');
    if (!form) return;

    const selects = Array.from(form.querySelectorAll('select[data-staff-status]'));
    const fieldset = document.getElementById('staff-attendance-fieldset');
    const override = document.getElementById('staff-attendance-override');

    form.querySelectorAll('[data-staff-bulk]').forEach(function (button) {
        button.addEventListener('click', function () {
            const value = button.getAttribute('data-staff-bulk');
            selects.forEach(function (select) {
                select.value = value;
            });
        });
    });

    if (override && fieldset) {
        override.addEventListener('change', function () {
            fieldset.disabled = ! override.checked;
        });
    }
})();
</script>
@endpush
