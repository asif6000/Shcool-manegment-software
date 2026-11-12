@use('App\Support\Money')
@extends('layouts.app')

@section('title', $voucher->voucher_no)

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-journal-text"></i>{{ __('Voucher') }}</span>
            <h1 class="page-title">{{ $voucher->voucher_no }}</h1>
            <p class="page-subtitle">{{ $voucher->voucher_date?->format('d M Y') }} · {{ __(ucfirst($voucher->type)) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('vouchers.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Vouchers') }}</a>
            <button type="button" class="btn btn-light-soft" onclick="window.print()"><i class="bi bi-printer"></i>{{ __('Print') }}</button>
            @if ($voucher->status === 'posted')
                @can('vouchers.manage')
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#voucherCancel">
                        <i class="bi bi-slash-circle"></i>{{ __('Cancel voucher') }}
                    </button>
                @endcan
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <section class="ui-card p-lg">
                <div class="card-head">
                    <h2 class="card-title">{{ __('Voucher details') }}</h2>
                    <span class="badge-soft {{ $voucher->status === 'posted' ? 'is-success' : 'is-muted' }}">{{ __(ucfirst($voucher->status)) }}</span>
                </div>
                <dl class="detail-list mt-3 mb-0">
                    <div><dt>{{ __('Type') }}</dt><dd>{{ __(ucfirst($voucher->type)) }}</dd></div>
                    <div><dt>{{ __('Amount') }}</dt><dd class="fw-700">{{ Money::format((float) $voucher->amount) }}</dd></div>
                    <div><dt>{{ __('Channel') }}</dt><dd>{{ __(ucfirst($voucher->channel)) }}@if ($voucher->bankAccount) · {{ $voucher->bankAccount->label() }}@endif</dd></div>
                    <div><dt>{{ __('Category') }}</dt><dd>{{ $voucher->category ?: '—' }}</dd></div>
                    <div><dt>{{ __('Payee') }}</dt><dd>{{ $voucher->payee ?: '—' }}</dd></div>
                    <div><dt>{{ __('Reference') }}</dt><dd>{{ $voucher->reference_no ?: '—' }}</dd></div>
                    <div><dt>{{ __('Description') }}</dt><dd>{{ $voucher->description }}</dd></div>
                    <div><dt>{{ __('Created by') }}</dt><dd>{{ $voucher->creator?->name ?? '—' }} · {{ $voucher->created_at?->format('d M Y H:i') }}</dd></div>
                </dl>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Ledger entry') }}</h2>
                @if ($voucher->transaction)
                    <dl class="detail-list mt-3 mb-0">
                        <div><dt>{{ __('Cashbook entry') }}</dt><dd>#{{ $voucher->transaction->id }}</dd></div>
                        <div><dt>{{ __('Entry date') }}</dt><dd>{{ $voucher->transaction->transaction_date?->format('d M Y') }}</dd></div>
                        <div><dt>{{ __('Amount') }}</dt><dd>{{ Money::format(abs((float) $voucher->transaction->amount)) }}</dd></div>
                        <div><dt>{{ __('Reference') }}</dt><dd>{{ $voucher->transaction->reference_no ?: '—' }}</dd></div>
                    </dl>
                @else
                    <p class="text-muted-2 mt-3 mb-0">{{ __('This voucher has no ledger entry.') }}</p>
                @endif
            </section>

            @if ($voucher->status === 'cancelled')
                <section class="ui-card p-lg mt-3">
                    <h2 class="card-title">{{ __('Cancellation') }}</h2>
                    <dl class="detail-list mt-3 mb-0">
                        <div><dt>{{ __('Cancelled by') }}</dt><dd>{{ $voucher->canceller?->name ?? '—' }}</dd></div>
                        <div><dt>{{ __('Cancelled at') }}</dt><dd>{{ $voucher->cancelled_at?->format('d M Y H:i') }}</dd></div>
                        <div><dt>{{ __('Reason') }}</dt><dd>{{ $voucher->cancel_reason }}</dd></div>
                    </dl>
                </section>
            @endif
        </div>
    </div>

    @if ($voucher->status === 'posted')
        @can('vouchers.manage')
            <div class="modal fade" id="voucherCancel" tabindex="-1" aria-labelledby="voucherCancelLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('vouchers.cancel', $voucher) }}" class="modal-content">
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="voucherCancelLabel">{{ __('Cancel voucher') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-2">{{ $voucher->voucher_no }} · {{ Money::format((float) $voucher->amount) }}</p>
                            <label class="form-label" for="voucher-cancel-reason">{{ __('Reason') }}</label>
                            <textarea id="voucher-cancel-reason" name="reason" class="form-control" rows="3" maxlength="500" required></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Keep voucher') }}</button>
                            <button type="submit" class="btn btn-danger">{{ __('Cancel and reverse ledger') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif
@endsection
