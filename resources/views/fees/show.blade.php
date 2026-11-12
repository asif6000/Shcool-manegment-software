@extends('layouts.app')

@section('title', $invoice->invoice_no)

@section('content')
    @php
        $statusClasses = [
            'issued' => 'is-warning',
            'partial' => 'is-info',
            'paid' => 'is-success',
            'settled' => 'is-brand',
            'cancelled' => 'is-danger',
        ];
        $editable = $invoice->status !== 'cancelled' && (float) $invoice->paid_total <= 0;
        $postedPayments = $invoice->allocations->filter(fn ($allocation) => $allocation->payment?->status === 'posted');
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-receipt"></i>{{ __('Fee invoice') }}</span>
            <h1 class="page-title mb-1">{{ $invoice->invoice_no }}</h1>
            <p class="page-subtitle mb-0">
                {{ $invoice->academicSession?->name ?? '—' }}
                @if ($invoice->billing_period)
                    · {{ $invoice->billing_period }}
                @endif
                <span class="badge-soft {{ $statusClasses[$invoice->status] ?? '' }} ms-1">{{ __(ucfirst($invoice->status)) }}</span>
            </p>
        </div>
        <div class="page-actions no-print">
            <a href="{{ route('fees.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Fees') }}</a>
            <a href="{{ route('fees.print', ['invoice' => $invoice, 'download' => 1]) }}" class="btn btn-light-soft"><i class="bi bi-download"></i>{{ __('Download') }}</a>
            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i>{{ __('Print') }}</button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <section class="ui-card table-card mb-3">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Invoice items') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Fee amounts captured when this invoice was generated.') }}</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Description') }}</th>
                                <th class="text-end">{{ __('Qty') }}</th>
                                <th class="text-end">{{ __('Unit') }}</th>
                                <th class="text-end">{{ __('Line total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($invoice->items as $item)
                                <tr>
                                    <td>
                                        {{ $item->description }}
                                        @if ($item->feeType)
                                            <div class="text-muted-2" style="font-size:.76rem;">{{ $item->feeType->name }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                                    <td class="text-end">৳ {{ number_format((float) $item->unit_amount, 2) }}</td>
                                    <td class="text-end">৳ {{ number_format((float) $item->line_total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-lg">
                    <dl class="detail-list mb-0">
                        <div><dt>{{ __('Subtotal') }}</dt><dd>৳ {{ number_format((float) $invoice->subtotal, 2) }}</dd></div>
                        @foreach ($invoice->discounts as $discount)
                            <div>
                                <dt>{{ __('Discount') }} · {{ $discount->name }}</dt>
                                <dd class="text-success">− ৳ {{ number_format((float) $discount->amount, 2) }}</dd>
                            </div>
                        @endforeach
                        <div><dt>{{ __('Total') }}</dt><dd class="fw-700">৳ {{ number_format((float) $invoice->total, 2) }}</dd></div>
                        <div><dt>{{ __('Paid') }}</dt><dd>৳ {{ number_format((float) $invoice->paid_total, 2) }}</dd></div>
                        <div><dt>{{ __('Balance due') }}</dt><dd class="fw-700">৳ {{ number_format((float) $invoice->due_total, 2) }}</dd></div>
                    </dl>
                </div>
            </section>

            @can('fees.manage')
                @if ($invoice->status === 'issued' && $invoice->items->isNotEmpty())
                    <section class="ui-card p-lg mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h2 class="card-title mb-1">{{ __('Edit fee lines') }}</h2>
                                <p class="text-muted-2 mb-0" style="font-size:.8rem;">
                                    {{ __('An unpaid invoice can be corrected. Once a payment is recorded the amounts are frozen.') }}
                                </p>
                            </div>
                        </div>

                            <div class="mt-3">
                                @foreach ($invoice->items as $item)
                                    <form method="POST" action="{{ route('fees.items.update', [$invoice, $item]) }}" class="row g-2 align-items-end border-bottom py-2" style="margin-left:0;margin-right:0;">
                                        @csrf
                                        @method('PUT')
                                        <div class="col-md-7">
                                            <label class="form-label" for="desc-{{ $item->id }}">{{ __('Description') }}</label>
                                            <input id="desc-{{ $item->id }}" name="description" class="form-control form-control-sm @error('description') is-invalid @enderror" maxlength="160" value="{{ old('description', $item->description) }}" required>
                                            @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label" for="amount-{{ $item->id }}">{{ __('Amount') }}</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">৳</span>
                                                <input id="amount-{{ $item->id }}" name="amount" type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', number_format((float) $item->line_total, 2, '.', '')) }}" required>
                                                @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="btn btn-sm btn-light-soft w-100"><i class="bi bi-check-lg"></i>{{ __('Save') }}</button>
                                        </div>
                                    </form>
                                @endforeach
                            </div>
                    </section>
                @endif
            @endcan

            @can('fees.manage')
                @if ($editable)
                    <section class="ui-card p-lg mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h2 class="card-title mb-1">{{ __('Discounts') }}</h2>
                                <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Discounts can be added only while the invoice has no recorded payments.') }}</p>
                            </div>
                        </div>

                        @if ($invoice->discounts->isNotEmpty())
                            <div class="table-responsive mt-3">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Discount') }}</th>
                                            <th>{{ __('Rule') }}</th>
                                            <th class="text-end">{{ __('Amount') }}</th>
                                            <th class="text-end">{{ __('Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($invoice->discounts as $discount)
                                            <tr>
                                                <td class="fw-600">{{ $discount->name }}</td>
                                                <td>
                                                    @if ($discount->type === 'percent')
                                                        {{ rtrim(rtrim(number_format((float) $discount->value, 2), '0'), '.') }}%
                                                    @else
                                                        {{ __('Fixed') }} ৳ {{ number_format((float) $discount->value, 2) }}
                                                    @endif
                                                </td>
                                                <td class="text-end text-success">− ৳ {{ number_format((float) $discount->amount, 2) }}</td>
                                                <td class="text-end">
                                                    <form method="POST" action="{{ route('fees.discounts.destroy', [$invoice, $discount]) }}" data-confirm="{{ __('Remove :name from this invoice?', ['name' => $discount->name]) }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-light-soft text-danger"><i class="bi bi-x-lg"></i>{{ __('Remove') }}</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if ($discounts->isEmpty())
                            <p class="text-muted-2 mt-3 mb-0" style="font-size:.82rem;">{{ __('No active discounts are configured for this school.') }}</p>
                        @else
                            <form method="POST" action="{{ route('fees.discounts.store', $invoice) }}" class="row g-2 align-items-end mt-1">
                                @csrf
                                <div class="col-sm-8">
                                    <label class="form-label" for="fee-discount">{{ __('Apply a discount') }}</label>
                                    <select id="fee-discount" name="fee_discount_id" class="form-select" required>
                                        <option value="">{{ __('Choose a discount') }}</option>
                                        @foreach ($discounts as $discount)
                                            <option value="{{ $discount->id }}">
                                                {{ $discount->name }}
                                                · {{ $discount->type === 'percent' ? rtrim(rtrim(number_format((float) $discount->value, 2), '0'), '.').'%' : '৳ '.number_format((float) $discount->value, 2) }}
                                                @if ($discount->feeType) ({{ $discount->feeType->name }}) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('fee_discount_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-sm-4">
                                    <button type="submit" class="btn btn-light-soft w-100"><i class="bi bi-plus-lg"></i>{{ __('Apply') }}</button>
                                </div>
                            </form>
                        @endif
                    </section>
                @endif
            @endcan

            <section class="ui-card table-card mb-3">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Payments') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Every posted payment has an immutable receipt.') }}</p>
                    </div>
                </div>

                @if ($invoice->allocations->isEmpty())
                    <div class="empty-state py-4">
                        <div class="empty-icon"><i class="bi bi-wallet2"></i></div>
                        <h3>{{ __('No payments recorded') }}</h3>
                        <p>{{ __('Collect the first payment using the form below.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Receipt') }}</th>
                                    <th>{{ __('Paid on') }}</th>
                                    <th>{{ __('Method') }}</th>
                                    <th>{{ __('Received by') }}</th>
                                    <th class="text-end">{{ __('Applied') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->allocations as $allocation)
                                    <tr>
                                        <td>
                                            @if ($allocation->payment?->receipt)
                                                <a href="{{ route('fees.receipt', $allocation->payment) }}" class="fw-600 text-decoration-none">
                                                    {{ $allocation->payment->receipt->receipt_no }}
                                                </a>
                                            @else
                                                <span class="text-muted-2">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $allocation->payment?->paid_at?->format('d M Y') ?? '—' }}</td>
                                        <td>{{ $allocation->payment ? __(ucfirst($allocation->payment->method)) : '—' }}</td>
                                        <td>{{ $allocation->payment?->receiver?->name ?? '—' }}</td>
                                        <td class="text-end">৳ {{ number_format((float) $allocation->amount, 2) }}</td>
                                        <td>
                                            @if ($allocation->payment?->status === 'reversed')
                                                <span class="badge-soft is-danger">{{ __('Reversed') }}</span>
                                            @elseif ($allocation->payment?->status === 'pending')
                                                @can('fees.verify')
                                                    <a class="badge-soft is-warning text-decoration-none" href="{{ route('fees.verifications') }}">{{ __('Held for verification') }}</a>
                                                @else
                                                    <span class="badge-soft is-warning">{{ __('Held for verification') }}</span>
                                                @endcan
                                            @elseif ($allocation->payment?->status === 'rejected')
                                                <span class="badge-soft is-muted">{{ __('Rejected') }}</span>
                                            @else
                                                <span class="badge-soft is-success">{{ __('Posted') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @can('fees.reverse')
                    @if ($postedPayments->isNotEmpty())
                        <div class="p-lg border-top">
                            <h3 class="card-title" style="font-size:.95rem;">{{ __('Reverse a payment') }}</h3>
                            <p class="text-muted-2" style="font-size:.8rem;">{{ __('Reversing restores the invoice balance and posts a reversing cash entry. Receipts are kept for audit.') }}</p>
                            <form method="POST" action="{{ route('fees.payments.reverse', $postedPayments->first()->payment) }}" class="row g-2 align-items-end" id="reverse-payment-form" data-confirm="{{ __('Reverse this payment? The invoice balance will be restored.') }}">
                                @csrf
                                <div class="col-md-4">
                                    <label class="form-label" for="reverse-payment">{{ __('Payment') }}</label>
                                    <select id="reverse-payment" class="form-select" data-reverse-target="#reverse-payment-form">
                                        @foreach ($postedPayments as $allocation)
                                            <option value="{{ route('fees.payments.reverse', $allocation->payment) }}">
                                                {{ $allocation->payment->receipt?->receipt_no ?? '#'.$allocation->payment->id }}
                                                · ৳ {{ number_format((float) $allocation->amount, 2) }}
                                                · {{ $allocation->payment->paid_at?->format('d M Y') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="reverse-reason">{{ __('Reason') }}</label>
                                    <input id="reverse-reason" name="reason" class="form-control" minlength="5" maxlength="500" required placeholder="{{ __('Why is this payment being reversed?') }}">
                                    @error('reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-light-soft text-danger w-100">
                                        <i class="bi bi-arrow-counterclockwise"></i>{{ __('Reverse') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    @endif
                @endcan
            </section>

            @canany(['fees.collect', 'fees.collect.scoped'])
                @if ($invoice->status !== 'cancelled' && (float) $invoice->due_total > 0)
                    <section class="ui-card p-lg" id="collect-payment">
                        <h2 class="card-title">{{ __('Collect payment') }}</h2>
                        <p class="text-muted-2" style="font-size:.8rem;">
                            {{ __('Still to collect on this invoice') }}:
                            <strong>৳ {{ number_format((float) $invoice->due_total, 2) }}</strong>.
                            {{ __('A receipt is issued immediately and cannot be edited.') }}
                        </p>
                        <form method="POST" action="{{ route('fees.payments.store', $invoice) }}" class="row g-3 mt-1">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                            <div class="col-md-4">
                                <label class="form-label" for="payment-amount">{{ __('Amount') }}</label>
                                <input id="payment-amount" name="amount" type="number" step="0.01" min="0.01" max="{{ (float) $invoice->due_total }}" class="form-control" value="{{ old('amount', number_format((float) $invoice->due_total, 2, '.', '')) }}" required>
                                @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="payment-method">{{ __('Method') }}</label>
                                <select id="payment-method" name="method" class="form-select" required>
                                    @foreach ($methods as $method)
                                        <option value="{{ $method }}" @selected(old('method', 'cash') === $method)>{{ __(ucfirst($method)) }}</option>
                                    @endforeach
                                </select>
                                @error('method')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="payment-date">{{ __('Paid on') }}</label>
                                <input id="payment-date" name="paid_at" type="date" class="form-control" value="{{ old('paid_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                                @error('paid_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="payment-reference">{{ __('Transaction reference') }}</label>
                                <input id="payment-reference" name="transaction_ref" class="form-control" maxlength="100" value="{{ old('transaction_ref') }}" placeholder="{{ __('bKash TrxID, cheque no, etc.') }}">
                                @error('transaction_ref')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="payment-notes">{{ __('Notes') }}</label>
                                <input id="payment-notes" name="notes" class="form-control" maxlength="500" value="{{ old('notes') }}">
                                @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12" data-verify-fields>
                                <label class="form-label" for="payment-bank">{{ __('Received into') }}</label>
                                <select id="payment-bank" name="bank_account_id" class="form-select @error('bank_account_id') is-invalid @enderror">
                                    <option value="">{{ __('Not a bank account') }}</option>
                                    @foreach ($bankAccounts as $account)
                                        <option value="{{ $account->id }}" @selected((string) old('bank_account_id') === (string) $account->id)>{{ $account->label() }}</option>
                                    @endforeach
                                </select>
                                @error('bank_account_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                @if ($bankAccounts->isEmpty())
                                    <div class="text-muted-2 mt-1" style="font-size:.76rem;">
                                        {{ __('No bank account has been added yet, so bank entries are recorded in the bank channel without an account.') }}
                                        @can('bank.manage')
                                            <a href="{{ route('bank.index') }}">{{ __('Add one') }}</a>
                                        @endcan
                                    </div>
                                @endif
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="payment-settle" name="settle_in_full" @checked(old('settle_in_full'))>
                                    <label class="form-check-label" for="payment-settle">
                                        {{ __('Settle in full') }}
                                        <span class="d-block text-muted-2" style="font-size:.76rem;">
                                            {{ __('Use this when the fee is taken short of the billed amount on purpose. Only the money received is recorded and paid into the cashbook; the difference is not kept as a due and does not return on a later invoice.') }}
                                        </span>
                                    </label>
                                </div>
                            </div>
                            @if ($verifyMethods)
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="1" id="payment-hold" name="hold_for_verification" @checked(old('hold_for_verification'))>
                                        <label class="form-check-label" for="payment-hold">
                                            {{ __('Hold for verification') }}
                                            <span class="d-block text-muted-2" style="font-size:.76rem;">
                                                {{ __('No receipt or cashbook entry until the bank or wallet payment is confirmed. Unverified payments still reduce the collectable balance.') }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            @endif
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-cash-coin"></i>{{ __('Record payment & issue receipt') }}</button>
                            </div>
                        </form>
                    </section>
                @endif
            @endcan
        </div>

        <div class="col-lg-4">
            <section class="ui-card p-lg mb-3">
                <h2 class="card-title">{{ __('Student') }}</h2>
                <dl class="detail-list mt-3 mb-0">
                    <div><dt>{{ __('Name') }}</dt><dd>{{ $invoice->student?->full_name ?? '—' }}</dd></div>
                    <div><dt>{{ __('Student ID') }}</dt><dd>{{ $invoice->student?->student_id ?? '—' }}</dd></div>
                    <div><dt>{{ __('Class') }}</dt><dd>{{ $invoice->enrollment?->schoolClass?->name ?? '—' }}</dd></div>
                    <div><dt>{{ __('Section') }}</dt><dd>{{ $invoice->enrollment?->section?->name ?? '—' }}</dd></div>
                    <div><dt>{{ __('Roll') }}</dt><dd>{{ $invoice->enrollment?->roll_no ?? '—' }}</dd></div>
                    @if ($invoice->student)
                        <div>
                            <dt>{{ __('Profile') }}</dt>
                            <dd><a href="{{ route('students.show', $invoice->student) }}">{{ __('Open student profile') }}</a></dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="ui-card p-lg mb-3">
                <h2 class="card-title">{{ __('Invoice details') }}</h2>
                <dl class="detail-list mt-3 mb-0">
                    <div><dt>{{ __('Session') }}</dt><dd>{{ $invoice->academicSession?->name ?? '—' }}</dd></div>
                    <div><dt>{{ __('Billing period') }}</dt><dd>{{ $invoice->billing_period ?? '—' }}</dd></div>
                    <div><dt>{{ __('Issued') }}</dt><dd>{{ $invoice->issue_date?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>{{ __('Due date') }}</dt><dd>{{ $invoice->due_date?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>{{ __('Created by') }}</dt><dd>{{ $invoice->creator?->name ?? '—' }}</dd></div>
                </dl>
                @if ($invoice->notes)
                    <p class="text-muted-2 mt-3 mb-0" style="font-size:.8rem;">{{ $invoice->notes }}</p>
                @endif
            </section>

            @can('fees.manage')
                @if ($invoice->status !== 'cancelled' && (float) $invoice->paid_total <= 0)
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('Cancel invoice') }}</h2>
                        <p class="text-muted-2" style="font-size:.8rem;">{{ __('Cancelling keeps the invoice on record but removes it from balances. Invoices with payments cannot be cancelled.') }}</p>
                        <form method="POST" action="{{ route('fees.cancel', $invoice) }}" data-confirm="{{ __('Cancel invoice :no? This cannot be undone.', ['no' => $invoice->invoice_no]) }}">
                            @csrf
                            <button type="submit" class="btn btn-light-soft text-danger w-100"><i class="bi bi-x-circle"></i>{{ __('Cancel invoice') }}</button>
                        </form>
                    </section>
                @endif
            @endcan
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        var reverseSelect = document.querySelector('[data-reverse-target]');
        if (reverseSelect) {
            reverseSelect.addEventListener('change', function () {
                var form = document.querySelector(reverseSelect.getAttribute('data-reverse-target'));
                if (form) { form.setAttribute('action', reverseSelect.value); }
            });
        }

        (function () {
            var method = document.getElementById('payment-method');
            var fields = document.querySelector('[data-verify-fields]');
            var hold = document.getElementById('payment-hold');
            if (!method || !fields) return;

            var nonCash = @json($verifyMethods);

            function sync() {
                var isNonCash = nonCash.indexOf(method.value) !== -1;
                fields.classList.toggle('d-none', !isNonCash);

                if (!isNonCash && hold) { hold.checked = false; }
                if (isNonCash && hold && !hold.dataset.touched) { hold.checked = true; }
            }

            if (hold) {
                hold.addEventListener('change', function () { hold.dataset.touched = '1'; });
            }

            method.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
