@extends('layouts.app')

@section('title', __('New invoice for a student'))

@section('content')
    @php
        $statusClasses = [
            'issued' => 'is-warning',
            'partial' => 'is-info',
            'paid' => 'is-success',
            'settled' => 'is-brand',
            'cancelled' => 'is-danger',
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-receipt-cutoff"></i>{{ __('Finance') }}</span>
            <h1 class="page-title">{{ __('New invoice for a student') }}</h1>
            <p class="page-subtitle">{{ __('Search a student, pick the session, month and fee type, enter the amount, then generate.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('fees.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Fees') }}</a>
            <a href="{{ route('fees.create') }}" class="btn btn-light-soft"><i class="bi bi-collection"></i>{{ __('Classwise billing') }}</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Find a student') }}</h2>

                <form method="GET" action="{{ route('fees.single') }}" class="mt-3">
                    <div class="mb-2">
                        <label class="form-label" for="student-search">{{ __('Student ID, name or admission no') }}</label>
                        <input id="student-search" name="search" class="form-control" value="{{ $search }}" placeholder="{{ __('Type to search') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="student-session">{{ __('Academic session') }}</label>
                        <select id="student-session" name="session_id" class="form-select" onchange="this.form.submit()">
                            @foreach ($sessions as $option)
                                <option value="{{ $option->id }}" @selected($session?->id === $option->id)>{{ $option->name }}@if($option->is_current) · {{ __('Current') }}@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-light-soft w-100"><i class="bi bi-search"></i>{{ __('Search') }}</button>
                </form>

                <div class="list-group list-group-flush mt-3" style="max-height:340px;overflow:auto;">
                    @forelse ($students as $option)
                        <a href="{{ route('fees.single', ['search' => $search, 'session_id' => $session?->id, 'student' => $option->id]) }}"
                           class="list-group-item list-group-item-action py-2 {{ $student?->id === $option->id ? 'active' : '' }}">
                            <div class="fw-600">{{ $option->enrollments->first()?->schoolClass?->name ?? __('Not enrolled') }} · {{ $option->full_name }}</div>
                            <div class="text-muted-2" style="font-size:.74rem;">{{ $option->student_id }}@if($option->enrollments->first()?->roll_no) · {{ __('Roll') }} {{ $option->enrollments->first()?->roll_no }}@endif</div>
                        </a>
                    @empty
                        <p class="text-muted-2 mb-0 py-2" style="font-size:.8rem;">
                            {{ $search === '' ? __('Search by student ID, name or admission number.') : __('No student matches that search.') }}
                        </p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="col-lg-8">
            @if ($student === null)
                <section class="ui-card p-lg">
                    <div class="empty-state py-5">
                        <i class="bi bi-person-search"></i>
                        <h2 class="card-title mt-2">{{ __('No student selected') }}</h2>
                        <p class="text-muted-2 mb-0">{{ __('Search on the left and pick the student to invoice.') }}</p>
                    </div>
                </section>
            @else
                <section class="ui-card p-lg mb-3">
                    <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
                        <div>
                            <h2 class="card-title mb-1">{{ $student->full_name }}</h2>
                            <p class="text-muted-2 mb-0" style="font-size:.78rem;">
                                {{ $student->student_id }}
                                · {{ $enrollment?->schoolClass?->name ?? __('No active enrollment in this session') }}
                                {{ $enrollment?->section?->name }}
                                @if ($enrollment?->roll_no) · {{ __('Roll') }} {{ $enrollment->roll_no }}@endif
                            </p>
                        </div>
                        <span class="badge-soft is-brand">{{ $session?->name }}</span>
                    </div>
                </section>

                @if ($enrollment === null)
                    <section class="ui-card p-lg">
                        <p class="text-muted-2 mb-0" style="font-size:.82rem;">
                            {{ __('This student has no active enrollment in the :session session, so no invoice can be raised for them.', ['session' => $session?->name ?? '—']) }}
                        </p>
                    </section>
                @else
                    <form method="POST" action="{{ route('fees.single.store') }}">
                        @csrf
                        <input type="hidden" name="academic_session_id" value="{{ $session->id }}">
                        <input type="hidden" name="student_id" value="{{ $student->id }}">

                        <section class="ui-card p-lg">
                            <h2 class="card-title">{{ __('Invoice details') }}</h2>
                            <div class="row g-3 mt-1">
                                <div class="col-md-4">
                                    <label class="form-label" for="invoice-month">{{ __('Month') }}</label>
                                    <select id="invoice-month" name="month" class="form-select @error('month') is-invalid @enderror" required>
                                        @forelse ($months as $index => $name)
                                            <option value="{{ $index }}" @selected((string) old('month', $currentMonth) === (string) $index)>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">{{ __('Monthly fees bill to this month. One-time fees bill once per session.') }}</div>
                                    @error('month')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="invoice-fee-type">{{ __('Fee type') }}</label>
                                    <select id="invoice-fee-type" name="fee_type_id" class="form-select @error('fee_type_id') is-invalid @enderror" required>
                                        <option value="">{{ __('Choose a fee type') }}</option>
                                        @foreach ($feeTypes as $feeType)
                                            <option value="{{ $feeType->id }}" @selected((string) old('fee_type_id') === (string) $feeType->id)>
                                                {{ $feeType->name }}@if($feeType->frequency) · {{ __($feeType->frequency) }}@endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('fee_type_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="invoice-amount">{{ __('Invoice amount') }}</label>
                                    <div class="input-group">
                                        <span class="input-group-text">৳</span>
                                        <input id="invoice-amount" name="amount" type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount') }}" required>
                                    </div>
                                    @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-receipt-cutoff"></i>{{ __('Generate invoice') }}</button>
                            </div>
                        </section>
                    </form>
                @endif

                <section class="ui-card p-lg mt-3">
                    <h2 class="card-title">{{ __('Already billed in this session') }}</h2>
                    @if ($existing->isEmpty())
                        <p class="text-muted-2 mb-0" style="font-size:.82rem;">{{ __('No invoice has been raised for this student in this session yet.') }}</p>
                    @else
                        <div class="table-responsive mt-3">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Invoice') }}</th>
                                        <th>{{ __('Period') }}</th>
                                        <th>{{ __('Issued') }}</th>
                                        <th class="text-end">{{ __('Total') }}</th>
                                        <th class="text-end">{{ __('Paid') }}</th>
                                        <th class="text-end">{{ __('Due') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($existing as $invoice)
                                        <tr>
                                            <td><a href="{{ route('fees.show', $invoice) }}" class="fw-600">{{ $invoice->invoice_no }}</a></td>
                                            <td>{{ $invoice->billing_period ?: __('One-off') }}</td>
                                            <td>{{ $invoice->issue_date?->format('d M Y') ?? '—' }}</td>
                                            <td class="text-end">৳ {{ number_format((float) $invoice->total, 2) }}</td>
                                            <td class="text-end">৳ {{ number_format((float) $invoice->paid_total, 2) }}</td>
                                            <td class="text-end">৳ {{ number_format((float) $invoice->due_total, 2) }}</td>
                                            <td><span class="badge-soft {{ $statusClasses[$invoice->status] ?? 'is-muted' }}">{{ __(ucfirst($invoice->status)) }}</span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif
        </div>
    </div>
@endsection
