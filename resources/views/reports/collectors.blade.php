@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Collector activity'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-badge"></i>{{ __('Reports') }}</span>
            <h1 class="page-title">{{ __('Collector activity and audit') }}</h1>
            <p class="page-subtitle">{{ __('Who took the money between :from and :to, and every posting, hold and reversal they signed off', [
                'from' => $filters['from']->format('d M Y'),
                'to' => $filters['to']->format('d M Y'),
            ]) }}</p>
        </div>
        @include('reports._actions', ['export' => 'collectors'])
    </div>

    @include('reports._filters')
    @include('reports._summary')

    <section class="ui-card table-card mb-3">
        <div class="table-toolbar"><h2 class="card-title mb-0">{{ __('By collector') }}</h2></div>
        @if ($rows->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-person-badge"></i></div>
                <h3>{{ __('No collections recorded') }}</h3>
                <p>{{ __('Activity appears once a payment is posted in this range.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Collector') }}</th>
                            <th scope="col" class="text-end">{{ __('Payments') }}</th>
                            <th scope="col" class="text-end">{{ __('Invoices') }}</th>
                            <th scope="col" class="text-end">{{ __('Standard fee') }}</th>
                            <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                            <th scope="col" class="text-end">{{ __('Reversals signed') }}</th>
                            <th scope="col" class="text-end">{{ __('Waiting verification') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="fw-600">
                                    @if ($row['key'] && auth()->user()->can('accountants.view'))
                                        <a href="{{ route('accountants.show', $row['key']) }}">{{ $row['label'] }}</a>
                                    @else
                                        {{ $row['label'] }}
                                    @endif
                                </td>
                                <td class="text-end">{{ number_format($row['payments']) }}</td>
                                <td class="text-end">{{ number_format($row['invoices']) }}</td>
                                <td class="text-end">{{ Money::format($row['standard_fee']) }}</td>
                                <td class="text-end fw-600">{{ Money::format($row['received']) }}</td>
                                <td class="text-end">{{ number_format($row['reversed']) }}</td>
                                <td class="text-end">{{ number_format($row['held']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <h2 class="card-title mb-0">{{ __('Audit trail') }}</h2>
            <span class="text-muted-2" style="font-size:.78rem;">{{ __('fee_payment events in range') }}</span>
        </div>
        @if ($audit === null)
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-shield-lock"></i></div>
                <h3>{{ __('Audit trail is not open to you') }}</h3>
                <p>{{ __('This page shows the money each collector handled. The event log needs the audit view permission.') }}</p>
            </div>
        @elseif ($audit->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-clipboard-check"></i></div>
                <h3>{{ __('No fee events in range') }}</h3>
                <p>{{ __('Postings, holds, verifications and reversals are written here as they happen.') }}</p>
            </div>
        @else
            <div class="table-responsive" style="max-height:520px;overflow:auto;">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('When') }}</th>
                            <th scope="col">{{ __('Who') }}</th>
                            <th scope="col">{{ __('Event') }}</th>
                            <th scope="col">{{ __('Details') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($audit as $entry)
                            <tr>
                                <td>{{ $entry->created_at?->format('d M Y H:i') }}</td>
                                <td>{{ $entry->user?->name ?? __('System') }}</td>
                                <td><span class="badge-soft {{ str_contains($entry->event, 'reversed') || str_contains($entry->event, 'reject') ? 'is-danger' : 'is-muted' }}">{{ $entry->event }}</span></td>
                                <td style="font-size:.8rem;">{{ $entry->description }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="table-toolbar border-top-0">{{ $audit->links() }}</div>
        @endif
    </section>
@endsection

@include('reports._print')
