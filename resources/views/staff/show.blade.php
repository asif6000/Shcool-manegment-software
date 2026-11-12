@extends('layouts.app')

@section('title', $person->full_name)

@section('content')
    @php
        $isTeacher = $type === 'teachers';
        $currentSalary = $person->salaryStructures->firstWhere('is_active', true);
    @endphp

    <div class="page-header">
        <div class="d-flex align-items-center gap-3">
            <span class="avatar avatar-lg">
                @if ($person->photoUrl())
                    <img src="{{ $person->photoUrl() }}" alt="{{ $person->full_name }}">
                @else
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($person->first_name, 0, 1).\Illuminate\Support\Str::substr($person->last_name ?? '', 0, 1)) }}
                @endif
            </span>
            <div>
                <span class="page-eyebrow"><i class="bi bi-person-workspace"></i>{{ $isTeacher ? __('Teacher profile') : __('Staff profile') }}</span>
                <h1 class="page-title mb-1">{{ $person->full_name }}</h1>
                <p class="page-subtitle mb-0">
                    {{ $person->employee_code }} · {{ $person->designation }}
                    <span class="badge-soft {{ $person->status === 'active' ? 'is-success' : ($person->status === 'inactive' ? 'is-warning' : 'is-danger') }} ms-1">{{ __(ucfirst($person->status)) }}</span>
                </p>
            </div>
        </div>
        <div class="page-actions">
            <a href="{{ route('staff.index', ['type' => $type]) }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Staff directory') }}</a>
            @can('staff.update')
                <a href="{{ route('staff.'.$type.'.edit', $person) }}" class="btn btn-primary"><i class="bi bi-pencil"></i>{{ __('Edit') }}</a>
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <section class="ui-card p-lg mb-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h2 class="card-title mb-0">{{ __('Personal details') }}</h2>
                    <span class="badge-soft">{{ __('Code') }} {{ $person->employee_code }}</span>
                </div>
                <dl class="detail-list">
                    <div><dt>{{ __('Gender') }}</dt><dd>{{ __(ucfirst($person->gender)) }}</dd></div>
                    <div><dt>{{ __('Date of birth') }}</dt><dd>{{ $person->date_of_birth?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>{{ __('Phone') }}</dt><dd>{{ $person->phone }}</dd></div>
                    <div><dt>{{ __('Email') }}</dt><dd>{{ $person->email ?? '—' }}</dd></div>
                    <div><dt>{{ __('Joining date') }}</dt><dd>{{ $person->joining_date?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>{{ __('Employment type') }}</dt><dd>{{ __(ucwords(str_replace('_', ' ', $person->employment_type))) }}</dd></div>
                    @if ($isTeacher)
                        <div><dt>{{ __('Qualification') }}</dt><dd>{{ $person->qualification ?? '—' }}</dd></div>
                        <div><dt>{{ __('Specialization') }}</dt><dd>{{ $person->specialization ?? '—' }}</dd></div>
                        <div>
                            <dt>{{ __('Classes') }}</dt>
                            <dd>
                                @forelse ($person->classes as $class)
                                    <span class="badge-soft me-1">{{ $class->name }}</span>
                                @empty
                                    {{ __('Not assigned') }}
                                @endforelse
                            </dd>
                        </div>
                    @else
                        <div><dt>{{ __('Department') }}</dt><dd>{{ $person->department ?? '—' }}</dd></div>
                    @endif
                </dl>
            </section>

            <section class="ui-card p-lg">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h2 class="card-title mb-0">{{ __('Current salary') }}</h2>
                    @can('salary.manage')
                        <a href="{{ route('staff.salary.index') }}" class="btn btn-sm btn-light-soft">{{ __('Manage') }}</a>
                    @endcan
                </div>
                @if ($currentSalary)
                    <dl class="detail-list mt-3">
                        <div><dt>{{ __('Basic salary') }}</dt><dd>৳ {{ number_format((float) $currentSalary->basic_salary, 2) }}</dd></div>
                        <div><dt>{{ __('House allowance') }}</dt><dd>৳ {{ number_format((float) $currentSalary->house_allowance, 2) }}</dd></div>
                        <div><dt>{{ __('Transport allowance') }}</dt><dd>৳ {{ number_format((float) $currentSalary->transport_allowance, 2) }}</dd></div>
                        <div><dt>{{ __('Medical allowance') }}</dt><dd>৳ {{ number_format((float) $currentSalary->medical_allowance, 2) }}</dd></div>
                        <div><dt>{{ __('Other allowance') }}</dt><dd>৳ {{ number_format((float) $currentSalary->other_allowance, 2) }}</dd></div>
                        <div><dt><strong>{{ __('Gross salary') }}</strong></dt><dd><strong>৳ {{ number_format((float) $currentSalary->gross_salary, 2) }}</strong></dd></div>
                        <div><dt>{{ __('Effective from') }}</dt><dd>{{ $currentSalary->effective_from?->format('d M Y') }}</dd></div>
                    </dl>
                @else
                    <p class="text-muted-2 mb-0 mt-2">{{ __('No active salary structure has been set for this employee.') }}</p>
                @endif
            </section>
        </div>

        <div class="col-lg-7">
            <section class="ui-card table-card mb-3">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Recent leave') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Pending and decided leave requests for this employee.') }}</p>
                    </div>
                    @can('leave.view')
                        <a href="{{ route('staff.leaves.index') }}" class="btn btn-sm btn-light-soft"><i class="bi bi-calendar2-week"></i>{{ __('Leave register') }}</a>
                    @endcan
                </div>

                @if ($person->leaves->isEmpty())
                    <div class="empty-state py-4"><p class="mb-0">{{ __('No leave has been recorded yet.') }}</p></div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Period') }}</th>
                                    <th>{{ __('Days') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($person->leaves as $leave)
                                    <tr>
                                        <td>{{ $leave->leaveType?->name ?? '—' }}</td>
                                        <td>
                                            {{ $leave->start_date?->format('d M Y') }} – {{ $leave->end_date?->format('d M Y') }}
                                            @if ($leave->rejection_reason)
                                                <div class="text-muted-2" style="font-size:.78rem;">{{ $leave->rejection_reason }}</div>
                                            @endif
                                        </td>
                                        <td>{{ rtrim(rtrim((string) $leave->total_days, '0'), '.') }}</td>
                                        <td>
                                            <span class="badge-soft {{ $leave->status === 'approved' ? 'is-success' : ($leave->status === 'rejected' ? 'is-danger' : 'is-warning') }}">
                                                {{ __(ucfirst($leave->status)) }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Salary history') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Only one structure stays active at a time.') }}</p>
                    </div>
                </div>

                @if ($person->salaryStructures->isEmpty())
                    <div class="empty-state py-4"><p class="mb-0">{{ __('No salary structure has been defined yet.') }}</p></div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Effective') }}</th>
                                    <th>{{ __('Basic') }}</th>
                                    <th>{{ __('Gross') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($person->salaryStructures as $structure)
                                    <tr>
                                        <td>
                                            {{ $structure->effective_from?->format('d M Y') }}
                                            @if ($structure->effective_to)
                                                <span class="text-muted-2"> – {{ $structure->effective_to->format('d M Y') }}</span>
                                            @endif
                                        </td>
                                        <td>৳ {{ number_format((float) $structure->basic_salary, 2) }}</td>
                                        <td>৳ {{ number_format((float) $structure->gross_salary, 2) }}</td>
                                        <td>
                                            <span class="badge-soft {{ $structure->is_active ? 'is-success' : '' }}">{{ $structure->is_active ? __('Active') : __('Closed') }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
