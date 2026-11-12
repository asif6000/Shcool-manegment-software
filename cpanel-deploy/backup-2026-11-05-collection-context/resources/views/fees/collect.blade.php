@extends('layouts.app')

@section('title', __('Collect fees'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-cash-stack"></i>{{ __('Fees') }}</span>
            <h1 class="page-title">{{ __('Fee collection desk') }}</h1>
            <p class="page-subtitle">{{ __('Find the student, tick the fees actually being paid and record the money received.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('fees.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Fees') }}</a>
            <a href="{{ route('fees.receipts') }}" class="btn btn-light-soft"><i class="bi bi-upc-scan"></i>{{ __('Receipts') }}</a>
        </div>
    </div>

    @unless ($unrestricted)
        <div class="ui-card p-3 mb-3">
            <p class="mb-0" style="font-size:.82rem;">
                <i class="bi bi-info-circle"></i>
                {{ __('You collect for the students on your own class list only.') }}
            </p>
        </div>
    @endunless

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Find a student') }}</h2>
                <form method="GET" action="{{ route('fees.collect') }}" class="mt-3">
                    <input type="hidden" name="session_id" value="{{ $session?->id }}">
                    <div class="mb-2">
                        <label class="form-label" for="collect-search">{{ __('Student ID, name or roll') }}</label>
                        <input id="collect-search" name="search" class="form-control" value="{{ $search }}" placeholder="{{ __('Type to search') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="collect-session">{{ __('Session') }}</label>
                        <select id="collect-session" name="session_id" class="form-select" onchange="this.form.submit()">
                            @foreach ($sessions as $option)
                                <option value="{{ $option->id }}" @selected($session?->id === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-light-soft w-100"><i class="bi bi-search"></i>{{ __('Search') }}</button>
                </form>

                <div class="list-group list-group-flush mt-3">
                    @forelse ($students as $option)
                        <a href="{{ route('fees.collect', ['search' => $search, 'session_id' => $session?->id, 'student' => $option->id]) }}"
                           class="list-group-item list-group-item-action {{ $student?->id === $option->id ? 'active' : '' }}">
                            <div class="fw-600">{{ $option->full_name }}</div>
                            <div class="text-muted-2" style="font-size:.76rem;">{{ $option->student_id }}</div>
                        </a>
                    @empty
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">
                            {{ $search === '' ? __('Search by student ID, name or roll number.') : __('No student by that name, ID or roll is open to you.') }}
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
                        <p class="text-muted-2 mb-0">{{ __('Search on the left, then pick the student whose money you are receiving.') }}</p>
                    </div>
                </section>
            @elseif ($invoices->isEmpty())
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ $student->full_name }}</h2>
                    <p class="text-muted-2" style="font-size:.8rem;">{{ __('No fee is waiting to be collected for this student in :session.', ['session' => $session?->name ?? __('any session')]) }}</p>
                    <a href="{{ route('fees.create', ['student' => $student->id, 'session_id' => $session?->id]) }}" class="btn btn-light-soft"><i class="bi bi-file-earmark-plus"></i>{{ __('Generate an invoice') }}</a>
                </section>
            @else
                <section class="ui-card p-lg">
                    <div class="d-flex flex-wrap gap-3 align-items-start justify-content-between">
                        <div>
                            <h2 class="card-title mb-1">{{ $student->full_name }}</h2>
                            <p class="text-muted-2 mb-0" style="font-size:.78rem;">
                                {{ $student->student_id }}
                                @foreach ($invoices as $invoice)
                                    @if ($loop->first && $invoice->enrollment)
                                        · {{ $invoice->enrollment->schoolClass?->name }} {{ $invoice->enrollment->section?->name }}
                                        · {{ __('Roll') }} {{ $invoice->enrollment->roll_no }}
                                    @endif
                                @endforeach
                            </p>
                        </div>
                        <span class="badge-soft is-warning">{{ __('Session') }}: {{ $session?->name ?? '—' }}</span>
                    </div>

                    <form method="POST" action="{{ route('fees.collect.store') }}" class="mt-4" id="collect-form">
                        @csrf
                        <input type="hidden" name="student_id" value="{{ $student->id }}">
                        <input type="hidden" name="academic_session_id" value="{{ $session?->id }}">
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                        <div class="table-responsive">
                            <table class="table table-aligned align-middle">
                                <thead>
                                    <tr>
                                        <th style="width:2.2rem;"></th>
                                        <th>{{ __('Fee record') }}</th>
                                        <th>{{ __('Configured fee') }}</th>
                                        <th class="text-end">{{ __('Amount received') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invoices as $invoice)
                                        <tr>
                                            <td>
                                                <input class="form-check-input" type="checkbox"
                                                       name="picked[]" value="{{ $invoice->id }}"
                                                       data-invoice="{{ $invoice->id }}"
                                                       data-max="{{ number_format((float) $invoice->due_total, 2, '.', '') }}"
                                                       @checked(in_array($invoice->id, (array) old('picked', $loop->index < 1 ? [$invoice->id] : [])))>
                                            </td>
                                            <td>
                                                <div class="fw-600">{{ $invoice->invoice_no }}</div>
                                                <div class="text-muted-2" style="font-size:.75rem;">
                                                    {{ $invoice->billing_period ?? $invoice->items->pluck('description')->filter()->implode(', ') ?: __('Fee') }}
                                                    · {{ __('due') }} {{ $invoice->due_date?->format('d M Y') }}
                                                </div>
                                            </td>
                                            <td>{{ \App\Support\Money::format($invoice->total) }}</td>
                                            <td class="text-end">
                                                <input type="number" step="0.01" min="0" max="{{ number_format((float) $invoice->due_total, 2, '.', '') }}"
                                                       name="amounts[{{ $invoice->id }}]"
                                                       class="form-control form-control-sm text-end line-amount"
                                                       data-invoice="{{ $invoice->id }}"
                                                       value="{{ old('amounts.'.$invoice->id, number_format((float) $invoice->due_total, 2, '.', '')) }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @error('lines')<div class="alert alert-danger py-2" style="font-size:.8rem;">{{ $message }}</div>@enderror

                        <div class="row g-3 mt-2">
                            <div class="col-md-4">
                                <label class="form-label" for="collect-method">{{ __('Method') }}</label>
                                <select id="collect-method" name="method" class="form-select" required>
                                    @foreach ($methods as $method)
                                        <option value="{{ $method }}" @selected(old('method', 'cash') === $method)>{{ __(ucfirst($method)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="collect-date">{{ __('Received on') }}</label>
                                <input id="collect-date" name="paid_at" type="date" class="form-control"
                                       value="{{ old('paid_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                                @error('paid_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="collect-reference">{{ __('Provider reference') }}</label>
                                <input id="collect-reference" name="transaction_ref" class="form-control" maxlength="100"
                                       value="{{ old('transaction_ref') }}" placeholder="{{ __('bKash TrxID, cheque no, etc.') }}">
                                @error('transaction_ref')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="collect-bank">{{ __('Received into') }}</label>
                                <select id="collect-bank" name="bank_account_id" class="form-select">
                                    <option value="">{{ __('Not a bank account') }}</option>
                                    @foreach ($bankAccounts as $account)
                                        <option value="{{ $account->id }}" @selected((string) old('bank_account_id') === (string) $account->id)>{{ $account->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="collect-notes">{{ __('Remarks') }}</label>
                                <input id="collect-notes" name="notes" class="form-control" maxlength="500" value="{{ old('notes') }}">
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="collect-settle" name="settle_in_full" @checked(old('settle_in_full'))>
                                    <label class="form-check-label" for="collect-settle">
                                        {{ __('Settle these fees in full') }}
                                        <span class="d-block text-muted-2" style="font-size:.76rem;">
                                            {{ __('Use this when the money received is deliberately short of the configured fee. Only the cash actually handed over is booked, one receipt is issued and the difference never returns as a due.') }}
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mt-4">
                            <div>
                                <div class="text-muted-2" style="font-size:.75rem;">{{ __('Total money received') }}</div>
                                <div class="fs-4 fw-700" id="collect-total">৳ 0.00</div>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-printer"></i>{{ __('Record collection & print receipt') }}</button>
                        </div>
                    </form>
                </section>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('collect-form');
                if (! form) return;

                const total = document.getElementById('collect-total');

                function refresh() {
                    let sum = 0;
                    form.querySelectorAll('[data-invoice]').forEach((box) => {
                        if (! box.classList.contains('line-amount')) return;
                        const toggle = form.querySelector('input[type="checkbox"][data-invoice="' + box.dataset.invoice + '"]');
                        if (toggle && toggle.checked) {
                            sum += parseFloat(box.value || '0') || 0;
                        }
                    });
                    total.textContent = '৳ ' + sum.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }

                form.addEventListener('input', refresh);
                form.addEventListener('change', refresh);
                refresh();
            })();
        </script>
    @endpush
@endsection
