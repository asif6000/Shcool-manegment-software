@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Fee collection report'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-cash-coin"></i>{{ __('Reports') }}</span>
            <h1 class="page-title">{{ __('Fee collection report') }}</h1>
            <p class="page-subtitle">{{ __('Money received between :from and :to, grouped by the day and month it was paid', [
                'from' => $filters['from']->format('d M Y'),
                'to' => $filters['to']->format('d M Y'),
            ]) }}</p>
        </div>
        @include('reports._actions', ['export' => 'collection'])
    </div>

    @include('reports._filters')
    @include('reports._summary')

    @php
        // The printed sheet has to carry the filters itself: the filter form is
        // hidden on paper, and a register without its conditions proves nothing.
        $applied = array_filter([
            __('Date from') => $filters['from']->format('d M Y'),
            __('Date to') => $filters['to']->format('d M Y'),
            __('Academic session') => $filters['session_id'] === null ? null : optional($sessions->firstWhere('id', $filters['session_id']))->name,
            __('Month') => $filters['fee_month'] === null ? null : \Illuminate\Support\Carbon::parse($filters['fee_month'].'-01')->translatedFormat('F Y'),
            __('Class') => $filters['class_id'] === null ? null : optional($classes->firstWhere('id', $filters['class_id']))->name,
            __('Section') => $filters['section_id'] === null ? null : optional($sections->firstWhere('id', $filters['section_id']))->name,
            __('Student') => $filters['student_id'] === null ? null : optional($students->firstWhere('id', $filters['student_id']))->full_name,
            __('Fee type') => $filters['fee_type_id'] === null ? null : optional($feeTypes->firstWhere('id', $filters['fee_type_id']))->name,
            __('Payment method') => $filters['method'] === null ? null : __(ucfirst($filters['method'])),
            __('Collected by') => $filters['collector_id'] === null ? null : optional($collectors->firstWhere('id', $filters['collector_id']))->name,
        ]);

        $unallocated = round((float) $summary['received'] - (float) $feeSummary['total'], 2);
    @endphp

    {{-- School-branded header that only exists on paper --}}
    <section class="report-letterhead">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h2 class="h5 mb-1">{{ $school?->name ?? config('app.name') }}</h2>
                <p class="mb-0" style="font-size:.75rem;">
                    {{ collect([$school?->address, $school?->upazila, $school?->district])->filter()->join(', ') }}
                    @if (filled($school?->phone)) · {{ $school->phone }} @endif
                    @if (filled($school?->eiin)) · EIIN {{ $school->eiin }} @endif
                </p>
            </div>
            <div class="text-end" style="font-size:.75rem;">
                <div class="fw-700">{{ __('Fee Collection Register') }}</div>
                <div>{{ __('Printed') }} {{ now()->format('d M Y h:i A') }}</div>
                <div>{{ auth()->user()?->name }}</div>
            </div>
        </div>
        <p class="mb-0 mt-1" style="font-size:.72rem;">
            @foreach ($applied as $label => $value)
                <span class="me-2">{{ $label }}: <strong>{{ $value }}</strong></span>
            @endforeach
        </p>
    </section>

    {{-- The counter register: one row per fee a payment was applied to --}}
    <section class="ui-card table-card mb-3" id="collection-register">
        <div class="table-toolbar flex-wrap gap-2">
            <h2 class="card-title mb-0">{{ __('Collection register') }}</h2>
            <div class="d-flex flex-wrap gap-2 no-print">
                @can('reports.export')
                    <a href="{{ route('reports.export', array_merge(request()->query(), ['report' => 'register'])) }}" class="btn btn-light-soft btn-sm">
                        <i class="bi bi-filetype-csv"></i>{{ __('Register CSV') }}
                    </a>
                @endcan
                <a href="{{ route('reports.collection', array_merge(request()->query(), ['all' => 1, 'print' => 1])) }}" class="btn btn-light-soft btn-sm">
                    <i class="bi bi-printer"></i>{{ __('Print every row') }}
                </a>
            </div>
        </div>

        @if ($register->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-journal-x"></i></div>
                <h3>{{ __('No money collected') }}</h3>
                <p>{{ __('No posted payment matches these filters. Payments held for verification are listed under reversals and unverified money.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0 register-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Serial') }}</th>
                            <th scope="col">{{ __('Transaction ID') }}</th>
                            <th scope="col">{{ __('Student Name') }}</th>
                            <th scope="col">{{ __('Class') }}</th>
                            <th scope="col">{{ __('Roll') }}</th>
                            <th scope="col">{{ __('Fee Type') }}</th>
                            <th scope="col">{{ __('Month') }}</th>
                            <th scope="col" class="text-end">{{ __('Scheduled Amount') }}</th>
                            <th scope="col" class="text-end">{{ __('Received Amount') }}</th>
                            <th scope="col">{{ __('Payment Method') }}</th>
                            <th scope="col">{{ __('Collection Date') }}</th>
                            <th scope="col">{{ __('Collected By') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($register as $row)
                            <tr>
                                <td>{{ $row['serial'] }}</td>
                                <td class="font-monospace">
                                    @if ($row['payment_id'] !== null)
                                        <a href="{{ route('fees.receipt', ['payment' => $row['payment_id'], 'print' => 1]) }}" class="text-decoration-none" title="{{ __('Print this receipt') }}">
                                            {{ $row['transaction_ref'] }}
                                        </a>
                                    @else
                                        {{ $row['transaction_ref'] }}
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-600">{{ $row['student'] }}</span>
                                    <span class="text-muted-2 d-block" style="font-size:.72rem;">{{ $row['invoice_no'] }}</span>
                                </td>
                                <td>{{ $row['class'] }}@if ($row['section'] !== '—') · {{ $row['section'] }} @endif</td>
                                <td>{{ $row['roll'] }}</td>
                                <td>{{ $row['fee_type'] }}</td>
                                <td>{{ $row['month_label'] }}</td>
                                <td class="text-end">{{ Money::format($row['scheduled']) }}</td>
                                <td class="text-end fw-700">{{ Money::format($row['received']) }}</td>
                                <td>{{ $row['method'] }}</td>
                                <td>{{ $row['collected_on']?->format('d M Y') ?? '—' }}</td>
                                <td>{{ $row['collector'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="8" scope="row">
                                {{ __(':count entries collected', ['count' => number_format($register->total())]) }}
                                <span class="fw-400 text-muted-2" style="font-size:.72rem;">{{ __('The scheduled column repeats an invoice when it was paid in instalments, so it is not summed.') }}</span>
                            </th>
                            <th class="text-end">{{ Money::format($summary['received']) }}</th>
                            <th colspan="3"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($register->hasPages())
                <div class="px-lg pb-2">{{ $register->links() }}</div>
            @endif

            <div class="report-signature justify-content-between pt-5 mt-3">
                <div class="text-center" style="min-width:45%;">
                    <div class="signature-line"></div>
                    <span style="font-size:.75rem;">{{ __('Accountant') }}</span>
                </div>
                <div class="text-center" style="min-width:45%;">
                    <div class="signature-line"></div>
                    <span style="font-size:.75rem;">{{ __('Head of School') }}</span>
                </div>
            </div>
        @endif
    </section>

    {{-- Fee-type totals: the categories the admin created, then each fee type --}}
    <section class="ui-card table-card mb-3">
        <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('Fee type summary') }}</h2></div>
        @if ($feeSummary['groups']->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-tags"></i></div>
                <h3>{{ __('No fee types collected') }}</h3>
                <p>{{ __('The collection register is empty for these filters.') }}</p>
            </div>
        @else
            <div class="row g-0">
                <div class="col-lg-5 border-lg-end">
                    <table class="table align-middle mb-0">
                        <caption class="px-lg pb-0 mb-0 text-muted-2" style="font-size:.75rem;">{{ __('By fee category') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Category') }}</th>
                                <th scope="col" class="text-end">{{ __('Total collection') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($feeSummary['groups'] as $group)
                                <tr>
                                    <td>{{ $group['label'] }}</td>
                                    <td class="text-end fw-600">{{ Money::format($group['received']) }}</td>
                                </tr>
                            @endforeach
                            @if ($unallocated > 0)
                                <tr>
                                    <td>{{ __('Invoices without a fee line') }}</td>
                                    <td class="text-end fw-600">{{ Money::format($unallocated) }}</td>
                                </tr>
                            @endif
                        </tbody>
                        <tfoot>
                            <tr>
                                <th scope="row">{{ __('Grand total') }}</th>
                                <th class="text-end">{{ Money::format(round($feeSummary['total'] + $unallocated, 2)) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="col-lg-7">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <caption class="px-lg pb-0 mb-0 text-muted-2" style="font-size:.75rem;">{{ __('Every fee type collected in this range') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Fee type') }}</th>
                                    <th scope="col">{{ __('Category') }}</th>
                                    <th scope="col" class="text-end">{{ __('Fee lines') }}</th>
                                    <th scope="col" class="text-end">{{ __('Billed') }}</th>
                                    <th scope="col" class="text-end">{{ __('Collected') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($feeSummary['types'] as $type)
                                    <tr>
                                        <td class="fw-600">{{ $type['label'] }}</td>
                                        <td>{{ $type['category_label'] }}</td>
                                        <td class="text-end">{{ number_format($type['lines']) }}</td>
                                        <td class="text-end">{{ Money::format($type['billed']) }}</td>
                                        <td class="text-end fw-700">{{ Money::format($type['received']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" scope="row">{{ __('Grand total') }}</th>
                                    <th class="text-end">{{ Money::format($feeSummary['total']) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <p class="text-muted-2 px-lg pb-2 mb-0" style="font-size:.78rem;">
                {{ __('Collected money is split across the fee lines of each invoice in proportion to the line, so the fee types add back to the register total.') }}
            </p>
        @endif
    </section>

    <div class="row g-3">
        <div class="col-lg-6">
            <section class="ui-card table-card h-100">
                <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('Daily collection') }}</h2></div>
                @if ($byDay->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-calendar3"></i></div>
                        <h3>{{ __('Nothing collected') }}</h3>
                        <p>{{ __('No posted payment falls inside this range with these filters.') }}</p>
                    </div>
                @else
                    <div class="table-responsive" style="max-height:420px;overflow:auto;">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Paid on') }}</th>
                                    <th scope="col" class="text-end">{{ __('Payments') }}</th>
                                    <th scope="col" class="text-end">{{ __('Invoices') }}</th>
                                    <th scope="col" class="text-end">{{ __('Standard fee') }}</th>
                                    <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byDay as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="text-end">{{ number_format($row['payments']) }}</td>
                                        <td class="text-end">{{ number_format($row['invoices']) }}</td>
                                        <td class="text-end">{{ Money::format($row['standard_fee']) }}</td>
                                        <td class="text-end fw-600">{{ Money::format($row['received']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th scope="row">{{ __('Total') }}</th>
                                    <th class="text-end">{{ number_format($summary['payments']) }}</th>
                                    <th class="text-end">{{ number_format($summary['invoices']) }}</th>
                                    <th class="text-end">{{ Money::format($summary['standard_fee']) }}</th>
                                    <th class="text-end">{{ Money::format($summary['received']) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="text-muted-2 px-lg pb-2 mb-0" style="font-size:.78rem;">
                        {{ __('A day repeats an invoice only when it was paid again that day, never the same money twice.') }}
                    </p>
                @endif
            </section>
        </div>

        <div class="col-lg-6">
            <section class="ui-card table-card h-100">
                <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('Monthly collection') }}</h2></div>
                @if ($byPaymentMonth->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-calendar-month"></i></div>
                        <h3>{{ __('No months with money') }}</h3>
                        <p>{{ __('Collection months appear once a payment is posted.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Payment month') }}</th>
                                    <th scope="col" class="text-end">{{ __('Payments') }}</th>
                                    <th scope="col" class="text-end">{{ __('Standard fee') }}</th>
                                    <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byPaymentMonth as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="text-end">{{ number_format($row['payments']) }}</td>
                                        <td class="text-end">{{ Money::format($row['standard_fee']) }}</td>
                                        <td class="text-end fw-600">{{ Money::format($row['received']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <div class="col-lg-6">
            <section class="ui-card table-card h-100">
                <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('By payment method') }}</h2></div>
                @if ($byMethod->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-cash-coin"></i></div>
                        <h3>{{ __('No collections in range') }}</h3>
                        <p>{{ __('Record a fee payment inside this date range to see the split.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Method') }}</th>
                                    <th scope="col" class="text-end">{{ __('Payments') }}</th>
                                    <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byMethod as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="text-end">{{ number_format($row['payments']) }}</td>
                                        <td class="text-end fw-600">{{ Money::format($row['received']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted-2 px-lg pb-2 mb-0" style="font-size:.78rem;">
                        <a href="{{ route('reports.methods', request()->query()) }}" class="text-decoration-none">{{ __('Open the payment method report') }} <i class="bi bi-arrow-right"></i></a>
                    </p>
                @endif
            </section>
        </div>

        <div class="col-lg-6">
            <section class="ui-card table-card h-100">
                <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('Reversals and unverified money') }}</h2></div>
                @if ($reversals->isEmpty() && $held->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-shield-check"></i></div>
                        <h3>{{ __('Nothing to reverse') }}</h3>
                        <p>{{ __('No payment in this range was reversed or is waiting for verification.') }}</p>
                    </div>
                @else
                    <div class="table-responsive" style="max-height:300px;overflow:auto;">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Student') }}</th>
                                    <th scope="col">{{ __('Status') }}</th>
                                    <th scope="col" class="text-end">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reversals as $row)
                                    <tr>
                                        <td>{{ $row['student'] }}<br><span class="text-muted-2" style="font-size:.75rem;">{{ $row['receipt_no'] }} · {{ $row['reversed_at']?->format('d M Y') }}</span></td>
                                        <td><span class="badge-soft is-danger">{{ __('Reversed') }}</span></td>
                                        <td class="text-end">{{ Money::format($row['amount']) }}</td>
                                    </tr>
                                @endforeach
                                @foreach ($held as $row)
                                    <tr>
                                        <td>{{ $row['student'] }}<br><span class="text-muted-2" style="font-size:.75rem;">{{ $row['method'] }} · {{ $row['paid_at']?->format('d M Y') }}</span></td>
                                        <td><span class="badge-soft is-warning">{{ __('Waiting verification') }}</span></td>
                                        <td class="text-end">{{ Money::format($row['amount']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted-2 px-lg pb-2 mb-0" style="font-size:.78rem;">
                        {{ __('These amounts are outside the cash received total: a reversal left the cashbook, an unverified payment has not entered it yet.') }}
                    </p>
                @endif
            </section>
        </div>
    </div>
@endsection

@include('reports._print')

@push('styles')
    <style>
        /* Pushed after reports._print so the wide register wins the page size. */
        .report-letterhead,
        .report-signature {
            display: none;
        }

        .signature-line {
            border-bottom: 1px solid #94a3b8;
            margin-bottom: .25rem;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }

            .report-letterhead {
                display: block;
                border-bottom: 2px solid #0a1d3d;
                padding-bottom: 6px;
                margin-bottom: 10px;
            }

            .report-signature {
                display: flex;
            }

            .register-table {
                font-size: .66rem !important;
            }

            .register-table th,
            .register-table td {
                padding: .25rem .35rem !important;
                white-space: nowrap;
            }

            .register-table tfoot th {
                white-space: normal;
            }
        }
    </style>
@endpush

@if (request()->boolean('print'))
    @push('scripts')
        <script>
            window.addEventListener('load', function () {
                window.print();
            });
        </script>
    @endpush
@endif
