@extends('layouts.app')

@section('title', __('Generate a fee invoice'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-receipt-cutoff"></i>{{ __('Finance') }}</span>
            <h1 class="page-title">{{ __('Generate a fee invoice') }}</h1>
            <p class="page-subtitle">{{ __('Search a student, check the fees that apply to them, then issue the invoice.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('fees.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Fees') }}</a>
            <a href="{{ route('fees.collect') }}" class="btn btn-light-soft"><i class="bi bi-cash-stack"></i>{{ __('Collect fees') }}</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Find a student') }}</h2>

                <form method="GET" action="{{ route('fees.create') }}" class="mt-3">
                    <input type="hidden" name="student" value="{{ $student?->id }}">
                    <input type="hidden" name="billing_period" value="{{ $billingPeriod }}">
                    <div class="mb-2">
                        <label class="form-label" for="student-search">{{ __('Student ID, name, roll or admission no') }}</label>
                        <input id="student-search" name="search" class="form-control" value="{{ $search }}" placeholder="{{ __('Type to search') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="student-session">{{ __('Session') }}</label>
                        <select id="student-session" name="session_id" class="form-select" onchange="this.form.submit()">
                            @foreach ($sessions as $option)
                                <option value="{{ $option->id }}" @selected($session?->id === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-light-soft w-100"><i class="bi bi-search"></i>{{ __('Search') }}</button>
                </form>

                <div class="list-group list-group-flush mt-3">
                    @forelse ($students as $option)
                        <a href="{{ route('fees.create', ['search' => $search, 'session_id' => $session?->id, 'student' => $option->id, 'billing_period' => $billingPeriod]) }}"
                           class="list-group-item list-group-item-action {{ $student?->id === $option->id ? 'active' : '' }}">
                            <div class="fw-600">{{ $option->full_name }}</div>
                            <div class="text-muted-2" style="font-size:.76rem;">{{ $option->student_id }}</div>
                        </a>
                    @empty
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">
                            {{ $search === '' ? __('Search by student ID, name, roll or admission number.') : __('No active student matches that search.') }}
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
                        <p class="text-muted-2 mb-0">{{ __('Search on the left, then pick the student to bill. An invoice is raised for one student at a time.') }}</p>
                    </div>
                </section>
            @elseif ($enrollment === null)
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ $student->full_name }}</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.82rem;">
                        {{ __('This student has no active enrollment in the :session session, so no invoice can be raised for them.', ['session' => $session?->name ?? '—']) }}
                    </p>
                </section>
            @else
                <section class="ui-card p-lg mb-3">
                    <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
                        <div>
                            <h2 class="card-title mb-1">{{ $student->full_name }}</h2>
                            <p class="text-muted-2 mb-0" style="font-size:.78rem;">
                                {{ $student->student_id }}
                                · {{ $enrollment->schoolClass?->name }} {{ $enrollment->section?->name }}
                                · {{ __('Roll') }} {{ $enrollment->roll_no }}
                            </p>
                        </div>
                        <span class="badge-soft is-brand">{{ $session?->name }}</span>
                    </div>
                </section>

                <form method="POST" action="{{ route('fees.generate') }}">
                    @csrf
                    <input type="hidden" name="academic_session_id" value="{{ $session->id }}">
                    <input type="hidden" name="class_id" value="{{ $enrollment->class_id }}">
                    <input type="hidden" name="section_id" value="{{ $enrollment->section_id }}">
                    <input type="hidden" name="student_ids[]" value="{{ $student->id }}">

                    <section class="ui-card p-lg mb-3">
                        <h2 class="card-title">{{ __('Fee items for this student') }}</h2>
                        <p class="text-muted-2" style="font-size:.8rem;">{{ __('Every checked line becomes one row on the invoice, at the amount shown.') }}</p>
                        @error('fee_type_ids')<div class="alert alert-danger py-2 mt-2 mb-0" style="font-size:.8rem;">{{ $message }}</div>@enderror

                        @if ($lines === [])
                            <p class="text-muted-2 mt-3 mb-0" style="font-size:.82rem;">
                                {{ __('Nothing is configured for this student yet. Add a class fee for :class or assign a fee to them directly.', ['class' => $enrollment->schoolClass?->name ?? __('the class')]) }}
                                @can('fees.manage')
                                    <a href="{{ route('fees.structures.index', ['session_id' => $session->id, 'class_id' => $enrollment->class_id]) }}" class="d-inline-block mt-2">
                                        <i class="bi bi-collection"></i>{{ __('Set the class fees') }}
                                    </a>
                                @endcan
                            </p>
                        @else
                            <div class="table-responsive mt-3">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width:44px;">
                                                <input type="checkbox" class="form-check-input" data-check-all="#student-fee-lines" aria-label="{{ __('Select all fee items') }}" checked>
                                            </th>
                                            <th>{{ __('Fee') }}</th>
                                            <th>{{ __('Based on') }}</th>
                                            <th class="text-end">{{ __('Amount') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="student-fee-lines">
                                        @php $selected = array_map('intval', (array) old('fee_type_ids', array_column($lines, 'fee_type_id'))); @endphp
                                        @foreach ($lines as $line)
                                            <tr>
                                                <td>
                                                    <input type="checkbox" class="form-check-input" name="fee_type_ids[]"
                                                           value="{{ $line['fee_type_id'] }}" id="line-{{ $line['fee_type_id'] }}"
                                                           @checked(in_array($line['fee_type_id'], $selected, true))>
                                                </td>
                                                <td>
                                                    <label for="line-{{ $line['fee_type_id'] }}" class="fw-600 mb-0">
                                                        {{ $line['fee_type_name'] !== '' ? $line['fee_type_name'] : $line['description'] }}
                                                    </label>
                                                    @if ($line['description'] !== ($line['fee_type_name'] ?: $line['description']))
                                                        <div class="text-muted-2" style="font-size:.76rem;">{{ $line['description'] }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge-soft {{ $line['source'] === 'assignment' ? 'is-brand' : 'is-muted' }}">
                                                        {{ $line['source'] === 'assignment' ? __('Student amount') : __('Class fee') }}
                                                    </span>
                                                </td>
                                                <td class="text-end">৳ {{ number_format($line['amount'], 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    <section class="ui-card p-lg mb-3">
                        <h2 class="card-title">{{ __('Invoice options') }}</h2>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label" for="billing-period">{{ __('Billing period') }}</label>
                                <input id="billing-period" type="month" name="billing_period" class="form-control" value="{{ old('billing_period', $billingPeriod) }}">
                                <div class="form-text">{{ __('One invoice per student per period. Leave blank for a one-off fee.') }}</div>
                                @error('billing_period')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="issue-date">{{ __('Issue date') }}</label>
                                <input id="issue-date" type="date" name="issue_date" class="form-control" value="{{ old('issue_date', $issueDate) }}" required>
                                @error('issue_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="due-date">{{ __('Due date') }}</label>
                                <input id="due-date" type="date" name="due_date" class="form-control" value="{{ old('due_date', $dueDate) }}" required>
                                @error('due_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="notes">{{ __('Notes') }}</label>
                                <textarea id="notes" name="notes" class="form-control" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                        @if ($lines === [])
                            <div class="alert alert-warning mt-4 mb-0 py-2" style="font-size:.82rem;" role="alert">
                                <i class="bi bi-exclamation-triangle"></i>
                                {{ __('There is nothing to bill :student yet, so this button stays off. Set a class fee for :class in :session first.', [
                                    'student' => $student->first_name.' '.$student->last_name,
                                    'class' => $enrollment->schoolClass?->name ?? __('this class'),
                                    'session' => $session->name,
                                ]) }}
                                @can('fees.manage')
                                    <a href="{{ route('fees.structures.index', ['session_id' => $session->id]) }}"><i class="bi bi-collection"></i>{{ __('Open class fees') }}</a>
                                @endcan
                            </div>
                        @endif
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary" @disabled($lines === [])>
                                <i class="bi bi-receipt-cutoff"></i>{{ __('Generate invoice') }}
                            </button>
                        </div>
                    </section>
                </form>

                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Already billed') }}</h2>
                    @if ($existing->isEmpty())
                        <p class="text-muted-2 mb-0" style="font-size:.82rem;">{{ __('No invoice has been raised for this student in this session yet.') }}</p>
                    @else
                        <div class="table-responsive mt-3">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Invoice') }}</th>
                                        <th>{{ __('Period') }}</th>
                                        <th class="text-end">{{ __('Total') }}</th>
                                        <th class="text-end">{{ __('Paid') }}</th>
                                        <th class="text-end">{{ __('Due') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($existing as $invoice)
                                        <tr>
                                            <td><a href="{{ route('fees.show', $invoice) }}">{{ $invoice->invoice_no }}</a></td>
                                            <td>{{ $invoice->billing_period ?: __('One-off') }}</td>
                                            <td class="text-end">৳ {{ number_format((float) $invoice->total, 2) }}</td>
                                            <td class="text-end">৳ {{ number_format((float) $invoice->paid_total, 2) }}</td>
                                            <td class="text-end">৳ {{ number_format((float) $invoice->due_total, 2) }}</td>
                                            <td><span class="badge-soft {{ ['issued' => 'is-warning', 'partial' => 'is-info', 'paid' => 'is-success', 'settled' => 'is-brand', 'cancelled' => 'is-danger'][$invoice->status] ?? 'is-muted' }}">{{ __(ucfirst($invoice->status)) }}</span></td>
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

@push('scripts')
    <script>
        document.querySelectorAll('[data-check-all]').forEach(function (toggle) {
            var target = document.querySelector(toggle.getAttribute('data-check-all'));
            if (!target) { return; }
            toggle.addEventListener('change', function () {
                target.querySelectorAll('input[type="checkbox"]').forEach(function (box) {
                    box.checked = toggle.checked;
                });
            });
        });
    </script>
@endpush
