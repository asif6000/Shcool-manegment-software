@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Collect student fee'))

@php
    $periodLabel = static function (?string $period): string {
        if ($period === null || $period === '') {
            return '—';
        }

        if (preg_match('/^\d{4}-\d{2}$/', $period) === 1) {
            return \Illuminate\Support\Carbon::parse($period.'-01')->translatedFormat('F Y');
        }

        return __('Whole session');
    };
@endphp

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-cash-stack"></i>{{ __('Fees') }}</span>
            <h1 class="page-title">{{ __('Collect student fee') }}</h1>
            <p class="page-subtitle">{{ __('Pick the unpaid invoice first. Its balance, student and fee details load automatically.') }}</p>
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
        <div class="col-lg-5">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('1. Select invoice') }}</h2>
                <form method="GET" action="{{ route('fees.collect') }}" class="mt-3">
                    <input type="hidden" name="session_id" value="{{ $session?->id }}">
                    <div class="mb-2">
                        <label class="form-label" for="collect-search">{{ __('Invoice number or student name') }}</label>
                        <input id="collect-search" name="search" class="form-control" value="{{ $search }}"
                               placeholder="{{ __('Invoice number, roll or name') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="collect-session">{{ __('Academic session') }}</label>
                        <select id="collect-session" name="session_id" class="form-select" onchange="this.form.submit()">
                            @foreach ($sessions as $option)
                                <option value="{{ $option->id }}" @selected($session?->id === $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-light-soft w-100"><i class="bi bi-search"></i>{{ __('Search') }}</button>
                </form>

                <div class="list-group list-group-flush mt-3">
                    @forelse ($invoices as $option)
                        <a href="{{ route('fees.collect', ['search' => $search, 'session_id' => $session?->id, 'invoice' => $option->id]) }}"
                           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center gap-2 {{ $invoice?->id === $option->id ? 'active' : '' }}">
                            <span>
                                <span class="fw-600 d-block">{{ $option->invoice_no }}</span>
                                <span class="text-muted-2" style="font-size:.75rem;">
                                    {{ $option->student?->full_name }}
                                    @if ($option->enrollment)
                                        · {{ $option->enrollment->schoolClass?->name }} {{ $option->enrollment->section?->name }}
                                        · {{ __('Roll') }} {{ $option->enrollment->roll_no }}
                                    @endif
                                    · {{ $periodLabel($option->billing_period) }}
                                </span>
                            </span>
                            <span class="text-end">
                                <span class="d-block fw-700">{{ Money::format($option->due_total) }}</span>
                                <span class="badge-soft {{ $option->status === 'partial' ? 'is-warning' : 'is-muted' }}">
                                    {{ $option->status === 'partial' ? __('Partly paid') : __('Unpaid') }}
                                </span>
                            </span>
                        </a>
                    @empty
                        <div class="empty-state py-4">
                            <i class="bi bi-receipt"></i>
                            <h2 class="card-title mt-2">{{ __('Nothing to collect') }}</h2>
                            <p class="text-muted-2 mb-0" style="font-size:.8rem;">
                                {{ $search === ''
                                    ? __('No unpaid or part-paid invoice exists in this session yet. Generate the class fees first.')
                                    : __('No open invoice matches that search.') }}
                            </p>
                        </div>
                    @endforelse
                </div>

                @if ($invoices->hasPages())
                    <div class="mt-3">{{ $invoices->withQueryString()->links() }}</div>
                @endif
            </section>
        </div>

        <div class="col-lg-7">
            @if ($invoice === null)
                <section class="ui-card p-lg">
                    <div class="empty-state py-5">
                        <i class="bi bi-cursor"></i>
                        <h2 class="card-title mt-2">{{ __('No invoice selected') }}</h2>
                        <p class="text-muted-2 mb-0">{{ __('Choose an invoice on the left to load its student, fees and balance.') }}</p>
                    </div>
                </section>
            @else
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('2. Invoice details') }}</h2>

                    <div class="row g-2 mt-2" style="font-size:.84rem;">
                        <div class="col-6 col-md-4">
                            <span class="text-muted-2 d-block" style="font-size:.72rem;">{{ __('Invoice number') }}</span>
                            <span class="fw-600">{{ $invoice->invoice_no }}</span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-muted-2 d-block" style="font-size:.72rem;">{{ __('Student') }}</span>
                            <span class="fw-600">{{ $invoice->student?->full_name }}</span>
                            <span class="text-muted-2 d-block" style="font-size:.72rem;">{{ $invoice->student?->student_id }}</span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-muted-2 d-block" style="font-size:.72rem;">{{ __('Class · Section · Roll') }}</span>
                            <span class="fw-600">
                                {{ $invoice->enrollment?->schoolClass?->name ?? '—' }}
                                · {{ $invoice->enrollment?->section?->name ?? '—' }}
                                · {{ $invoice->enrollment?->roll_no ?? '—' }}
                            </span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-muted-2 d-block" style="font-size:.72rem;">{{ __('Academic session') }}</span>
                            <span class="fw-600">{{ $invoice->academicSession?->name ?? '—' }}</span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-muted-2 d-block" style="font-size:.72rem;">{{ __('Month') }}</span>
                            <span class="fw-600">{{ $periodLabel($invoice->billing_period) }}</span>
                        </div>
                        <div class="col-6 col-md-4">
                            <span class="text-muted-2 d-block" style="font-size:.72rem;">{{ __('Due on') }}</span>
                            <span class="fw-600">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</span>
                        </div>
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-aligned align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('Fee type') }}</th>
                                    <th class="text-end">{{ __('Scheduled amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($invoice->items as $item)
                                    <tr>
                                        <td>{{ $item->feeType?->name ?? $item->description }}</td>
                                        <td class="text-end">{{ Money::format($item->line_total) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-muted-2">{{ __('This invoice has no fee line recorded.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-2 mt-1">
                        <div class="col-4">
                            <div class="text-muted-2" style="font-size:.72rem;">{{ __('Invoice amount') }}</div>
                            <div class="fw-700">{{ Money::format($invoice->total) }}</div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted-2" style="font-size:.72rem;">{{ __('Previously received') }}</div>
                            <div class="fw-700 text-success">{{ Money::format($invoice->paid_total) }}</div>
                        </div>
                        <div class="col-4">
                            <div class="text-muted-2" style="font-size:.72rem;">{{ __('Remaining payable') }}</div>
                            <div class="fw-700 text-danger">{{ Money::format($invoice->due_total) }}</div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h2 class="card-title">{{ __('3. Record the payment') }}</h2>

                    <form method="POST" action="{{ route('fees.collect.store') }}" class="mt-3" id="collect-form">
                        @csrf
                        <input type="hidden" name="picked[]" value="{{ $invoice->id }}">
                        <input type="hidden" name="academic_session_id" value="{{ $invoice->academic_session_id }}">
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="collect-amount">{{ __('Received amount') }} <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">৳</span>
                                    <input id="collect-amount" type="number" step="0.01" min="0.01"
                                           max="{{ number_format((float) $invoice->due_total, 2, '.', '') }}"
                                           name="amounts[{{ $invoice->id }}]"
                                           class="form-control @error('amounts') is-invalid @enderror"
                                           value="{{ old('amounts.'.$invoice->id, number_format((float) $invoice->due_total, 2, '.', '')) }}"
                                           required>
                                </div>
                                <p class="text-muted-2 mb-0 mt-1" style="font-size:.72rem;">
                                    {{ __('At most :amount — the balance still payable.', ['amount' => Money::format($invoice->due_total)]) }}
                                </p>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="collect-method">{{ __('Payment method') }} <span class="text-danger">*</span></label>
                                <select id="collect-method" name="method" class="form-select" required>
                                    @foreach ($methods as $method)
                                        <option value="{{ $method }}" @selected(old('method', 'cash') === $method)>{{ __(ucfirst($method)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="collect-date">{{ __('Collection date') }}</label>
                                <input id="collect-date" name="paid_at" type="date" class="form-control"
                                       value="{{ old('paid_at', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                                @error('paid_at')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            @if ($referenceRequired !== [])
                                <div class="col-md-6">
                                    <label class="form-label" for="collect-reference">{{ __('Provider reference') }}</label>
                                    <input id="collect-reference" name="transaction_ref" class="form-control" maxlength="100"
                                           value="{{ old('transaction_ref') }}" placeholder="{{ __('bKash TrxID, cheque no, etc.') }}">
                                    @error('transaction_ref')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            @endif
                        </div>

                        @error('amounts')<div class="alert alert-danger py-2 mt-3" style="font-size:.8rem;">{{ $message }}</div>@enderror
                        @error('amount')<div class="alert alert-danger py-2 mt-3" style="font-size:.8rem;">{{ $message }}</div>@enderror
                        @error('picked')<div class="alert alert-danger py-2 mt-3" style="font-size:.8rem;">{{ $message }}</div>@enderror

                        <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mt-4">
                            <div>
                                <div class="text-muted-2" style="font-size:.75rem;">{{ __('Balance after this payment') }}</div>
                                <div class="fs-5 fw-700" id="collect-balance">৳ 0.00</div>
                            </div>
                            <button type="submit" class="btn btn-primary" id="collect-submit">
                                <i class="bi bi-cash-coin"></i>{{ __('Collect Payment') }}
                            </button>
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

                const amount = form.querySelector('#collect-amount');
                const balance = document.getElementById('collect-balance');
                const submit = document.getElementById('collect-submit');
                const remaining = parseFloat(amount.max) || 0;

                function refresh() {
                    const received = parseFloat(amount.value || '0') || 0;
                    const left = Math.max(0, remaining - received);
                    balance.textContent = '৳ ' + left.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }

                form.addEventListener('input', refresh);
                refresh();

                form.addEventListener('submit', function () {
                    submit.disabled = true;
                    submit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + @js(__('Recording…'));
                });
            })();
        </script>
    @endpush
@endsection
