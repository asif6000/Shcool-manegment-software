@extends('layouts.app')

@section('title', __('Fees & payments'))

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
            <span class="page-eyebrow"><i class="bi bi-receipt"></i>{{ __('Finance') }}</span>
            <h1 class="page-title">{{ __('Fees & payments') }}</h1>
            <p class="page-subtitle">{{ __('Invoices, balances and receipts for this school.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('fees.dues') }}" class="btn btn-light-soft">
                <i class="bi bi-wallet2"></i>{{ __('Outstanding dues') }}
            </a>
            @can('fees.export')
                <a href="{{ route('fees.export', array_filter($filters)) }}" class="btn btn-light-soft">
                    <i class="bi bi-filetype-csv"></i>{{ __('Export CSV') }}
                </a>
            @endcan
            @can('fees.manage')
                <a href="{{ route('fees.create') }}" class="btn btn-primary">
                    <i class="bi bi-receipt-cutoff"></i>{{ __('Generate invoice') }}
                </a>
            @endcan
        </div>
    </div>

    <section class="stats-grid mb-3" aria-label="{{ __('Fee summary') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Total billed') }}</p>
                    <p class="stat-value">৳ {{ number_format($summary['total'], 2) }}</p>
                </div>
                <span class="stat-icon" aria-hidden="true"><i class="bi bi-journal-text"></i></span>
            </div>
            <div class="stat-foot"><span>{{ trans_choice(':count invoice|:count invoices', $summary['count'], ['count' => $summary['count']]) }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Collected') }}</p>
                    <p class="stat-value">৳ {{ number_format($summary['paid'], 2) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Excludes cancelled invoices') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Outstanding') }}</p>
                    <p class="stat-value">৳ {{ number_format($summary['due'], 2) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Across all invoices') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Overdue invoices') }}</p>
                    <p class="stat-value">{{ number_format($summary['overdue']) }}</p>
                </div>
                <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-exclamation-circle"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Past their due date') }}</span></div>
        </article>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('fees.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="fee-search">{{ __('Search invoices') }}</label>
                    <input id="fee-search" type="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Search invoice, student or ID') }}">
                </div>
                <label class="visually-hidden" for="filter-session">{{ __('Session') }}</label>
                <select id="filter-session" name="session_id" class="form-select" data-auto-submit>
                    <option value="">{{ __('All sessions') }}</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected($filters['session_id'] === (string) $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-class">{{ __('Class') }}</label>
                <select id="filter-class" name="class_id" class="form-select" data-auto-submit>
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($filters['class_id'] === (string) $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-status">{{ __('Status') }}</label>
                <select id="filter-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Models\FeeInvoice::STATUSES as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __(ucfirst($status)) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('fees.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count invoice|:count invoices', $invoices->total(), ['count' => $invoices->total()]) }}</span>
        </div>

        @if ($invoices->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-receipt"></i></div>
                <h3>{{ __('No invoices found') }}</h3>
                <p>{{ array_filter($filters) ? __('No invoices match these filters.') : __('Generate invoices for a class to begin billing.') }}</p>
                @can('fees.manage')
                    <a href="{{ route('fees.create') }}" class="btn btn-primary mt-3"><i class="bi bi-receipt-cutoff"></i>{{ __('Generate invoice') }}</a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Invoice') }}</th>
                            <th scope="col">{{ __('Student') }}</th>
                            <th scope="col">{{ __('Class / section') }}</th>
                            <th scope="col">{{ __('Period') }}</th>
                            <th scope="col">{{ __('Issued / due') }}</th>
                            <th scope="col" class="text-end">{{ __('Total') }}</th>
                            <th scope="col" class="text-end">{{ __('Due') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            <tr>
                                <td>
                                    <a href="{{ route('fees.show', $invoice) }}" class="fw-600 text-decoration-none">{{ $invoice->invoice_no }}</a>
                                </td>
                                <td>
                                    <div>{{ $invoice->student?->full_name ?? '—' }}</div>
                                    <div class="text-muted-2" style="font-size:.76rem;">{{ $invoice->student?->student_id }}</div>
                                </td>
                                <td>
                                    {{ $invoice->enrollment?->schoolClass?->name ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.76rem;">
                                        {{ $invoice->enrollment?->section?->name ? __('Section').' '.$invoice->enrollment->section->name : __('No section') }}
                                        @if ($invoice->enrollment?->roll_no)
                                            · {{ __('Roll') }} {{ $invoice->enrollment->roll_no }}
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $invoice->billing_period ?? '—' }}</td>
                                <td>
                                    <div>{{ $invoice->issue_date?->format('d M Y') ?? '—' }}</div>
                                    <div class="text-muted-2" style="font-size:.76rem;">{{ __('Due') }} {{ $invoice->due_date?->format('d M Y') ?? '—' }}</div>
                                </td>
                                <td class="text-end">৳ {{ number_format((float) $invoice->total, 2) }}</td>
                                <td class="text-end fw-600">৳ {{ number_format((float) $invoice->due_total, 2) }}</td>
                                <td>
                                    <span class="badge-soft {{ $statusClasses[$invoice->status] ?? '' }}">{{ __(ucfirst($invoice->status)) }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="row-actions">
                                        <a href="{{ route('fees.show', $invoice) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('View invoice') }}" aria-label="{{ __('View invoice :no', ['no' => $invoice->invoice_no]) }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @if ($invoice->status !== 'cancelled' && (float) $invoice->due_total > 0)
                                            @can('fees.collect')
                                                <a href="{{ route('fees.show', $invoice) }}#collect-payment" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Collect payment') }}" aria-label="{{ __('Collect payment for :no', ['no' => $invoice->invoice_no]) }}">
                                                    <i class="bi bi-cash-coin"></i>
                                                </a>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $invoices->firstItem(), 'to' => $invoices->lastItem(), 'total' => $invoices->total()]) }}</span>
                {{ $invoices->links() }}
            </div>
        @endif
    </section>
@endsection
