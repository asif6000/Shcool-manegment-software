@extends('layouts.app')

@section('title', __('Staff leave'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-calendar2-week"></i>{{ __('Human resources') }}</span>
            <h1 class="page-title">{{ __('Staff leave') }}</h1>
            <p class="page-subtitle">{{ __('Record leave types and requests. Overlapping requests are rejected and every decision is audited.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('staff.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Staff directory') }}</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <section class="ui-card p-lg h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Leave types') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Types in use cannot be deleted; deactivate them instead.') }}</p>
                    </div>
                </div>

                @if ($leaveTypes->isEmpty())
                    <p class="text-muted-2">{{ __('No leave types have been defined yet.') }}</p>
                @else
                    <div class="table-responsive mb-3">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Max / year') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($leaveTypes as $leaveType)
                                    <tr>
                                        <td>
                                            <div>{{ $leaveType->name }}</div>
                                            <div class="text-muted-2" style="font-size:.75rem;">
                                                {{ $leaveType->code ?: '—' }} · {{ $leaveType->is_paid ? __('Paid') : __('Unpaid') }}
                                            </div>
                                        </td>
                                        <td>{{ $leaveType->max_days_per_year ?? __('No limit') }}</td>
                                        <td>
                                            <span class="badge-soft {{ $leaveType->is_active ? 'is-success' : '' }}">{{ $leaveType->is_active ? __('Active') : __('Inactive') }}</span>
                                        </td>
                                        <td class="text-end">
                                            @can('leave.manage')
                                                <div class="d-inline-flex gap-1">
                                                    <button type="button" class="icon-action" data-bs-toggle="modal" data-bs-target="#leaveTypeModal{{ $leaveType->id }}" aria-label="{{ __('Edit :name', ['name' => $leaveType->name]) }}" title="{{ __('Edit') }}">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <form method="POST" action="{{ route('staff.leave-types.destroy', $leaveType) }}"
                                                          data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                          data-confirm-delete="1"
                                                          data-confirm-label="{{ __('Permanently Delete') }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="icon-action is-danger" aria-label="{{ __('Delete :name', ['name' => $leaveType->name]) }}" title="{{ __('Delete') }}">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @can('leave.manage')
                    <form method="POST" action="{{ route('staff.leave-types.store') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-7">
                            <label class="form-label" for="leave-type-name">{{ __('New leave type') }}</label>
                            <input id="leave-type-name" type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-5">
                            <label class="form-label" for="leave-type-code">{{ __('Code') }}</label>
                            <input id="leave-type-code" type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}">
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="leave-type-days">{{ __('Max / year') }}</label>
                            <input id="leave-type-days" type="number" name="max_days_per_year" min="1" max="365" class="form-control" value="{{ old('max_days_per_year') }}">
                        </div>
                        <div class="col-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_paid" value="1" id="leave-type-paid" checked>
                                <label class="form-check-label" for="leave-type-paid">{{ __('Paid') }}</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="leave-type-active" checked>
                                <label class="form-check-label" for="leave-type-active">{{ __('Active') }}</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i>{{ __('Add leave type') }}</button>
                        </div>
                    </form>
                @endcan
            </section>
        </div>

        <div class="col-lg-7">
            <section class="ui-card p-lg h-100">
                <div class="mb-3">
                    <h2 class="card-title mb-1">{{ __('Record a leave request') }}</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Choose either a teacher or a staff member. Requests start as pending.') }}</p>
                </div>

                @can('leave.manage')
                    @if ($leaveTypes->where('is_active', true)->isEmpty())
                        <div class="empty-state py-4">
                            <p class="mb-0">{{ __('Add an active leave type before recording a request.') }}</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('staff.leaves.store') }}" class="row g-3">
                            @csrf
                            <div class="col-sm-6">
                                <label class="form-label" for="leave-type">{{ __('Leave type') }}</label>
                                <select id="leave-type" name="leave_type_id" class="form-select @error('leave_type_id') is-invalid @enderror" required>
                                    <option value="">{{ __('Choose a type') }}</option>
                                    @foreach ($leaveTypes->where('is_active', true) as $leaveType)
                                        <option value="{{ $leaveType->id }}" @selected((string) old('leave_type_id') === (string) $leaveType->id)>{{ $leaveType->name }}</option>
                                    @endforeach
                                </select>
                                @error('leave_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label" for="leave-teacher">{{ __('Teacher') }}</label>
                                <select id="leave-teacher" name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror" data-staff-pair="leave-employee">
                                    <option value="">{{ __('No teacher selected') }}</option>
                                    @foreach ($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" @selected((string) old('teacher_id') === (string) $teacher->id)>{{ $teacher->full_name }}</option>
                                    @endforeach
                                </select>
                                @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-sm-6">
                                <label class="form-label" for="leave-employee">{{ __('Staff member') }}</label>
                                <select id="leave-employee" name="employee_id" class="form-select @error('employee_id') is-invalid @enderror" data-staff-pair="leave-teacher">
                                    <option value="">{{ __('No staff member selected') }}</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->full_name }}</option>
                                    @endforeach
                                </select>
                                @error('employee_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-sm-3">
                                <label class="form-label" for="leave-start">{{ __('From') }}</label>
                                <input id="leave-start" type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', now()->toDateString()) }}" required>
                                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-sm-3">
                                <label class="form-label" for="leave-end">{{ __('To') }}</label>
                                <input id="leave-end" type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', now()->toDateString()) }}" required>
                                @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="leave-reason">{{ __('Reason') }}</label>
                                <textarea id="leave-reason" name="reason" rows="2" class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason') }}</textarea>
                                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-calendar-plus"></i>{{ __('Record request') }}</button>
                            </div>
                        </form>
                    @endif
                @else
                    <p class="text-muted-2 mb-0">{{ __('You can view leave requests but not record or approve them.') }}</p>
                @endcan
            </section>
        </div>
    </div>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <div>
                <h2 class="card-title mb-1">{{ __('Leave requests') }}</h2>
                <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Only pending requests can be approved or rejected.') }}</p>
            </div>
            <form method="GET" action="{{ route('staff.leaves.index') }}" class="filter-form">
                <label class="visually-hidden" for="leave-status">{{ __('Status') }}</label>
                <select id="leave-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (['pending' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected')] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($requests->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-calendar2-week"></i></div>
                <h3>{{ __('No leave requests found') }}</h3>
                <p>{{ __('Record a request or change the status filter.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Staff member') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Period') }}</th>
                            <th>{{ __('Days') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Decision') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $leave)
                            <tr>
                                <td>
                                    <div>{{ $leave->staffName() }}</div>
                                    <div class="text-muted-2" style="font-size:.75rem;">{{ $leave->teacher_id ? __('Teacher') : __('Staff member') }}</div>
                                </td>
                                <td>{{ $leave->leaveType?->name ?? '—' }}</td>
                                <td>
                                    {{ $leave->start_date?->format('d M Y') }} – {{ $leave->end_date?->format('d M Y') }}
                                    <div class="text-muted-2" style="font-size:.75rem;">{{ \Illuminate\Support\Str::limit($leave->reason, 60) }}</div>
                                </td>
                                <td>{{ rtrim(rtrim((string) $leave->total_days, '0'), '.') }}</td>
                                <td>
                                    <span class="badge-soft {{ $leave->status === 'approved' ? 'is-success' : ($leave->status === 'rejected' ? 'is-danger' : 'is-warning') }}">
                                        {{ __(ucfirst($leave->status)) }}
                                    </span>
                                    @if ($leave->approver)
                                        <div class="text-muted-2" style="font-size:.72rem;">{{ $leave->approver->name }} · {{ $leave->approved_at?->format('d M Y') }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($leave->status === 'pending')
                                        @can('leave.manage')
                                            <div class="d-flex flex-column flex-sm-row gap-1 justify-content-end align-items-sm-center">
                                                <form method="POST" action="{{ route('staff.leaves.approve', $leave) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-light-soft text-success"><i class="bi bi-check2"></i>{{ __('Approve') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('staff.leaves.reject', $leave) }}" class="d-flex gap-1">
                                                    @csrf
                                                    <label class="visually-hidden" for="reject-{{ $leave->id }}">{{ __('Rejection reason') }}</label>
                                                    <input id="reject-{{ $leave->id }}" type="text" name="reason" class="form-control form-control-sm" style="max-width:150px;" placeholder="{{ __('Reason') }}" required>
                                                    <button type="submit" class="btn btn-sm btn-light-soft text-danger"><i class="bi bi-x-lg"></i>{{ __('Reject') }}</button>
                                                </form>
                                            </div>
                                        @endcan
                                    @else
                                        <span class="text-muted-2" style="font-size:.78rem;">{{ $leave->approved_at?->format('d M Y') ?? '—' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $requests->firstItem(), 'to' => $requests->lastItem(), 'total' => $requests->total()]) }}</span>
                {{ $requests->links() }}
            </div>
        @endif
    </section>

    @can('leave.manage')
        @foreach ($leaveTypes as $leaveType)
            <div class="modal fade" id="leaveTypeModal{{ $leaveType->id }}" tabindex="-1" aria-labelledby="leaveTypeModalLabel{{ $leaveType->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('staff.leave-types.update', $leaveType) }}" class="modal-content">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="leaveTypeModalLabel{{ $leaveType->id }}">{{ __('Edit leave type') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="type-name-{{ $leaveType->id }}">{{ __('Name') }}</label>
                                <input id="type-name-{{ $leaveType->id }}" type="text" name="name" class="form-control" value="{{ $leaveType->name }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="type-code-{{ $leaveType->id }}">{{ __('Code') }}</label>
                                <input id="type-code-{{ $leaveType->id }}" type="text" name="code" class="form-control" value="{{ $leaveType->code }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="type-days-{{ $leaveType->id }}">{{ __('Max days per year') }}</label>
                                <input id="type-days-{{ $leaveType->id }}" type="number" name="max_days_per_year" min="1" max="365" class="form-control" value="{{ $leaveType->max_days_per_year }}">
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="is_paid" value="1" id="type-paid-{{ $leaveType->id }}" @checked($leaveType->is_paid)>
                                <label class="form-check-label" for="type-paid-{{ $leaveType->id }}">{{ __('Paid leave') }}</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="type-active-{{ $leaveType->id }}" @checked($leaveType->is_active)>
                                <label class="form-check-label" for="type-active-{{ $leaveType->id }}">{{ __('Active') }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('Save changes') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endcan
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-staff-pair]').forEach(function (select) {
        select.addEventListener('change', function () {
            const partner = document.getElementById(select.getAttribute('data-staff-pair'));
            if (!partner) return;

            if (select.value !== '') {
                partner.value = '';
                partner.disabled = true;
            } else {
                partner.disabled = false;
            }
        });

        if (select.value !== '') {
            const partner = document.getElementById(select.getAttribute('data-staff-pair'));
            if (partner) partner.disabled = true;
        }
    });
})();
</script>
@endpush
