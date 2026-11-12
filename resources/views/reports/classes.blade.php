@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Class and section collections'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-diagram-3"></i>{{ __('Reports') }}</span>
            <h1 class="page-title">{{ __('Class and section collections') }}</h1>
            <p class="page-subtitle">{{ __('Money received between :from and :to, grouped by the class each fee record belonged to', [
                'from' => $filters['from']->format('d M Y'),
                'to' => $filters['to']->format('d M Y'),
            ]) }}</p>
        </div>
        @include('reports._actions', ['export' => 'classes'])
    </div>

    @include('reports._filters')
    @include('reports._summary')

    <div class="row g-3">
        @foreach ([
            ['title' => __('By class'), 'rows' => $byClass, 'empty' => __('No class has a posted payment in this range.')],
            ['title' => __('By section'), 'rows' => $bySection, 'empty' => __('No section has a posted payment in this range.')],
        ] as $block)
            <div class="col-lg-6">
                <section class="ui-card table-card h-100">
                    <div class="table-toolbar"><h2 class="card-title mb-0">{{ $block['title'] }}</h2></div>
                    @if ($block['rows']->isEmpty())
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-school"></i></div>
                            <h3>{{ __('Nothing to group') }}</h3>
                            <p>{{ $block['empty'] }}</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ $block['title'] === __('By class') ? __('Class') : __('Class · section') }}</th>
                                        <th scope="col" class="text-end">{{ __('Invoices') }}</th>
                                        <th scope="col" class="text-end">{{ __('Payments') }}</th>
                                        <th scope="col" class="text-end">{{ __('Standard fee') }}</th>
                                        <th scope="col" class="text-end">{{ __('Cash received') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($block['rows'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td class="text-end">{{ number_format($row['invoices']) }}</td>
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
        @endforeach
    </div>

    <p class="text-muted-2 mt-3 mb-0" style="font-size:.78rem;">
        <i class="bi bi-info-circle"></i>
        {{ __('Every payment is counted in exactly one class and one section, so the class rows add back to the cash received total. A fee record with no enrollment lands in the unrecorded row instead of being dropped.') }}
    </p>
@endsection

@include('reports._print')
