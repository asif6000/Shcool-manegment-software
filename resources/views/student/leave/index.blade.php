@extends('layouts.student')

@section('title', __('Leave requests'))

@section('content')
    @php
        $rows = $rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $rows : collect();
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
            'cancelled' => ['', __('Withdrawn')],
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-calendar2-x"></i>{{ __('Attendance') }}</span>
            <h1 class="page-title">{{ __('Leave requests') }}</h1>
            <p class="page-subtitle">{{ __('Ask the school office for leave before you stay away. The office records the decision here.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('student.attendance') }}" class="btn btn-light-soft">
                <i class="bi bi-calendar2-check"></i>{{ __('My attendance') }}
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <section class="ui-card p-lg">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">{{ __('New request') }}</h2>
                        <p class="card-subtitle">{{ __('Dates cannot overlap with a request already waiting.') }}</p>
                    </div>
                    @if ($pending > 0)
                        <span class="badge-soft is-warning">{{ trans_choice(':count request is waiting|:count requests are waiting', $pending, ['count' => $pending]) }}</span>
                    @endif
                </div>

                <form method="POST" action="{{ route('student.leave.store') }}" class="mt-3" novalidate>
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="from_date">{{ __('From') }}<span class="required">*</span></label>
                            <input class="form-control @error('from_date') is-invalid @enderror" type="date" id="from_date" name="from_date" value="{{ old('from_date') }}" required>
                            @error('from_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="to_date">{{ __('To') }}<span class="required">*</span></label>
                            <input class="form-control @error('to_date') is-invalid @enderror" type="date" id="to_date" name="to_date" value="{{ old('to_date') }}" required>
                            @error('to_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="leave_type">{{ __('Reason type') }}<span class="required">*</span></label>
                            <select class="form-select @error('leave_type') is-invalid @enderror" id="leave_type" name="leave_type" required>
                                @foreach ($types as $type)
                                    <option value="{{ $type }}" @selected(old('leave_type') === $type)>{{ $typeLabels[$type] ?? ucfirst($type) }}</option>
                                @endforeach
                            </select>
                            @error('leave_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="reason">{{ __('Details') }}<span class="required">*</span></label>
                            <textarea class="form-control @error('reason') is-invalid @enderror" id="reason" name="reason" rows="4" maxlength="1000" required
                                      placeholder="{{ __('Explain briefly why you will be absent.') }}">{{ old('reason') }}</textarea>
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="contact_phone">{{ __('Guardian phone for that day') }}</label>
                            <input class="form-control @error('contact_phone') is-invalid @enderror" type="text" id="contact_phone" name="contact_phone"
                                   value="{{ old('contact_phone', $guardianPhone) }}" maxlength="30" inputmode="tel">
                            @error('contact_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-4">
                        <i class="bi bi-send"></i>{{ __('Send request') }}
                    </button>
                    <p class="text-muted-2 mt-3 mb-0" style="font-size:.76rem;">
                        <i class="bi bi-info-circle"></i>{{ __('The office normally answers within a working day. You can withdraw a request while it is still waiting.') }}
                    </p>
                </form>
            </section>
        </div>

        <div class="col-lg-7">
            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('My requests') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Newest leave dates first.') }}</p>
                    </div>
                </div>

                @if ($rows->isEmpty())
                    <div class="empty-state py-4">
                        <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                        <h3>{{ __('No leave requests') }}</h3>
                        <p>{{ __('Requests you send from the form on the left appear here with the office decision.') }}</p>
                    </div>
                @else
                    <div class="notice-list">
                        @foreach ($rows as $leave)
                            @php [$badgeClass, $badgeLabel] = $statusBadges[$leave->status] ?? ['', __(ucfirst($leave->status))]; @endphp
                            <article class="notice-item">
                                <div class="notice-icon {{ $leave->status === 'approved' ? 'text-success' : '' }}">
                                    <i class="bi bi-calendar2-x"></i>
                                </div>
                                <div style="min-width:0;">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <h3 class="notice-title mb-0">{{ $typeLabels[$leave->leave_type] ?? $leave->typeLabel() }}</h3>
                                        <span class="badge-soft {{ $badgeClass }}"><span class="dot"></span>{{ $badgeLabel }}</span>
                                    </div>
                                    <p class="notice-meta mb-2">
                                        {{ $leave->from_date?->format('d M Y') ?? '—' }} → {{ $leave->to_date?->format('d M Y') ?? '—' }}
                                        · {{ trans_choice(':count day|:count days', $leave->daysRequested(), ['count' => $leave->daysRequested()]) }}
                                        · {{ __('Sent') }} {{ $leave->created_at?->format('d M Y') }}
                                    </p>
                                    <p class="text-body mb-0" style="font-size:.84rem;white-space:pre-line;">{{ $leave->reason }}</p>

                                    @if ($leave->decision_notes)
                                        <div class="alert alert-secondary mt-3 mb-0 py-2 px-3" style="font-size:.8rem;">
                                            <strong class="d-block">{{ __('Office notes') }}</strong>
                                            {{ $leave->decision_notes }}
                                            @if ($leave->decider)
                                                <span class="text-muted-2">— {{ $leave->decider->name }}, {{ $leave->decided_at?->format('d M Y, h:i A') }}</span>
                                            @endif
                                        </div>
                                    @endif

                                    @if ($leave->contact_phone)
                                        <div class="text-muted-2 mt-2" style="font-size:.74rem;">
                                            <i class="bi bi-telephone"></i>{{ $leave->contact_phone }}
                                        </div>
                                    @endif
                                </div>
                                <div class="d-flex align-items-start">
                                    @if ($leave->status === 'pending')
                                        <form method="POST" action="{{ route('student.leave.cancel', $leave->id) }}"
                                              data-confirm="{{ __('Withdraw this leave request?') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-light-soft text-danger">
                                                <i class="bi bi-x-circle"></i>{{ __('Withdraw') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    @if ($rows->hasPages())
                        <div class="p-3 border-top">{{ $rows->links() }}</div>
                    @endif
                @endif
            </section>
        </div>
    </div>
@endsection
