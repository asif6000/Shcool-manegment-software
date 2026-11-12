@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Accountant details').' · '.$accountant->name)

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-badge"></i>{{ __('Accountant Management') }}</span>
            <h1 class="page-title">{{ $accountant->name }}</h1>
            <p class="page-subtitle">{{ __('Everything collected by this account holder, from the receipts they posted.') }}</p>
        </div>
        <div class="page-actions">
            @can('accountants.view')
                <a href="{{ route('accountants.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Accountant list') }}</a>
            @endcan
            @can('update', $accountant)
                <a href="{{ route('accountants.edit', $accountant) }}" class="btn btn-light-soft"><i class="bi bi-pencil"></i>{{ __('Edit accountant') }}</a>
            @endcan
            <button type="button" class="btn btn-light-soft" onclick="window.print()"><i class="bi bi-printer"></i>{{ __('Print / Save as PDF') }}</button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <section class="ui-card h-100">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="avatar avatar-lg">
                        @if ($accountant->avatarUrl())
                            <img src="{{ $accountant->avatarUrl() }}" alt="">
                        @else
                            {{ $accountant->initials() }}
                        @endif
                    </span>
                    <div>
                        <h2 class="card-title mb-1">{{ $accountant->name }}</h2>
                        @if ($accountant->is_active)
                            <span class="badge-soft is-success"><span class="dot"></span>{{ __('Active') }}</span>
                        @else
                            <span class="badge-soft is-danger"><span class="dot"></span>{{ __('Inactive') }}</span>
                        @endif
                    </div>
                </div>

                <dl class="detail-list mb-0 mt-2">
                    <div>
                        <dt>{{ __('Employee ID') }}</dt>
                        <dd>{{ $employee?->employee_code ?: __('Not linked to staff records') }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Designation') }}</dt>
                        <dd>{{ $employee?->designation ?: $accountant->primaryRoleLabel() }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Sign-in mobile') }}</dt>
                        <dd>{{ $accountant->mobile_number ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Phone') }}</dt>
                        <dd>{{ $employee?->phone ?: ($accountant->phone ?: '—') }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Roles') }}</dt>
                        <dd>{{ $accountant->roles->pluck('name')->map(fn (string $role): string => __(ucfirst($role)))->implode(', ') ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Last sign-in') }}</dt>
                        <dd>{{ $accountant->last_login_at?->format('d M Y, h:i A') ?: __('Never') }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Account created') }}</dt>
                        <dd>{{ $accountant->created_at?->format('d M Y') ?: '—' }}</dd>
                    </div>
                </dl>

                @if ($canManage)
                    @can('changeStatus', $accountant)
                        <form method="POST" action="{{ route('accountants.status', $accountant) }}" class="mt-3"
                              data-confirm="{{ $accountant->is_active ? __('Deactivate :name? They will be signed out immediately.', ['name' => $accountant->name]) : __('Activate :name?', ['name' => $accountant->name]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn btn-light w-100">
                                <i class="bi {{ $accountant->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                                {{ $accountant->is_active ? __('Deactivate account') : __('Activate account') }}
                            </button>
                        </form>
                    @endcan
                @endif
            </section>
        </div>

        <div class="col-lg-8">
            <section class="stats-grid mb-0" aria-label="{{ __('Collection summary') }}">
                <article class="stat-card">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">{{ __('Today') }}</p>
                            <p class="stat-value">{{ Money::format($totals['today']) }}</p>
                        </div>
                        <span class="stat-icon" aria-hidden="true"><i class="bi bi-sunrise"></i></span>
                    </div>
                    <div class="stat-foot"><span>{{ now()->format('d M Y') }}</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">{{ __('This week') }}</p>
                            <p class="stat-value">{{ Money::format($totals['week']) }}</p>
                        </div>
                        <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-calendar-week"></i></span>
                    </div>
                    <div class="stat-foot"><span>{{ __('Monday to today') }}</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">{{ __('This month') }}</p>
                            <p class="stat-value">{{ Money::format($totals['month']) }}</p>
                        </div>
                        <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-calendar-month"></i></span>
                    </div>
                    <div class="stat-foot"><span>{{ now()->translatedFormat('F Y') }}</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">{{ __('This year') }}</p>
                            <p class="stat-value">{{ Money::format($totals['year']) }}</p>
                        </div>
                        <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-calendar-3-event"></i></span>
                    </div>
                    <div class="stat-foot"><span>{{ $summary['students'] > 0 ? trans_choice(':count student|:count students', $summary['students'], ['count' => $summary['students']]) : __('No students paid this year') }}</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">{{ __('Total collected') }}</p>
                            <p class="stat-value">{{ Money::format($totals['total']) }}</p>
                        </div>
                        <span class="stat-icon" aria-hidden="true"><i class="bi bi-cash-stack"></i></span>
                    </div>
                    <div class="stat-foot">
                        <span>{{ trans_choice(':count payment|:count payments', $lifetime_payments, ['count' => $lifetime_payments]) }} {{ __('since the first receipt') }}</span>
                    </div>
                </article>
                <article class="stat-card">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">{{ __('Reversed by them') }}</p>
                            <p class="stat-value">{{ Money::format($summary['reversed_amount']) }}</p>
                        </div>
                        <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-arrow-counterclockwise"></i></span>
                    </div>
                    <div class="stat-foot">
                        <span>{{ trans_choice(':count refund|:count refunds', $summary['reversed_count'], ['count' => $summary['reversed_count']]) }} {{ __('this year') }}</span>
                    </div>
                </article>
            </section>
        </div>
    </div>

    <div class="row g-3 mt-0">
        <div class="col-lg-7">
            <section class="ui-card h-100">
                <div class="table-toolbar">
                    <h2 class="card-title mb-0">{{ __('Monthly collection') }}</h2>
                    <span class="text-muted-2" style="font-size:.78rem;">{{ __('Last 12 months with activity') }}</span>
                </div>
                @if ($byMonth->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-bar-chart"></i></div>
                        <h3>{{ __('No collections yet') }}</h3>
                        <p>{{ __('A month appears here once a payment is posted under this account holder.') }}</p>
                    </div>
                @else
                    <div class="chart-wrap dash-chart">
                        <canvas id="accountantCollectionChart" role="img" aria-label="{{ __('Monthly collection for this accountant') }}"></canvas>
                    </div>
                    <div class="mt-2">
                        @php($peak = max($byMonth->max('received'), 1))
                        @foreach ($byMonth as $month)
                            <div class="progress-row">
                                <div class="progress-row-head">
                                    <span>{{ $month['label'] }}</span>
                                    <strong>{{ Money::format($month['received']) }}</strong>
                                </div>
                                <div class="progress-track" role="img" aria-label="{{ __(':month: :amount', ['month' => $month['label'], 'amount' => Money::format($month['received'])]) }}">
                                    <span style="width:{{ round($month['received'] / $peak * 100) }}%;"></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <div class="col-lg-5">
            <section class="ui-card h-100">
                <div class="table-toolbar">
                    <h2 class="card-title mb-0">{{ __('Student-wise collection') }}</h2>
                    <span class="text-muted-2" style="font-size:.78rem;">{{ __('This year') }}</span>
                </div>
                @if ($byStudent->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-people"></i></div>
                        <h3>{{ __('Nothing collected this year') }}</h3>
                        <p>{{ __('Students appear here against the receipts this accountant has posted.') }}</p>
                    </div>
                @else
                    <div class="table-responsive" style="max-height:430px;overflow:auto;">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Student') }}</th>
                                    <th scope="col">{{ __('Class') }}</th>
                                    <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($byStudent as $row)
                                    <tr>
                                        <td>
                                            <span class="fw-600">{{ $row['label'] }}</span>
                                            @if ($row['code'])
                                                <span class="d-block text-muted-2" style="font-size:.72rem;">{{ $row['code'] }}</span>
                                            @endif
                                        </td>
                                        <td>{{ trim(($row['class'] ?? '').' '.($row['section'] ?? '')) ?: '—' }}</td>
                                        <td class="text-end fw-600">{{ Money::format($row['received']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>

    <section class="ui-card table-card mt-3">
        <div class="table-toolbar">
            <h2 class="card-title mb-0">{{ __('Payment history') }}</h2>
            <form method="GET" action="{{ route('accountants.show', $accountant) }}" class="filter-form">
                <label class="visually-hidden" for="paid-from">{{ __('Paid from') }}</label>
                <input id="paid-from" type="date" name="from" class="form-control form-control-sm" value="{{ $filters['from']->toDateString() }}">
                <label class="visually-hidden" for="paid-to">{{ __('Paid to') }}</label>
                <input id="paid-to" type="date" name="to" class="form-control form-control-sm" value="{{ $filters['to']->toDateString() }}">
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                <a href="{{ route('accountants.show', $accountant) }}" class="btn btn-sm btn-link">{{ __('All time') }}</a>
            </form>
        </div>

        @if ($payments->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-receipt"></i></div>
                <h3>{{ __('No payments in this range') }}</h3>
                <p>{{ __('Posted receipts against this collector appear here with the student, invoice and amount.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Paid at') }}</th>
                            <th scope="col">{{ __('Receipt') }}</th>
                            <th scope="col">{{ __('Student') }}</th>
                            <th scope="col">{{ __('Class') }}</th>
                            <th scope="col">{{ __('Invoice') }}</th>
                            <th scope="col">{{ __('Method') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col" class="text-end">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $allocation)
                            @php($payment = $allocation->payment)
                            <tr>
                                <td>{{ $payment?->paid_at?->format('d M Y H:i') ?? '—' }}</td>
                                <td>
                                    @if ($payment?->receipt)
                                        <a href="{{ route('fees.receipt', $payment) }}">{{ $payment->receipt->receipt_no }}</a>
                                    @else
                                        <span class="text-muted-2">{{ __('No receipt') }}</span>
                                    @endif
                                </td>
                                <td>{{ $payment?->student?->full_name ?? __('Student not found') }}</td>
                                <td>
                                    @php($enrollment = $allocation->invoice?->enrollment)
                                    {{ trim(($enrollment?->schoolClass?->name ?? '').' '.($enrollment?->section?->name ?? '')) ?: '—' }}
                                </td>
                                <td style="font-size:.8rem;">{{ $allocation->invoice?->invoice_no ?? '—' }}</td>
                                <td><span class="badge-soft is-muted">{{ __(ucfirst((string) $payment?->method)) }}</span></td>
                                <td><span class="badge-soft {{ $payment?->status === 'posted' ? 'is-success' : 'is-warning' }}">{{ __(ucfirst((string) $payment?->status)) }}</span></td>
                                <td class="text-end fw-600">{{ Money::format($allocation->amount) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                {{ $payments->links() }}
            </div>
        @endif
    </section>

    <section class="ui-card table-card mt-3">
        <div class="table-toolbar">
            <h2 class="card-title mb-0">{{ __('Audit trail') }}</h2>
            <span class="text-muted-2" style="font-size:.78rem;">{{ __('fee_payment events by this account holder') }}</span>
        </div>
        @if ($audit === null)
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-shield-lock"></i></div>
                <h3>{{ __('Audit trail is not open to you') }}</h3>
                <p>{{ __('Payment edits, holds and reversals are recorded here and need the audit view permission to read.') }}</p>
            </div>
        @elseif ($audit->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-clipboard-check"></i></div>
                <h3>{{ __('No fee events recorded') }}</h3>
                <p>{{ __('Posting, holding, verifying and reversing a receipt each write one entry.') }}</p>
            </div>
        @else
            <div class="table-responsive" style="max-height:420px;overflow:auto;">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('When') }}</th>
                            <th scope="col">{{ __('Event') }}</th>
                            <th scope="col">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($audit as $entry)
                            <tr>
                                <td>{{ $entry->created_at?->format('d M Y H:i') }}</td>
                                <td><span class="badge-soft {{ str_contains($entry->event, 'reversed') || str_contains($entry->event, 'reject') ? 'is-danger' : 'is-muted' }}">{{ $entry->event }}</span></td>
                                <td style="font-size:.8rem;">{{ $entry->description }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-footer">{{ $audit->links() }}</div>
        @endif
    </section>
@endsection

@push('scripts')
    @if ($byMonth->isNotEmpty())
        <script src="{{ asset('assets/vendor/chartjs/chart.umd.min.js') }}"></script>
        <script>
            (function () {
                const canvas = document.getElementById('accountantCollectionChart');
                if (!canvas || !window.Chart) return;

                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: @json($byMonth->pluck('label')->all()),
                        datasets: [{
                            label: @json(__('Cash received')),
                            data: @json($byMonth->pluck('received')->all()),
                            backgroundColor: '#2563eb',
                            borderRadius: 5,
                            maxBarThickness: 26
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#0b1f41',
                                padding: 10,
                                cornerRadius: 10,
                                callbacks: {
                                    label: (context) => '৳' + Number(context.parsed.y).toLocaleString('en-IN')
                                }
                            }
                        },
                        scales: {
                            x: { grid: { display: false }, border: { display: false }, ticks: { color: '#8a99b0', font: { size: 10 } } },
                            y: {
                                beginAtZero: true,
                                grid: { color: '#eef3fa' },
                                border: { display: false },
                                ticks: { color: '#8a99b0', font: { size: 10 }, callback: (value) => '৳' + Number(value).toLocaleString('en-IN') }
                            }
                        }
                    }
                });
            })();
        </script>
    @endif
@endpush

@include('reports._print')
