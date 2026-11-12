@extends('layouts.app')

@section('title', __('Outstanding dues'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-wallet2"></i>{{ __('Finance') }}</span>
            <h1 class="page-title">{{ __('Outstanding dues') }}</h1>
            <p class="page-subtitle">{{ __('Every unpaid balance in this school, oldest due date first.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('fees.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Fees') }}</a>
            @can('fees.manage')
                <a href="{{ route('fees.create') }}" class="btn btn-primary"><i class="bi bi-receipt-cutoff"></i>{{ __('Generate invoice') }}</a>
            @endcan
        </div>
    </div>

    <section class="stats-grid mb-3" aria-label="{{ __('Dues summary') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Outstanding') }}</p>
                    <p class="stat-value">৳ {{ number_format($summary['due'], 2) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-wallet2"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Across unpaid invoices') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Invoices') }}</p>
                    <p class="stat-value">{{ number_format($summary['count']) }}</p>
                </div>
                <span class="stat-icon" aria-hidden="true"><i class="bi bi-receipt"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Issued or partial') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Overdue') }}</p>
                    <p class="stat-value">{{ number_format($summary['overdue']) }}</p>
                </div>
                <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-exclamation-circle"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Past their due date') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Students') }}</p>
                    <p class="stat-value">{{ number_format($summary['students']) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-mortarboard"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('With an open balance') }}</span></div>
        </article>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('fees.dues') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="dues-search">{{ __('Search dues') }}</label>
                    <input id="dues-search" type="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Search invoice, student or ID') }}">
                </div>
                <label class="visually-hidden" for="dues-session">{{ __('Session') }}</label>
                <select id="dues-session" name="session_id" class="form-select" data-auto-submit>
                    <option value="">{{ __('All sessions') }}</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected($filters['session_id'] === (string) $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="dues-class">{{ __('Class') }}</label>
                <select id="dues-class" name="class_id" class="form-select" data-auto-submit>
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($filters['class_id'] === (string) $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('fees.dues') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count invoice|:count invoices', $invoices->total(), ['count' => $invoices->total()]) }}</span>
        </div>

        @if ($invoices->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-check2-circle"></i></div>
                <h3>{{ __('No outstanding dues') }}</h3>
                <p>{{ array_filter($filters) ? __('No invoices match these filters.') : __('Every issued invoice has been fully paid.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Invoice') }}</th>
                            <th scope="col">{{ __('Student') }}</th>
                            <th scope="col">{{ __('Class') }}</th>
                            <th scope="col">{{ __('Due date') }}</th>
                            <th class="text-end">{{ __('Invoice total') }}</th>
                            <th class="text-end">{{ __('Paid') }}</th>
                            <th class="text-end">{{ __('Balance') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($invoices as $invoice)
                            @php($overdue = $invoice->due_date !== null && $invoice->due_date->isPast())
                            <tr>
                                <td>
                                    <a href="{{ route('fees.show', $invoice) }}" class="fw-600 text-decoration-none">{{ $invoice->invoice_no }}</a>
                                    <div class="text-muted-2" style="font-size:.76rem;">{{ $invoice->billing_period ?? __('No period') }}</div>
                                </td>
                                <td>
                                    <div>{{ $invoice->student?->full_name ?? '—' }}</div>
                                    <div class="text-muted-2" style="font-size:.76rem;">{{ $invoice->student?->student_id }}</div>
                                </td>
                                <td>
                                    {{ $invoice->enrollment?->schoolClass?->name ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.76rem;">
                                        {{ $invoice->enrollment?->section?->name ? __('Section').' '.$invoice->enrollment->section->name : __('No section') }}
                                    </div>
                                </td>
                                <td>
                                    {{ $invoice->due_date?->format('d M Y') ?? '—' }}
                                    @if ($overdue)
                                        <span class="badge-soft is-danger ms-1">{{ __('Overdue') }}</span>
                                    @endif
                                </td>
                                <td class="text-end">৳ {{ number_format((float) $invoice->total, 2) }}</td>
                                <td class="text-end">৳ {{ number_format((float) $invoice->paid_total, 2) }}</td>
                                <td class="text-end fw-700">৳ {{ number_format((float) $invoice->due_total, 2) }}</td>
                                <td class="text-end">
                                    <div class="row-actions">
                                        <a href="{{ route('fees.show', $invoice) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('View invoice') }}" aria-label="{{ __('View invoice :no', ['no' => $invoice->invoice_no]) }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @can('fees.collect')
                                            <a href="{{ route('fees.show', $invoice) }}#collect-payment" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Collect payment') }}" aria-label="{{ __('Collect payment for :no', ['no' => $invoice->invoice_no]) }}">
                                                <i class="bi bi-cash-coin"></i>
                                            </a>
                                        @endcan
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
