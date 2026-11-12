@extends(auth()->user()?->hasRole('teacher') && ! auth()->user()?->hasAnyRole(['school_admin', 'principal', 'super_admin']) ? 'layouts.teacher' : 'layouts.app')

@section('title', __('My leave'))

@section('content')
    @php
        $statusBadges = [
            'pending' => ['is-warning', __('Awaiting decision')],
            'approved' => ['is-success', __('Approved')],
            'rejected' => ['is-danger', __('Rejected')],
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-calendar2-x"></i>{{ __('Account') }}</span>
            <h1 class="page-title">{{ __('My leave') }}</h1>
            <p class="page-subtitle">{{ __('Ask the office before you stay away. Every request you send is listed here with the decision.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('teacher.routine') }}" class="btn btn-light-soft">
                <i class="bi bi-calendar3"></i>{{ __('My routine') }}
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <section class="ui-card p-lg">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">{{ __('New request') }}</h2>
                        <p class="card-subtitle">{{ __('Dates cannot overlap with a request already waiting or approved.') }}</p>
                    </div>
                    @if ($pending > 0)
                        <span class="badge-soft is-warning">{{ trans_choice(':count request is waiting|:count requests are waiting', $pending, ['count' => $pending]) }}</span>
                    @endif
                </div>

                @if ($profile === null)
                    <div class="alert alert-secondary mt-3 mb-0" role="alert">
                        <i class="bi bi-info-circle"></i>
                        {{ __('This login is not linked to a staff profile yet, so the office has to record leave for you.') }}
                    </div>
                @elseif ($leaveTypes->isEmpty())
                    <div class="alert alert-secondary mt-3 mb-0" role="alert">
                        <i class="bi bi-info-circle"></i>
                        {{ __('The school has not set up leave types yet, so there is nothing to apply for. The office can add them under Teachers & staff.') }}
                    </div>
                @else
                    <form method="POST" action="{{ route('teacher.leave.store') }}" class="mt-3" novalidate>
                        @csrf

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="leave_type_id">{{ __('Leave type') }}<span class="required">*</span></label>
                                <select class="form-select @error('leave_type_id') is-invalid @enderror" id="leave_type_id" name="leave_type_id" required>
                                    <option value="">{{ __('Choose a leave type') }}</option>
                                    @foreach ($leaveTypes as $type)
                                        <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>
                                            {{ $type->name }}@if ($type->max_days_per_year) · {{ __(':count days a year', ['count' => $type->max_days_per_year]) }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('leave_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="start_date">{{ __('From') }}<span class="required">*</span></label>
                                <input class="form-control @error('start_date') is-invalid @enderror" type="date" id="start_date" name="start_date"
                                       value="{{ old('start_date') }}" max="{{ now()->addYear()->toDateString() }}" required>
                                @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="end_date">{{ __('To') }}<span class="required">*</span></label>
                                <input class="form-control @error('end_date') is-invalid @enderror" type="date" id="end_date" name="end_date"
                                       value="{{ old('end_date') }}" min="{{ old('start_date') ?: now()->subWeek()->toDateString() }}" required>
                                @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="reason">{{ __('Reason') }}<span class="required">*</span></label>
                                <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" rows="4" maxlength="1000" required
                                          placeholder="{{ __('Who covers your periods, and how the office can reach you.') }}">{{ old('reason') }}</textarea>
                                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-4">
                            <i class="bi bi-send"></i>{{ __('Send request') }}
                        </button>
                        <p class="text-muted-2 mt-3 mb-0" style="font-size:.76rem;">
                            <i class="bi bi-info-circle"></i>{{ __('The office decides in the staff leave register. You are told as soon as it is answered.') }}
                        </p>
                    </form>
                @endif
            </section>

            <div class="mini-stats mt-3">
                <div class="mini-stat">
                    <span class="value">{{ $pending }}</span>
                    <span class="label">{{ __('Waiting for a decision') }}</span>
                </div>
                <div class="mini-stat">
                    <span class="value">{{ number_format($daysThisYear, 1) }}</span>
                    <span class="label">{{ __('Approved days in :year', ['year' => now()->format('Y')]) }}</span>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('My requests') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Newest leave dates first.') }}</p>
                    </div>
                    <form class="filter-form" method="GET" action="{{ route('teacher.leave.index') }}" data-auto-submit>
                        <select class="form-select form-select-sm" name="status" aria-label="{{ __('Filter by status') }}">
                            <option value="">{{ __('Every status') }}</option>
                            @foreach ($statusBadges as $value => [$unused, $label])
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                @if ($rows->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                        <h3>{{ __('No leave requests') }}</h3>
                        <p>{{ $status === null
                            ? __('Requests you send from the form on the left appear here with the office decision.')
                            : __('Nothing matches that status filter.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Leave') }}</th>
                                    <th>{{ __('Dates') }}</th>
                                    <th>{{ __('Days') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $leave)
                                    @php [$badgeClass, $badgeLabel] = $statusBadges[$leave->status] ?? ['', __(ucfirst($leave->status))]; @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $leave->leaveType?->name ?? '—' }}</div>
                                            <div class="text-muted-2" style="font-size:.74rem;max-width:34ch;">
                                                {{ \Illuminate\Support\Str::limit($leave->reason, 90) }}
                                            </div>
                                            @if ($leave->status === 'rejected' && $leave->rejection_reason)
                                                <div class="text-danger mt-1" style="font-size:.74rem;">
                                                    <i class="bi bi-chat-left-text"></i>{{ $leave->rejection_reason }}
                                                </div>
                                            @elseif ($leave->status === 'approved' && $leave->approver)
                                                <div class="text-muted-2" style="font-size:.74rem;">
                                                    {{ __('Approved by :name', ['name' => $leave->approver->name]) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td style="font-size:.8rem;white-space:nowrap;">
                                            {{ $leave->start_date?->format('d M Y') ?? '—' }}
                                            → {{ $leave->end_date?->format('d M Y') ?? '—' }}
                                            <div class="text-muted-2" style="font-size:.72rem;">
                                                {{ __('Sent') }} {{ $leave->created_at?->format('d M Y') }}
                                            </div>
                                        </td>
                                        <td>{{ number_format((float) $leave->total_days, 1) }}</td>
                                        <td>
                                            <span class="badge-soft {{ $badgeClass }}"><span class="dot"></span>{{ $badgeLabel }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer">{{ $rows->links() }}</div>
                @endif
            </section>
        </div>
    </div>
@endsection
