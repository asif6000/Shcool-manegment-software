@use('App\Support\Money')
@extends('layouts.app')

@section('title', __('Student fee ledger'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-lines-fill"></i>{{ __('Fees') }}</span>
            <h1 class="page-title">{{ __('Student fee ledger') }}</h1>
            <p class="page-subtitle">{{ __('Billed, paid and outstanding totals per student for the selected session.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('fees.index') }}" class="btn btn-light-soft"><i class="bi bi-receipt"></i>{{ __('Invoices') }}</a>
            @can('fees.export')
                <a href="{{ route('fees.ledger.export', array_filter($filters)) }}" class="btn btn-light-soft"><i class="bi bi-filetype-csv"></i>{{ __('Export CSV') }}</a>
            @endcan
        </div>
    </div>

    <section class="stats-grid mb-3" aria-label="{{ __('Ledger summary') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Billed') }}</p>
                    <p class="stat-value">{{ Money::format($summary['billed']) }}</p>
                </div>
                <span class="stat-icon" aria-hidden="true"><i class="bi bi-receipt-cutoff"></i></span>
            </div>
            <div class="stat-foot"><span>{{ trans_choice(':count student|:count students', $summary['students'], ['count' => $summary['students']]) }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Collected') }}</p>
                    <p class="stat-value">{{ Money::format($summary['paid']) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
            </div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Outstanding') }}</p>
                    <p class="stat-value">{{ Money::format($summary['due']) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-exclamation-circle"></i></span>
            </div>
            <div class="stat-foot">
                @can('fees.view')
                    <a href="{{ route('fees.dues') }}" class="text-decoration-none">{{ __('Open due report') }}</a>
                @endcan
            </div>
        </article>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('fees.ledger') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="ledger-search">{{ __('Search students') }}</label>
                    <input id="ledger-search" type="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Name or student ID') }}">
                </div>
                <select name="session_id" class="form-select" data-auto-submit aria-label="{{ __('Session') }}">
                    <option value="">{{ __('All sessions') }}</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected($filters['session_id'] === (string) $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
                <select name="class_id" class="form-select" data-auto-submit aria-label="{{ __('Class') }}">
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($filters['class_id'] === (string) $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                <select name="standing" class="form-select" data-auto-submit aria-label="{{ __('Balance') }}">
                    <option value="">{{ __('Any balance') }}</option>
                    <option value="due" @selected($filters['standing'] === 'due')>{{ __('Has dues') }}</option>
                    <option value="clear" @selected($filters['standing'] === 'clear')>{{ __('Fully paid') }}</option>
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('fees.ledger') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count student|:count students', $students->total(), ['count' => $students->total()]) }}</span>
        </div>

        @if ($students->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-person-lines-fill"></i></div>
                <h3>{{ __('No student invoices found') }}</h3>
                <p>{{ array_filter($filters) ? __('No students match these filters.') : __('Generate fee invoices first so a ledger can be built.') }}</p>
                @can('fees.manage')
                    <a href="{{ route('fees.create') }}" class="btn btn-primary mt-3"><i class="bi bi-receipt-cutoff"></i>{{ __('Generate invoice') }}</a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Student') }}</th>
                            <th scope="col">{{ __('Class') }}</th>
                            <th scope="col" class="text-end">{{ __('Billed') }}</th>
                            <th scope="col" class="text-end">{{ __('Paid') }}</th>
                            <th scope="col" class="text-end">{{ __('Due') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            @php($enrollment = $student->enrollments->first())
                            <tr>
                                <td>
                                    <span class="fw-600">{{ $student->full_name }}</span>
                                    <div class="text-muted-2" style="font-size:.74rem;">{{ $student->student_id }}</div>
                                </td>
                                <td>
                                    {{ $enrollment?->schoolClass?->name ?? '—' }}
                                    @if ($enrollment?->section)
                                        <div class="text-muted-2" style="font-size:.74rem;">{{ $enrollment->section->name }} · {{ __('Roll :roll', ['roll' => $enrollment->roll_no ?? '—']) }}</div>
                                    @endif
                                </td>
                                <td class="text-end">{{ Money::format((float) ($student->billed_total ?? 0)) }}</td>
                                <td class="text-end text-success">{{ Money::format((float) ($student->paid_total ?? 0)) }}</td>
                                <td class="text-end fw-600 {{ (float) ($student->due_total ?? 0) > 0 ? 'text-danger' : 'text-success' }}">
                                    {{ Money::format((float) ($student->due_total ?? 0)) }}
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-light-soft" href="{{ route('fees.index', ['search' => $student->student_id]) }}">
                                        {{ __('Invoices') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $students->firstItem(), 'to' => $students->lastItem(), 'total' => $students->total()]) }}</span>
                {{ $students->links() }}
            </div>
        @endif
    </section>
@endsection
