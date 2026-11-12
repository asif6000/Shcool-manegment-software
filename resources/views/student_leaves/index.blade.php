@extends('layouts.app')

@section('title', __('Student leave'))

@section('content')
    @php
        $typeLabels = [
            'sick' => __('Sick leave'),
            'casual' => __('Casual leave'),
            'family' => __('Family matter'),
            'emergency' => __('Emergency'),
            'other' => __('Other'),
        ];
        $statusBadges = [
            'pending' => ['is-warning', __('Awaiting decision')],
            'approved' => ['is-success', __('Approved')],
            'rejected' => ['is-danger', __('Rejected')],
            'cancelled' => ['', __('Withdrawn by family')],
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-calendar2-x"></i>{{ __('Attendance') }}</span>
            <h1 class="page-title">{{ __('Student leave') }}</h1>
            <p class="page-subtitle">{{ __('Requests sent from the student portal. Approving or rejecting tells the student and marks the absence in their record.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('attendance.students.index') }}" class="btn btn-light-soft">
                <i class="bi bi-calendar2-check"></i>{{ __('Mark attendance') }}
            </a>
        </div>
    </div>

    <div class="mini-stats mb-3">
        <div class="mini-stat">
            <span class="value">{{ $pendingCount }}</span>
            <span class="label">{{ __('Waiting for a decision') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ $leaves->total() }}</span>
            <span class="label">{{ __('Requests for this filter') }}</span>
        </div>
        <div class="mini-stat text-start">
            <span class="label">{{ __('Attendance is still entered by the class teacher — approving leave does not mark a day present on its own.') }}</span>
        </div>
    </div>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('student_leaves.index') }}" class="filter-form">
                <label class="visually-hidden" for="filter-status">{{ __('Status') }}</label>
                <select id="filter-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                            {{ $statusBadges[$status][1] ?? __(ucfirst($status)) }}
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-class">{{ __('Class') }}</label>
                <select id="filter-class" name="class" class="form-select" data-auto-submit>
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected((int) ($filters['class'] ?? 0) === $class->id)>
                            {{ $class->name }}
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-from">{{ __('Leave starts on or after') }}</label>
                <input id="filter-from" type="date" name="from" class="form-control" value="{{ $filters['from'] ?? '' }}" aria-label="{{ __('From') }}">
                <label class="visually-hidden" for="filter-to">{{ __('Leave ends on or before') }}</label>
                <input id="filter-to" type="date" name="to" class="form-control" value="{{ $filters['to'] ?? '' }}" aria-label="{{ __('To') }}">
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('student_leaves.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">
                {{ trans_choice(':count request|:count requests', $leaves->total(), ['count' => $leaves->total()]) }}
            </span>
        </div>

        @if ($leaves->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                <h3>{{ __('No leave requests') }}</h3>
                <p>{{ __('Requests students send from the portal land here. Nothing is waiting right now.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Student') }}</th>
                            <th>{{ __('Dates') }}</th>
                            <th>{{ __('Reason') }}</th>
                            <th>{{ __('Status') }}</th>
                            @can('leave.manage')
                                <th class="text-end">{{ __('Decision') }}</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($leaves as $leave)
                            @php
                                [$badgeClass, $badgeLabel] = $statusBadges[$leave->status] ?? ['', __(ucfirst($leave->status))];
                                $enrollment = $leave->student?->enrollments?->sortByDesc('enrolled_on')->first();
                            @endphp
                            <tr>
                                <td style="min-width:0;">
                                    @if ($leave->student)
                                        <a href="{{ route('students.show', $leave->student) }}" class="fw-semibold">
                                            {{ $leave->student->full_name }}
                                        </a>
                                    @else
                                        <span class="fw-semibold">{{ __('Removed student') }}</span>
                                    @endif
                                    <div class="text-muted-2" style="font-size:.74rem;">
                                        {{ $leave->student?->student_id ?? '—' }}
                                        · {{ $enrollment?->schoolClass?->name ?? '—' }}
                                        @if ($enrollment?->section)
                                            · {{ __('Section') }} {{ $enrollment->section->name }}
                                        @endif
                                        @if ($enrollment?->roll_no)
                                            · {{ __('Roll') }} {{ $enrollment->roll_no }}
                                        @endif
                                    </div>
                                </td>
                                <td style="font-size:.8rem;white-space:nowrap;">
                                    {{ $leave->from_date?->format('d M Y') ?? '—' }} → {{ $leave->to_date?->format('d M Y') ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.72rem;">
                                        {{ trans_choice(':count day|:count days', $leave->daysRequested(), ['count' => $leave->daysRequested()]) }}
                                    </div>
                                </td>
                                <td style="min-width:0;">
                                    <span class="badge-soft">{{ $typeLabels[$leave->leave_type] ?? $leave->typeLabel() }}</span>
                                    <div class="text-muted-2 mt-1" style="font-size:.76rem;">{{ \Illuminate\Support\Str::limit($leave->reason, 110) }}</div>
                                    @if ($leave->contact_phone)
                                        <div class="text-muted-2" style="font-size:.72rem;">
                                            <i class="bi bi-telephone"></i>{{ $leave->contact_phone }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge-soft {{ $badgeClass }}"><span class="dot"></span>{{ $badgeLabel }}</span>
                                    @if ($leave->decision_notes)
                                        <div class="text-muted-2 mt-1" style="font-size:.72rem;">
                                            {{ \Illuminate\Support\Str::limit($leave->decision_notes, 60) }}
                                        </div>
                                    @endif
                                    @if ($leave->isDecided())
                                        <div class="text-muted-2" style="font-size:.7rem;">
                                            {{ $leave->decider?->name ?? '—' }} · {{ $leave->decided_at?->format('d M Y, g:i a') }}
                                        </div>
                                    @endif
                                </td>
                                @can('leave.manage')
                                    <td class="text-end" style="width:290px;">
                                        @if ($leave->status !== 'cancelled')
                                            <button type="button" class="btn btn-sm btn-light-soft" data-bs-toggle="collapse"
                                                    data-bs-target="#decide-{{ $leave->id }}" aria-expanded="false">
                                                <i class="bi bi-clipboard-check"></i>{{ $leave->status === 'pending' ? __('Decide') : __('Change decision') }}
                                            </button>
                                        @else
                                            <span class="text-muted-2" style="font-size:.74rem;">{{ __('The family withdrew it') }}</span>
                                        @endif
                                    </td>
                                @endcan
                            </tr>
                            @can('leave.manage')
                                @if ($leave->status !== 'cancelled')
                                    <tr>
                                        <td colspan="5" class="p-0 border-top-0">
                                            <div class="collapse" id="decide-{{ $leave->id }}">
                                                <div class="p-3">
                                                    <p class="text-muted-2" style="font-size:.78rem;">
                                                        <strong>{{ $typeLabels[$leave->leave_type] ?? $leave->typeLabel() }}</strong>
                                                        · {{ $leave->from_date?->format('d M Y') }} → {{ $leave->to_date?->format('d M Y') }}
                                                    </p>
                                                    <p class="mb-3" style="font-size:.82rem;white-space:pre-line;">{{ $leave->reason }}</p>
                                                    <form method="POST" action="{{ route('student_leaves.decide', $leave) }}">
                                                        @csrf
                                                        <div class="row g-2 align-items-end">
                                                            <div class="col-md-7">
                                                                <label class="form-label" for="notes-{{ $leave->id }}">
                                                                    {{ __('Note for the family') }}
                                                                </label>
                                                                <input id="notes-{{ $leave->id }}" type="text" name="notes" maxlength="500"
                                                                       class="form-control form-control-sm"
                                                                       placeholder="{{ __('Optional. Shown in the student portal.') }}">
                                                            </div>
                                                            <div class="col-md-5">
                                                                <div class="d-flex gap-2">
                                                                    <button type="submit" name="decision" value="approved"
                                                                            class="btn btn-sm btn-primary flex-fill">
                                                                        <i class="bi bi-check2"></i>{{ __('Approve') }}
                                                                    </button>
                                                                    <button type="submit" name="decision" value="rejected"
                                                                            class="btn btn-sm btn-light-soft text-danger flex-fill">
                                                                        <i class="bi bi-x-lg"></i>{{ __('Reject') }}
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="text-muted-2 mt-2" style="font-size:.72rem;">
                                                            {{ __('The decision is recorded and the student is told. A decision can be changed again from this row.') }}
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endcan
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($leaves->hasPages())
                <div class="table-footer">{{ $leaves->links() }}</div>
            @endif
        @endif
    </section>
@endsection
