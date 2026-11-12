@extends('layouts.app')

@section('title', __('Promotion reports'))

@php
    $actionTone = [
        'promote' => 'is-success',
        'repeat' => 'is-info',
        'hold' => 'is-warning',
        'transfer_out' => 'is-danger',
    ];
@endphp

@push('styles')
<style>
    .promotion-report-hero {
        border-radius: var(--radius-lg);
        padding: 1.25rem 1.4rem;
        color: #eef5ff;
        background:
            radial-gradient(circle at 92% 12%, rgba(56, 189, 248, .22), transparent 30%),
            linear-gradient(135deg, #0b1f41 0%, #102b58 60%, #12356a 100%);
        box-shadow: var(--shadow-soft);
    }
    .promotion-report-hero .eyebrow { display: inline-flex; align-items: center; gap: .4rem; color: #9cc3ff; font-size: .7rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
    .promotion-report-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .65rem; margin-top: 1rem; }
    .promotion-report-stat { padding: .7rem .8rem; border-radius: 14px; background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .09); }
    .promotion-report-stat span { display: flex; align-items: center; gap: .35rem; color: #9dbce8; font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; }
    .promotion-report-stat strong { display: block; color: #fff; font-size: 1.45rem; line-height: 1.25; }
    .promotion-report-table td { font-size: .8rem; vertical-align: middle; }
    .promotion-report-table th { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); white-space: nowrap; }
    .promotion-route { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; }
    .promotion-route .route-box { padding: .3rem .5rem; border-radius: 9px; background: var(--canvas); border: 1px solid var(--line); line-height: 1.25; }
    .promotion-route .route-box small { display: block; color: var(--muted); font-size: .65rem; }
    @media (max-width: 991.98px) { .promotion-report-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-bar-chart-line"></i>{{ __('Academic management') }}</span>
            <h1 class="page-title">{{ __('Promotion reports') }}</h1>
            <p class="page-subtitle">{{ __('Promoted, repeated, held and transferred-out students, with the exact source and target placements recorded at the time of processing.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('promotions.index', ['from_session_id' => $fromSession?->id]) }}" class="btn btn-light-soft">
                <i class="bi bi-arrow-left"></i>{{ __('Promotion workspace') }}
            </a>
            <a href="{{ route('promotions.report.export', $filters) }}" class="btn btn-primary">
                <i class="bi bi-filetype-csv"></i>{{ __('Export CSV') }}
            </a>
        </div>
    </div>

    <section class="promotion-report-hero mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
            <div>
                <span class="eyebrow"><i class="bi bi-clipboard-data"></i>{{ __('Batch outcomes') }}</span>
                <div class="mt-2 h5 mb-0">{{ $fromSession?->name ?? __('All sessions') }}</div>
                <div class="small mt-1" style="color:#b9d0ee;">{{ trans_choice(':count recorded promotion|:count recorded promotions', $total, ['count' => $total]) }}</div>
            </div>
            @if ($currentSession)
                <span class="badge-soft is-success"><i class="bi bi-calendar-check"></i>{{ __('Current session') }}: {{ $currentSession->name }}</span>
            @endif
        </div>
        <div class="promotion-report-grid">
            @foreach ($actions as $key => $label)
                <a href="{{ route('promotions.report', array_merge($filters, ['action' => $key, 'from_session_id' => $fromSession?->id])) }}" class="promotion-report-stat text-decoration-none">
                    <span>
                        <i class="bi {{ $key === 'promote' ? 'bi-arrow-up-circle' : ($key === 'repeat' ? 'bi-arrow-repeat' : ($key === 'hold' ? 'bi-pause-circle' : 'bi-box-arrow-right')) }}"></i>
                        {{ $label }}
                    </span>
                    <strong>{{ $summary[$key] ?? 0 }}</strong>
                </a>
            @endforeach
        </div>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('promotions.report') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="report-search">{{ __('Search students') }}</label>
                    <input id="report-search" type="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Name, student ID or admission no') }}">
                </div>
                <label class="visually-hidden" for="report-from-session">{{ __('Source session') }}</label>
                <select id="report-from-session" name="from_session_id" class="form-select" data-auto-submit>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected(($filters['from_session_id'] ?? $fromSession?->id) == $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="report-to-session">{{ __('Target session') }}</label>
                <select id="report-to-session" name="to_session_id" class="form-select" data-auto-submit>
                    <option value="">{{ __('All target sessions') }}</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected(($filters['to_session_id'] ?? '') == $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="report-action">{{ __('Action') }}</label>
                <select id="report-action" name="action" class="form-select" data-auto-submit>
                    <option value="">{{ __('All actions') }}</option>
                    @foreach ($actions as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['action'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="report-class">{{ __('Class') }}</label>
                <select id="report-class" name="class_id" class="form-select" data-auto-submit>
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(($filters['class_id'] ?? '') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('promotions.report') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count record|:count records', $promotions->total(), ['count' => $promotions->total()]) }}</span>
        </div>

        @if ($promotions->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-clock-history"></i></div>
                <h3>{{ __('No promotion records yet') }}</h3>
                <p>{{ __('Process a promotion batch and its outcomes will appear here for audit and reporting.') }}</p>
                <a href="{{ route('promotions.index') }}" class="btn btn-primary mt-3"><i class="bi bi-arrow-up-right-circle"></i>{{ __('Open promotion workspace') }}</a>
            </div>
        @else
            <div class="table-responsive">
                <table class="table promotion-report-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Student') }}</th>
                            <th>{{ __('Action') }}</th>
                            <th>{{ __('From') }}</th>
                            <th>{{ __('To') }}</th>
                            <th>{{ __('Result') }}</th>
                            <th>{{ __('Recorded') }}</th>
                            <th>{{ __('Notes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($promotions as $promotion)
                            <tr>
                                <td>
                                    <a href="{{ $promotion->student ? route('students.show', $promotion->student) : '#' }}" class="fw-600 text-decoration-none">{{ $promotion->student?->full_name ?? '—' }}</a>
                                    <div class="text-muted-2" style="font-size:.72rem;">{{ $promotion->student?->student_id }} · {{ $promotion->student?->admission_no }}</div>
                                </td>
                                <td>
                                    <span class="badge-soft {{ $actionTone[$promotion->action] ?? '' }}">{{ $promotion->actionLabel() }}</span>
                                    <div class="text-muted-2" style="font-size:.68rem;">{{ __(ucfirst($promotion->status)) }}</div>
                                </td>
                                <td>
                                    <div class="promotion-route">
                                        <span class="route-box">
                                            <strong>{{ $promotion->fromSession?->name ?? '—' }}</strong>
                                            <small>{{ $promotion->fromClass?->name ?? '—' }}{{ $promotion->fromSection?->name ? ' · '.$promotion->fromSection->name : '' }} · {{ __('Roll') }} {{ $promotion->from_roll_no ?? '—' }}</small>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if ($promotion->toSession || $promotion->toClass)
                                        <div class="promotion-route">
                                            <i class="bi bi-arrow-right text-muted-2"></i>
                                            <span class="route-box">
                                                <strong>{{ $promotion->toSession?->name ?? '—' }}</strong>
                                                <small>{{ $promotion->toClass?->name ?? '—' }}{{ $promotion->toSection?->name ? ' · '.$promotion->toSection->name : '' }} · {{ __('Roll') }} {{ $promotion->to_roll_no ?? '—' }}</small>
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-muted-2">{{ $promotion->action === 'hold' ? __('Held in current placement') : __('No new enrollment') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($promotion->result_snapshot)
                                        <div class="fw-600">{{ isset($promotion->result_snapshot['gpa']) ? number_format((float) $promotion->result_snapshot['gpa'], 2) : '—' }} GPA</div>
                                        <div class="text-muted-2" style="font-size:.72rem;">{{ $promotion->resultExam?->name ?? ($promotion->result_snapshot['exam_name'] ?? '') }}</div>
                                        <span class="badge-soft {{ ($promotion->eligibility ?? '') === 'eligible' ? 'is-success' : (($promotion->eligibility ?? '') === 'ineligible' ? 'is-danger' : 'is-warning') }}">{{ __(ucfirst((string) ($promotion->eligibility ?? 'unavailable'))) }}</span>
                                    @else
                                        <span class="text-muted-2">{{ __('No result snapshot') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ $promotion->promoted_at?->format('d M Y, h:i A') ?? '—' }}</div>
                                    <div class="text-muted-2" style="font-size:.72rem;">{{ $promotion->promoter?->name ?? '—' }}</div>
                                </td>
                                <td>
                                    <span class="text-muted-2" style="font-size:.75rem;">{{ $promotion->notes ? \Illuminate\Support\Str::limit($promotion->notes, 80) : '—' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from-:to of :total', ['from' => $promotions->firstItem(), 'to' => $promotions->lastItem(), 'total' => $promotions->total()]) }}</span>
                {{ $promotions->links() }}
            </div>
        @endif
    </section>
@endsection
