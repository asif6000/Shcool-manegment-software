@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Discounts and scholarships'))

@section('content')
    @php
        $statusClasses = [
            'pending' => 'is-warning',
            'approved' => 'is-success',
            'applied' => 'is-success',
            'rejected' => 'is-danger',
            'cancelled' => 'is-muted',
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-percent"></i>{{ __('Fees') }}</span>
            <h1 class="page-title">{{ __('Discounts and scholarships') }}</h1>
            <p class="page-subtitle">{{ __('Concessions applied to invoices. Requests above :threshold need approval before they change the invoice.', ['threshold' => Money::format($threshold)]) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('fees.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Invoices') }}</a>
        </div>
    </div>

    <section class="stats-grid mb-3" aria-label="{{ __('Discount summary') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Awaiting approval') }}</p>
                    <p class="stat-value">{{ number_format($summary['pending_count']) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
            </div>
            <div class="stat-foot"><span>{{ Money::format($summary['pending_value']) }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Applied concessions') }}</p>
                    <p class="stat-value">{{ Money::format($summary['applied_value']) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
            </div>
            <div class="stat-foot"><span>{{ trans_choice(':count concession|:count concessions', $summary['applied_count'], ['count' => $summary['applied_count']]) }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Approval threshold') }}</p>
                    <p class="stat-value">{{ Money::format($threshold) }}</p>
                </div>
                <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-sliders"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Per invoice concession') }}</span></div>
        </article>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('fees.discounts.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="discount-search">{{ __('Search discounts') }}</label>
                    <input id="discount-search" type="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Invoice, student or concession') }}">
                </div>
                <select name="status" class="form-select" data-auto-submit aria-label="{{ __('Status') }}">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Models\FeeInvoiceDiscount::STATUSES as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __(ucfirst($status)) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('fees.discounts.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count concession|:count concessions', $discounts->total(), ['count' => $discounts->total()]) }}</span>
        </div>

        @if ($discounts->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-percent"></i></div>
                <h3>{{ __('No concessions found') }}</h3>
                <p>{{ array_filter($filters) ? __('No concessions match these filters.') : __('Apply a concession from an invoice page to see it here.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Invoice') }}</th>
                            <th scope="col">{{ __('Student') }}</th>
                            <th scope="col">{{ __('Concession') }}</th>
                            <th scope="col" class="text-end">{{ __('Amount') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col">{{ __('Requested by') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($discounts as $discount)
                            <tr>
                                <td>
                                    @if ($discount->invoice)
                                        <a href="{{ route('fees.show', $discount->invoice) }}" class="fw-600 text-decoration-none">{{ $discount->invoice->invoice_no }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    {{ $discount->invoice?->student?->full_name ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.74rem;">{{ $discount->invoice?->student?->student_id }}</div>
                                </td>
                                <td>
                                    {{ $discount->name }}
                                    <div class="text-muted-2" style="font-size:.74rem;">
                                        {{ $discount->type === 'percent' ? number_format((float) $discount->value, 2).'%' : Money::format((float) $discount->value) }}
                                    </div>
                                </td>
                                <td class="text-end fw-600">{{ Money::format((float) $discount->amount) }}</td>
                                <td><span class="badge-soft {{ $statusClasses[$discount->status] ?? '' }}">{{ __(ucfirst($discount->status)) }}</span></td>
                                <td>
                                    {{ $discount->requester?->name ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.74rem;">{{ $discount->created_at?->format('d M Y') }}</div>
                                </td>
                                <td class="text-end">
                                    @if ($discount->status === 'pending')
                                        @can('fees.discounts.approve')
                                            <div class="row-actions">
                                                <form method="POST" action="{{ route('fees.discounts.approve', $discount) }}" class="d-inline"
                                                      onsubmit="return confirm(@json(__('Approve and apply this concession?')))">
                                                    @csrf
                                                    <button type="submit" class="icon-action text-success" title="{{ __('Approve') }}" aria-label="{{ __('Approve concession') }}">
                                                        <i class="bi bi-check2-circle"></i>
                                                    </button>
                                                </form>
                                                <button type="button" class="icon-action text-danger" data-bs-toggle="modal" data-bs-target="#discountReject{{ $discount->id }}"
                                                        title="{{ __('Reject') }}" aria-label="{{ __('Reject concession') }}">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </div>
                                        @endcan
                                    @elseif ($discount->status === 'applied')
                                        <span class="text-muted-2" style="font-size:.74rem;">{{ $discount->approver?->name }}</span>
                                    @else
                                        <span class="text-muted-2" style="font-size:.74rem;">{{ $discount->rejection_reason }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $discounts->firstItem(), 'to' => $discounts->lastItem(), 'total' => $discounts->total()]) }}</span>
                {{ $discounts->links() }}
            </div>
        @endif
    </section>

    @can('fees.discounts.approve')
        @foreach ($discounts as $discount)
            @if ($discount->status === 'pending')
                <div class="modal fade" id="discountReject{{ $discount->id }}" tabindex="-1" aria-labelledby="discountRejectLabel{{ $discount->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <form method="POST" action="{{ route('fees.discounts.reject', $discount) }}" class="modal-content">
                            @csrf
                            <div class="modal-header">
                                <h2 class="modal-title fs-5" id="discountRejectLabel{{ $discount->id }}">{{ __('Reject concession') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-2">{{ $discount->invoice?->invoice_no }} · {{ $discount->name }}</p>
                                <label class="form-label" for="discount-reason-{{ $discount->id }}">{{ __('Reason') }}</label>
                                <textarea id="discount-reason-{{ $discount->id }}" name="reason" class="form-control" rows="3" maxlength="500" required></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                <button type="submit" class="btn btn-danger">{{ __('Reject request') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endforeach
    @endcan
@endsection
