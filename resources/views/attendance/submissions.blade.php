@extends('layouts.app')

@section('title', $reviews ? __('Attendance approvals') : __('My attendance submissions'))

@section('content')
    @php
        $statusClasses = [
            \App\Models\AttendanceSubmission::STATUS_SUBMITTED => 'is-warning',
            \App\Models\AttendanceSubmission::STATUS_APPROVED => 'is-success',
            \App\Models\AttendanceSubmission::STATUS_REJECTED => 'is-danger',
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-clipboard-check"></i>{{ __('Attendance') }}</span>
            <h1 class="page-title">{{ $reviews ? __('Attendance approvals') : __('My submissions') }}</h1>
            <p class="page-subtitle">
                @if ($reviews)
                    {{ __('Registers a teacher has handed over. Approving locks the day; sending it back asks for another pass.') }}
                @else
                    {{ __('Every day you hand over, and what the office decided about it.') }}
                @endif
            </p>
        </div>
        <div class="page-actions">
            @can('attendance.view')
                <a href="{{ route('attendance.students.index') }}" class="btn btn-light-soft"><i class="bi bi-journal-check"></i>{{ __('Mark attendance') }}</a>
            @endcan
            @can('attendance.report')
                <a href="{{ route('attendance.students.report') }}" class="btn btn-light-soft"><i class="bi bi-graph-up"></i>{{ __('Monthly report') }}</a>
            @endcan
        </div>
    </div>

    @include('attendance.partials.tabs', ['active' => 'submissions'])

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('attendance.submissions.index') }}" class="filter-form" role="search">
                <div class="filter-select">
                    <label class="visually-hidden" for="status">{{ __('Status') }}</label>
                    <select id="status" name="status" class="form-select form-select-sm" data-auto-submit>
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach (\App\Models\AttendanceSubmission::STATUSES as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ __(ucfirst($option)) }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($reviews && $sessions->isNotEmpty())
                    <div class="filter-select">
                        <label class="visually-hidden" for="session_id">{{ __('Session') }}</label>
                        <select id="session_id" name="session_id" class="form-select form-select-sm" data-auto-submit>
                            <option value="">{{ __('All sessions') }}</option>
                            @foreach ($sessions as $option)
                                <option value="{{ $option->id }}" @selected($sessionId === (int) $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <noscript><button type="submit" class="btn btn-primary btn-sm">{{ __('Filter') }}</button></noscript>
            </form>
        </div>

        @if ($submissions->isEmpty())
            <div class="empty-state">
                <span class="empty-icon"><i class="bi bi-inbox"></i></span>
                <h2 class="h6 mb-1">{{ __('Nothing here yet') }}</h2>
                <p class="mb-3">{{ $reviews
                    ? __('No register has been handed over yet. Submitted days appear here for a decision.')
                    : __('You have not submitted an attendance register yet. Mark a class and hand it over from the register screen.') }}</p>
                @can('attendance.view')
                    <a href="{{ route('attendance.students.index') }}" class="btn btn-primary btn-sm">{{ __('Open the register') }}</a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Register') }}</th>
                            @if ($reviews)
                                <th>{{ __('Submitted by') }}</th>
                            @endif
                            <th class="text-end">{{ __('Present') }}</th>
                            <th class="text-end">{{ __('Absent') }}</th>
                            <th class="text-end">{{ __('Late') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($submissions as $submission)
                            <tr>
                                <td>
                                    <a href="{{ route('attendance.students.index', [
                                        'session_id' => $submission->academic_session_id,
                                        'class_id' => $submission->class_id,
                                        'section_id' => $submission->section_id,
                                        'date' => $submission->attendance_date->toDateString(),
                                    ]) }}" class="text-body fw-semibold text-decoration-none">
                                        {{ $submission->attendance_date->format('d M Y') }}
                                    </a>
                                    <div class="text-muted-2" style="font-size:.78rem;">{{ $submission->academicSession?->name }}</div>
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ $submission->registerLabel() }}</span>
                                    <div class="text-muted-2" style="font-size:.78rem;">
                                        {{ trans_choice(':count student|:count students', $submission->student_count, ['count' => $submission->student_count]) }}
                                        @if ($submission->revision_count > 0)
                                            · {{ trans_choice('1 edit|:count edits', $submission->revision_count, ['count' => $submission->revision_count]) }}
                                        @endif
                                    </div>
                                </td>
                                @if ($reviews)
                                    <td>
                                        {{ $submission->submittedBy?->name ?? __('—') }}
                                        <div class="text-muted-2" style="font-size:.78rem;">
                                            {{ $submission->submitted_at?->diffForHumans() }}
                                        </div>
                                    </td>
                                @endif
                                <td class="text-end">{{ $submission->present_count }}</td>
                                <td class="text-end">{{ $submission->absent_count }}</td>
                                <td class="text-end">{{ $submission->late_count }}</td>
                                <td>
                                    <span class="badge-soft {{ $statusClasses[$submission->status] ?? '' }}">{{ $submission->statusLabel() }}</span>
                                    @if ($submission->review_note)
                                        <div class="text-muted-2 mt-1" style="font-size:.76rem; max-width:16rem;">{{ $submission->review_note }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($reviews && $submission->isAwaitingReview())
                                        <div class="d-inline-flex gap-1">
                                            <form method="POST" action="{{ route('attendance.submissions.decide', [$submission, 'approve']) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="icon-action text-success" title="{{ __('Approve') }}" aria-label="{{ __('Approve attendance for :register', ['register' => $submission->registerLabel()]) }}">
                                                    <i class="bi bi-check2-circle"></i>
                                                </button>
                                            </form>
                                            <button type="button" class="icon-action text-danger" data-bs-toggle="modal" data-bs-target="#submissionReject{{ $submission->id }}"
                                                    title="{{ __('Send back') }}" aria-label="{{ __('Send back attendance for :register', ['register' => $submission->registerLabel()]) }}">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-muted-2" style="font-size:.78rem;">
                                            {{ $submission->reviewedBy ? $submission->reviewedBy->name.' · '.$submission->reviewed_at?->format('d M') : __('—') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $submissions->firstItem(), 'to' => $submissions->lastItem(), 'total' => $submissions->total()]) }}</span>
                {{ $submissions->links() }}
            </div>
        @endif
    </section>

    @if ($reviews)
        @foreach ($submissions as $submission)
            @if ($submission->isAwaitingReview())
                <div class="modal fade" id="submissionReject{{ $submission->id }}" tabindex="-1" aria-labelledby="submissionRejectLabel{{ $submission->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <form method="POST" action="{{ route('attendance.submissions.decide', [$submission, 'reject']) }}" class="modal-content">
                            @csrf
                            <div class="modal-header">
                                <h2 class="modal-title fs-5" id="submissionRejectLabel{{ $submission->id }}">{{ __('Send the register back') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-1">
                                    <span class="fw-semibold">{{ $submission->registerLabel() }}</span>
                                    · {{ $submission->attendance_date->format('d M Y') }}
                                </p>
                                <p class="text-muted-2 mb-3" style="font-size:.8rem;">
                                    {{ __(':teacher can mark the day again after a reason is given. It returns to this queue when resubmitted.', [
                                        'teacher' => $submission->submittedBy?->name ?? __('The teacher'),
                                    ]) }}
                                </p>
                                <label class="form-label" for="submission-note-{{ $submission->id }}">{{ __('What needs to change?') }}</label>
                                <textarea id="submission-note-{{ $submission->id }}" name="note" class="form-control" rows="3" minlength="5" maxlength="255" required></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-danger">{{ __('Send back') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endforeach
    @endif
@endsection
