@extends('layouts.app')

@section('title', __('Student promotion'))

@php
    $actions = \App\Models\StudentPromotion::actionLabels();
    $promotionResult = session('promotion_result');
    $canPromote = auth()->user()?->can('academic.manage');
    $eligibilityTone = fn (array $eligibility): string => match ($eligibility['tone']) {
        'success' => 'is-success',
        'danger' => 'is-danger',
        'muted' => 'is-warning',
        default => '',
    };
@endphp

@push('styles')
<style>
    .promotion-hero {
        position: relative;
        overflow: hidden;
        border: 0;
        border-radius: var(--radius-lg);
        padding: 1.35rem 1.45rem;
        color: #eef5ff;
        background:
            radial-gradient(circle at 88% 15%, rgba(56, 189, 248, .24), transparent 28%),
            radial-gradient(circle at 4% 110%, rgba(124, 58, 237, .24), transparent 34%),
            linear-gradient(135deg, #0b1f41 0%, #102b58 55%, #12356a 100%);
        box-shadow: var(--shadow-soft);
    }
    .promotion-hero::after {
        content: "";
        position: absolute;
        inset: auto -60px -80px auto;
        width: 220px;
        height: 220px;
        border: 1px solid rgba(255, 255, 255, .13);
        border-radius: 50%;
        box-shadow: 0 0 0 28px rgba(255, 255, 255, .03), 0 0 0 56px rgba(255, 255, 255, .018);
        pointer-events: none;
    }
    .promotion-hero .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .11em;
        text-transform: uppercase;
        color: #9cc3ff;
    }
    .promotion-flow {
        display: flex;
        align-items: center;
        gap: .7rem;
        flex-wrap: wrap;
        margin-top: .8rem;
    }
    .promotion-session {
        min-width: 150px;
        padding: .7rem .9rem;
        border: 1px solid rgba(255, 255, 255, .14);
        border-radius: var(--radius-md);
        background: rgba(255, 255, 255, .07);
        backdrop-filter: blur(4px);
    }
    .promotion-session span { display: block; color: #9cc3ff; font-size: .68rem; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; }
    .promotion-session strong { display: block; font-size: 1.12rem; margin-top: .1rem; color: #fff; }
    .promotion-flow-arrow { color: #7fb3ff; font-size: 1.2rem; }
    .promotion-hero-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .55rem; margin-top: 1.05rem; }
    .promotion-hero-stat { padding: .55rem .65rem; border-radius: 12px; background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .08); }
    .promotion-hero-stat span { display: block; color: #9dbce8; font-size: .68rem; text-transform: uppercase; letter-spacing: .07em; font-weight: 700; }
    .promotion-hero-stat strong { display: block; font-size: 1.25rem; color: #fff; line-height: 1.2; }
    .promotion-toolbar {
        display: grid;
        grid-template-columns: minmax(140px, .9fr) minmax(150px, 1fr) minmax(150px, 1fr) minmax(130px, .8fr) auto;
        gap: .55rem;
        align-items: end;
        padding: .9rem 1rem;
        border-top: 1px solid var(--line);
        border-bottom: 1px solid var(--line);
        background: linear-gradient(180deg, #f8fbff, #fff);
    }
    .promotion-toolbar .field-label { display: block; font-size: .68rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); margin-bottom: .22rem; }
    .promotion-toolbar .form-select, .promotion-toolbar .form-control { min-height: 38px; }
    .promotion-toolbar-actions { display: flex; gap: .45rem; align-items: center; }
    .promotion-selection {
        display: flex; align-items: center; gap: .45rem;
        padding: .45rem .65rem; border-radius: 999px;
        background: var(--brand-50); color: var(--brand-700);
        font-size: .76rem; font-weight: 700; white-space: nowrap;
    }
    .promotion-row.is-selected { background: #f3f8ff; }
    .promotion-person { display: flex; align-items: center; gap: .6rem; min-width: 190px; }
    .promotion-person .avatar { width: 34px; height: 34px; font-size: .72rem; }
    .promotion-person .name { font-weight: 600; color: var(--ink); }
    .promotion-person .meta { color: var(--muted); font-size: .72rem; }
    .promotion-result { font-size: .78rem; line-height: 1.35; }
    .promotion-result strong { font-size: .86rem; }
    .promotion-history { display: flex; gap: .4rem; flex-wrap: wrap; }
    .promotion-history span { display: inline-flex; align-items: center; gap: .25rem; font-size: .68rem; padding: .16rem .4rem; border-radius: 999px; background: var(--canvas); color: var(--ink-soft); white-space: nowrap; }
    .promotion-badge { display: inline-flex; align-items: center; gap: .28rem; padding: .2rem .5rem; border-radius: 999px; font-size: .7rem; font-weight: 700; }
    .promotion-badge.is-ready { color: var(--success); background: var(--success-soft); }
    .promotion-badge.is-held { color: var(--warning); background: var(--warning-soft); }
    .promotion-preview-table th { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); white-space: nowrap; }
    .promotion-preview-table td { font-size: .78rem; vertical-align: middle; }
    .promotion-warning-list { margin: .25rem 0 0; padding-left: 1rem; color: var(--warning); font-size: .7rem; }
    .promotion-error-list { margin: .25rem 0 0; padding-left: 1rem; color: var(--danger); font-size: .7rem; }
    .promotion-modal-summary { display: flex; gap: .45rem; flex-wrap: wrap; }
    .promotion-overlay {
        position: fixed; inset: 0; z-index: 2000;
        display: grid; place-items: center;
        padding: 1rem;
        background: rgba(7, 22, 47, .62);
        backdrop-filter: blur(4px);
    }
    .promotion-overlay-card {
        width: min(430px, 100%);
        padding: 1.4rem;
        border-radius: var(--radius-lg);
        background: #fff;
        box-shadow: 0 30px 90px rgba(7, 22, 47, .28);
        text-align: center;
    }
    .promotion-overlay-card .spinner-border { width: 2.4rem; height: 2.4rem; }
    .promotion-overlay-card .progress { height: 8px; border-radius: 999px; overflow: hidden; }
    .promotion-overlay-card .progress-bar { width: 40%; animation: promotion-progress 1.4s ease-in-out infinite alternate; }
    @keyframes promotion-progress { from { margin-left: 0; width: 28%; } to { margin-left: 72%; width: 28%; } }
    .promotion-result-banner { border-left: 4px solid var(--success); }
    @media (max-width: 1199.98px) {
        .promotion-toolbar { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .promotion-toolbar-actions { grid-column: span 3; }
    }
    @media (max-width: 767.98px) {
        .promotion-hero-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .promotion-toolbar { grid-template-columns: 1fr 1fr; }
        .promotion-toolbar-actions { grid-column: span 2; justify-content: space-between; }
        .promotion-flow { gap: .45rem; }
        .promotion-session { min-width: 0; flex: 1 1 42%; }
    }
</style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-arrow-up-right-circle"></i>{{ __('Academic management') }}</span>
            <h1 class="page-title">{{ __('Student promotion') }}</h1>
            <p class="page-subtitle">{{ __('Move students into the next academic session with a preview, explicit confirmation and a full audit trail. Previous results, attendance, fees and payments are never overwritten.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('promotions.report', ['from_session_id' => $fromSession?->id]) }}" class="btn btn-light-soft">
                <i class="bi bi-bar-chart-line"></i>{{ __('Promotion reports') }}
            </a>
        </div>
    </div>

    @if ($promotionResult)
        <div class="alert alert-success promotion-result-banner d-flex align-items-start gap-2 mb-3">
            <i class="bi bi-check2-circle fs-5"></i>
            <div>
                <strong>{{ __('Batch completed') }}</strong>
                <div>
                    {{ $promotionResult['action_label'] }} · {{ $promotionResult['processed'] }} {{ trans_choice('student|students', $promotionResult['processed']) }}
                    @if ($promotionResult['to_session'])
                        {{ __('into') }} <strong>{{ $promotionResult['to_session'] }}</strong>
                        @if ($promotionResult['target_class'])
                            · {{ $promotionResult['target_class'] }}{{ $promotionResult['target_section'] ? ' · '.$promotionResult['target_section'] : '' }}
                        @endif
                    @endif
                </div>
                <div class="small text-muted-2 mt-1">{{ __('Old enrollments, results, attendance, fees and payment history remain intact.') }}</div>
            </div>
        </div>
    @endif

    <section class="promotion-hero mb-3">
        <div class="d-flex flex-wrap justify-content-between gap-3">
            <div>
                <span class="eyebrow"><i class="bi bi-diagram-3"></i>{{ __('Promotion workspace') }}</span>
                <div class="promotion-flow">
                    <div class="promotion-session">
                        <span>{{ __('Current session') }}</span>
                        <strong>{{ $fromSession?->name ?? __('Not configured') }}</strong>
                    </div>
                    <i class="bi bi-arrow-right promotion-flow-arrow"></i>
                    <div class="promotion-session">
                        <span>{{ __('Suggested next session') }}</span>
                        <strong>{{ $targetSession?->name ?? __('Choose below') }}</strong>
                    </div>
                    <div class="promotion-session">
                        <span>{{ __('Selection') }}</span>
                        <strong id="heroSelectionCount">0</strong>
                    </div>
                </div>
            </div>
            <div class="text-md-end">
                <span class="eyebrow">{{ __('Safety') }}</span>
                <div class="small mt-2" style="max-width:260px;color:#b9d0ee;">
                    {{ __('Bulk writes run inside one database transaction. A conflict rolls the whole batch back.') }}
                </div>
            </div>
        </div>
        <div class="promotion-hero-stats">
            <div class="promotion-hero-stat"><span>{{ __('Candidates') }}</span><strong>{{ $summary['total'] }}</strong></div>
            <div class="promotion-hero-stat"><span>{{ __('Eligible (page)') }}</span><strong>{{ $summary['eligible'] }}</strong></div>
            <div class="promotion-hero-stat"><span>{{ __('Needs review (page)') }}</span><strong>{{ $summary['ineligible'] + $summary['unavailable'] }}</strong></div>
            <div class="promotion-hero-stat"><span>{{ __('Processed (page)') }}</span><strong>{{ $summary['processed'] }}</strong></div>
        </div>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('promotions.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="promotion-search">{{ __('Search students') }}</label>
                    <input id="promotion-search" type="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Name, student ID, admission no or phone') }}">
                </div>
                <label class="visually-hidden" for="promotion-session">{{ __('Source session') }}</label>
                <select id="promotion-session" name="from_session_id" class="form-select" data-auto-submit>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected(($filters['from_session_id'] ?? $fromSession?->id) == $session->id)>{{ $session->name }}{{ $session->is_current ? ' · '.__('Current') : '' }}</option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="promotion-class">{{ __('Current class') }}</label>
                <select id="promotion-class" name="class_id" class="form-select" data-auto-submit>
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(($filters['class_id'] ?? '') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                @if ($sourceSections->isNotEmpty())
                    <label class="visually-hidden" for="promotion-section">{{ __('Current section') }}</label>
                    <select id="promotion-section" name="section_id" class="form-select" data-auto-submit>
                        <option value="">{{ __('All sections') }}</option>
                        @foreach ($sourceSections as $section)
                            <option value="{{ $section->id }}" @selected(($filters['section_id'] ?? '') == $section->id)>{{ __('Section') }} {{ $section->name }}</option>
                        @endforeach
                    </select>
                @endif
                <label class="visually-hidden" for="promotion-state">{{ __('Processing state') }}</label>
                <select id="promotion-state" name="state" class="form-select" data-auto-submit>
                    <option value="">{{ __('All students') }}</option>
                    <option value="pending" @selected(($filters['state'] ?? '') === 'pending')>{{ __('Not processed') }}</option>
                    <option value="processed" @selected(($filters['state'] ?? '') === 'processed')>{{ __('Already processed') }}</option>
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('promotions.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count candidate|:count candidates', $students->total(), ['count' => $students->total()]) }}</span>
        </div>

        @if ($fromSession === null)
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
                <h3>{{ __('No academic session yet') }}</h3>
                <p>{{ __('Create an academic session and enroll students before promoting them.') }}</p>
                @can('academic.manage')
                    <a href="{{ route('academic.index') }}" class="btn btn-primary mt-3">{{ __('Open academic setup') }}</a>
                @endcan
            </div>
        @else
            <form method="POST" action="{{ route('promotions.store') }}" id="promotionForm" novalidate>
                @csrf
                <input type="hidden" name="from_session_id" value="{{ $fromSession->id }}">
                <input type="hidden" name="notes" id="promotionNotesHidden" value="{{ old('notes') }}">

                <div class="promotion-toolbar">
                    <div>
                        <label class="field-label" for="promotion-action">{{ __('Action') }}</label>
                        <select id="promotion-action" name="action" class="form-select" required>
                            @foreach ($actions as $value => $label)
                                <option value="{{ $value }}" @selected(old('action', 'promote') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-target-field>
                        <label class="field-label" for="promotion-target-session">{{ __('Target session') }}</label>
                        <select id="promotion-target-session" name="target_session_id" class="form-select">
                            <option value="">{{ __('Choose session') }}</option>
                            @foreach ($sessionsForTarget as $session)
                                <option value="{{ $session->id }}" @selected((string) old('target_session_id', $targetSession?->id) === (string) $session->id)>{{ $session->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-target-field>
                        <label class="field-label" for="promotion-target-class">{{ __('Target class') }}</label>
                        <select id="promotion-target-class" name="target_class_id" class="form-select">
                            <option value="">{{ __('Choose class') }}</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" data-level="{{ $class->level }}" data-name="{{ $class->name }}" @selected((string) old('target_class_id') === (string) $class->id)>{{ $class->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div data-target-field>
                        <label class="field-label" for="promotion-target-section">{{ __('Target section') }}</label>
                        <select id="promotion-target-section" name="target_section_id" class="form-select">
                            <option value="">{{ __('Choose section') }}</option>
                            @foreach ($classes as $class)
                                @foreach ($class->sections as $section)
                                    <option value="{{ $section->id }}" data-class-id="{{ $class->id }}" data-name="{{ $section->name }}">{{ $class->name }} · {{ __('Section') }} {{ $section->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div data-target-field>
                        <label class="field-label" for="promotion-roll-strategy">{{ __('Roll numbers') }}</label>
                        <select id="promotion-roll-strategy" name="roll_strategy" class="form-select">
                            <option value="auto" @selected(old('roll_strategy', 'auto') === 'auto')>{{ __('Auto (next free)') }}</option>
                            <option value="keep" @selected(old('roll_strategy') === 'keep')>{{ __('Keep old roll if free') }}</option>
                            <option value="manual" @selected(old('roll_strategy') === 'manual')>{{ __('Manual') }}</option>
                        </select>
                    </div>
                    <div class="d-none" data-target-field id="promotionManualRollWrap">
                        <label class="field-label" for="promotion-roll-no">{{ __('Manual roll') }}</label>
                        <input id="promotion-roll-no" type="number" name="roll_no" min="1" max="9999" value="{{ old('roll_no') }}" class="form-control" placeholder="{{ __('One student only') }}">
                    </div>
                    <div class="promotion-toolbar-actions">
                        <span class="promotion-selection"><i class="bi bi-check2-square"></i><span id="selectionCount">0</span> {{ __('selected') }}</span>
                        @if ($canPromote)
                            <button type="button" id="previewButton" class="btn btn-primary">
                                <i class="bi bi-eye"></i>{{ __('Preview') }}
                            </button>
                        @else
                            <span class="badge-soft is-warning">{{ __('View only') }}</span>
                        @endif
                    </div>
                </div>
                <div id="promotionFeedback" class="alert d-none mx-3 mt-3 mb-0" role="alert"></div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" style="width:42px;">
                                    <input id="selectAll" class="form-check-input" type="checkbox" aria-label="{{ __('Select all on this page') }}">
                                </th>
                                <th scope="col">{{ __('Student') }}</th>
                                <th scope="col">{{ __('Current placement') }}</th>
                                <th scope="col">{{ __('Latest result') }}</th>
                                <th scope="col">{{ __('Eligibility') }}</th>
                                <th scope="col">{{ __('Preserved history') }}</th>
                                <th scope="col">{{ __('Promotion state') }}</th>
                                <th scope="col" class="text-end">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($students as $enrollment)
                                @php
                                    $student = $enrollment->student;
                                    $ctx = $context[(int) $enrollment->student_id] ?? null;
                                    $eligibility = $ctx['eligibility'] ?? ['key' => 'unavailable', 'label' => __('Not available'), 'tone' => 'muted', 'reason' => ''];
                                    $result = $ctx['result'] ?? null;
                                    $promotion = $ctx['promotion'] ?? null;
                                    $history = $ctx['history'] ?? ['attendance_count' => 0, 'fee_invoice_count' => 0, 'fee_paid_total' => 0];
                                @endphp
                                <tr class="promotion-row {{ $promotion ? 'is-selected' : '' }}"
                                    data-student-id="{{ $student->id }}"
                                    data-from-class-id="{{ $enrollment->class_id }}"
                                    data-from-class-level="{{ $enrollment->schoolClass?->level }}"
                                    data-from-section-name="{{ $enrollment->section?->name }}"
                                    data-processed="{{ $promotion ? '1' : '0' }}">
                                    <td>
                                        <input class="form-check-input promotion-checkbox" type="checkbox" name="student_ids[]" value="{{ $student->id }}" @disabled($promotion !== null) aria-label="{{ __('Select :name', ['name' => $student->full_name]) }}">
                                    </td>
                                    <td>
                                        <div class="promotion-person">
                                            <span class="avatar">
                                                @if ($student->photoUrl())
                                                    <img src="{{ $student->photoUrl() }}" alt="">
                                                @else
                                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($student->first_name, 0, 1).\Illuminate\Support\Str::substr($student->last_name ?? '', 0, 1)) }}
                                                @endif
                                            </span>
                                            <span>
                                                <a href="{{ route('students.show', $student) }}" class="name text-decoration-none">{{ $student->full_name }}</a>
                                                <span class="meta d-block">{{ $student->student_id }} · {{ $student->admission_no }}</span>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-600">{{ $enrollment->schoolClass?->name ?? '—' }}</div>
                                        <div class="text-muted-2" style="font-size:.75rem;">{{ $enrollment->section?->name ? __('Section').' '.$enrollment->section->name : __('No section') }} · {{ __('Roll') }} {{ $enrollment->roll_no ?? '—' }}</div>
                                    </td>
                                    <td>
                                        @if ($result)
                                            <div class="promotion-result">
                                                <strong>{{ $result['gpa'] !== null ? number_format($result['gpa'], 2) : '—' }} GPA</strong>
                                                @if ($result['grade_letter'])
                                                    <span class="badge-soft ms-1">{{ $result['grade_letter'] }}</span>
                                                @endif
                                                <div class="text-muted-2">{{ $result['exam_name'] }}</div>
                                                <div class="text-muted-2">{{ number_format($result['total_obtained'], 0) }} / {{ number_format($result['total_full_marks'], 0) }} {{ __('marks') }}</div>
                                            </div>
                                        @else
                                            <span class="badge-soft is-warning">{{ __('No published result') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge-soft {{ $eligibilityTone($eligibility) }}" title="{{ $eligibility['reason'] }}">{{ $eligibility['label'] }}</span>
                                        <div class="text-muted-2" style="font-size:.7rem;max-width:170px;">{{ $eligibility['reason'] }}</div>
                                    </td>
                                    <td>
                                        <div class="promotion-history">
                                            <span title="{{ __('Attendance records in this session') }}"><i class="bi bi-calendar2-check"></i>{{ $history['attendance_count'] }}</span>
                                            <span title="{{ __('Fee invoices in this session') }}"><i class="bi bi-receipt"></i>{{ $history['fee_invoice_count'] }}</span>
                                            <span title="{{ __('Paid amount in this session') }}"><i class="bi bi-cash-coin"></i>{{ number_format($history['fee_paid_total'], 0) }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($promotion)
                                            <span class="badge-soft is-success"><i class="bi bi-check2"></i>{{ $promotion->actionLabel() }}</span>
                                            <div class="text-muted-2" style="font-size:.7rem;">{{ $promotion->promoted_at?->format('d M Y') }}</div>
                                        @else
                                            <span class="promotion-badge is-ready"><i class="bi bi-circle-fill" style="font-size:.35rem;"></i>{{ __('Ready') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if ($promotion)
                                            <span class="text-muted-2" style="font-size:.72rem;">{{ __('Already processed') }}</span>
                                        @elseif ($canPromote)
                                            <button type="button" class="btn btn-sm btn-light-soft" data-row-select title="{{ __('Select this student and preview') }}">
                                                <i class="bi bi-arrow-up-right"></i>{{ __('Process') }}
                                            </button>
                                        @else
                                            <span class="text-muted-2" style="font-size:.72rem;">·</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8">
                                        <div class="empty-state py-4">
                                            <div class="empty-icon"><i class="bi bi-person-check"></i></div>
                                            <h3>{{ __('No candidates found') }}</h3>
                                            <p>{{ __('Adjust the filters or enroll students in the selected session first.') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </form>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from-:to of :total', ['from' => $students->firstItem() ?? 0, 'to' => $students->lastItem() ?? 0, 'total' => $students->total()]) }}</span>
                {{ $students->links() }}
            </div>
        @endif
    </section>

    @if ($canPromote)
        <div class="modal fade" id="promotionPreviewModal" tabindex="-1" aria-labelledby="promotionPreviewTitle" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5 mb-1" id="promotionPreviewTitle">{{ __('Promotion preview') }}</h2>
                            <p class="text-muted-2 mb-0" style="font-size:.78rem;" id="previewSubtitle">{{ __('Review every change before confirming.') }}</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="promotion-modal-summary mb-3" id="previewSummary"></div>
                        <div id="previewAlert" class="alert d-none" role="alert"></div>
                        <div class="table-responsive">
                            <table class="table promotion-preview-table align-middle">
                                <thead>
                                    <tr>
                                        <th>{{ __('Student') }}</th>
                                        <th>{{ __('Current') }}</th>
                                        <th></th>
                                        <th>{{ __('Target') }}</th>
                                        <th>{{ __('Roll') }}</th>
                                        <th>{{ __('Result') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="previewRows"></tbody>
                            </table>
                        </div>
                        <div class="mt-3">
                            <label class="form-label" for="promotionNotes">{{ __('Notes (optional)') }}</label>
                            <textarea id="promotionNotes" class="form-control" rows="2" maxlength="1000" placeholder="{{ __('Reason, approval reference or special instruction') }}"></textarea>
                            <div class="form-hint mt-1">{{ __('Notes are stored on every promotion record in this batch.') }}</div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0 d-flex gap-2 align-items-start">
                            <i class="bi bi-shield-check"></i>
                            <div class="small">
                                {{ __('Historical records are preserved: the previous enrollment keeps its session, class, section and roll. Results, attendance, invoices and payments are not modified.') }}
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" form="promotionForm" class="btn btn-primary" id="confirmPromotionButton">
                            <i class="bi bi-check2-circle"></i>{{ __('Confirm and process') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="promotionProgress" class="promotion-overlay d-none" role="status" aria-live="polite">
            <div class="promotion-overlay-card">
                <div class="spinner-border text-primary mb-3" role="presentation"></div>
                <h2 class="h5 mb-1">{{ __('Processing promotion') }}</h2>
                <p class="text-muted-2 small mb-3">{{ __('The batch is running in one database transaction. Please keep this tab open until it finishes.') }}</p>
                <div class="progress" role="progressbar" aria-label="{{ __('Promotion in progress') }}">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary"></div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('promotionForm');
    if (!form) return;

    const previewUrl = @json(route('promotions.preview'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const modalElement = document.getElementById('promotionPreviewModal');
    const previewModal = modalElement && window.bootstrap ? new bootstrap.Modal(modalElement) : null;
    const actionField = document.getElementById('promotion-action');
    const targetFields = document.querySelectorAll('[data-target-field]');
    const targetSession = document.getElementById('promotion-target-session');
    const targetClass = document.getElementById('promotion-target-class');
    const targetSection = document.getElementById('promotion-target-section');
    const rollStrategy = document.getElementById('promotion-roll-strategy');
    const manualRollWrap = document.getElementById('promotionManualRollWrap');
    const manualRoll = document.getElementById('promotion-roll-no');
    const checkboxList = Array.from(document.querySelectorAll('.promotion-checkbox'));
    const selectAll = document.getElementById('selectAll');
    const selectionCount = document.getElementById('selectionCount');
    const heroSelectionCount = document.getElementById('heroSelectionCount');
    const previewButton = document.getElementById('previewButton');
    const previewRows = document.getElementById('previewRows');
    const previewSummary = document.getElementById('previewSummary');
    const previewSubtitle = document.getElementById('previewSubtitle');
    const previewAlert = document.getElementById('previewAlert');
    const confirmButton = document.getElementById('confirmPromotionButton');
    const feedback = document.getElementById('promotionFeedback');
    const notes = document.getElementById('promotionNotes');
    const notesHidden = document.getElementById('promotionNotesHidden');
    const progress = document.getElementById('promotionProgress');
    let selectedIds = [];
    let previewLoading = false;
    let submitting = false;

    const actionNeedsTarget = function (action) {
        return action === 'promote' || action === 'repeat';
    };

    const escapeHtml = function (value) {
        return String(value ?? '').replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character];
        });
    };

    const selectedCheckboxes = function () {
        return checkboxList.filter(function (checkbox) {
            return checkbox.checked && !checkbox.disabled;
        });
    };

    const refreshSelection = function () {
        selectedIds = selectedCheckboxes().map(function (checkbox) {
            return Number(checkbox.value);
        });

        selectionCount.textContent = String(selectedIds.length);
        heroSelectionCount.textContent = String(selectedIds.length);

        if (selectAll) {
            const eligibleCheckboxes = checkboxList.filter(function (checkbox) { return !checkbox.disabled; });
            selectAll.checked = eligibleCheckboxes.length > 0 && eligibleCheckboxes.every(function (checkbox) { return checkbox.checked; });
            selectAll.indeterminate = selectedIds.length > 0 && !selectAll.checked;
        }

        document.querySelectorAll('.promotion-row').forEach(function (row) {
            const checkbox = row.querySelector('.promotion-checkbox');
            row.classList.toggle('is-selected', Boolean(checkbox && checkbox.checked));
        });
    };

    const updateTargetVisibility = function () {
        const needsTarget = actionNeedsTarget(actionField?.value);
        targetFields.forEach(function (field) {
            if (!field.hasAttribute('data-target-field')) return;
            if (field.id === 'promotionManualRollWrap') return;
            field.classList.toggle('d-none', !needsTarget);
        });

        [targetSession, targetClass, targetSection].forEach(function (field) {
            if (!field) return;
            field.required = needsTarget;
            field.disabled = !needsTarget;
        });

        if (manualRollWrap) {
            manualRollWrap.classList.toggle('d-none', !needsTarget || rollStrategy?.value !== 'manual');
        }
        if (manualRoll) {
            manualRoll.disabled = !needsTarget || rollStrategy?.value !== 'manual';
            manualRoll.required = !manualRoll.disabled;
        }
    };

    const filterSections = function () {
        if (!targetClass || !targetSection) return;
        const classId = targetClass.value;
        let firstVisible = null;
        Array.from(targetSection.options).forEach(function (option) {
            if (!option.dataset.classId) return;
            const visible = option.dataset.classId === classId;
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible && !firstVisible) firstVisible = option;
            if (!visible && option.selected) targetSection.value = '';
        });
        if (!targetSection.value && firstVisible) {
            targetSection.value = firstVisible.value;
        }
    };

    const showFeedback = function (message, tone) {
        if (!feedback) return;
        feedback.className = 'alert mx-3 mt-3 mb-0 alert-' + (tone || 'danger');
        feedback.textContent = message;
        feedback.classList.remove('d-none');
    };

    const clearFeedback = function () {
        if (!feedback) return;
        feedback.classList.add('d-none');
        feedback.textContent = '';
    };

    const validateBeforePreview = function () {
        if (selectedIds.length === 0) {
            showFeedback(@json(__('Select at least one student to preview.')), 'warning');
            return false;
        }

        if (actionNeedsTarget(actionField.value)) {
            if (!targetSession?.value || !targetClass?.value || !targetSection?.value) {
                showFeedback(@json(__('Choose a target session, class and section before previewing.')), 'warning');
                return false;
            }
        }

        return true;
    };

    const renderPreview = function (plan) {
        if (!previewRows || !previewSummary) return;

        previewSubtitle.textContent = plan.action_label + ' · ' + (plan.from_session_name || '') + (plan.to_session_name ? ' · ' + plan.to_session_name : '');
        previewSummary.innerHTML = [
            badge('Total', plan.summary.total, 'is-info'),
            badge(@json(__('Ready')), plan.summary.ready, 'is-success'),
            badge(@json(__('Conflicts')), plan.summary.conflicts, plan.summary.conflicts > 0 ? 'is-danger' : ''),
            badge(@json(__('Eligible')), plan.summary.eligible, 'is-success'),
            badge(@json(__('Needs review')), plan.summary.ineligible + plan.summary.unavailable, (plan.summary.ineligible + plan.summary.unavailable) > 0 ? 'is-warning' : '')
        ].join('');

        previewRows.innerHTML = plan.rows.map(function (row) {
            const target = actionNeedsTarget(plan.action)
                ? escapeHtml(row.to_class_name || '—') + ' · ' + escapeHtml(row.to_section_name || '—')
                : '<span class="text-muted-2">' + escapeHtml(@json(__('No new enrollment'))) + '</span>';
            const current = escapeHtml(row.from_class_name || '—') + ' · ' + escapeHtml(row.from_section_name || @json(__('No section'))) + ' · ' + escapeHtml(@json(__('Roll'))) + ' ' + escapeHtml(row.from_roll_no ?? '—');
            const result = row.result
                ? '<strong>' + (row.result.gpa !== null ? escapeHtml(Number(row.result.gpa).toFixed(2)) : '—') + ' GPA</strong><div class="text-muted-2">' + escapeHtml(row.result.exam_name || '') + '</div>'
                : '<span class="text-muted-2">' + escapeHtml(@json(__('No published result'))) + '</span>';
            const status = row.status === 'ready'
                ? '<span class="promotion-badge is-ready"><i class="bi bi-check2"></i>' + escapeHtml(@json(__('Ready'))) + '</span>'
                : '<span class="promotion-badge" style="background:var(--danger-soft);color:var(--danger);"><i class="bi bi-x-circle"></i>' + escapeHtml(@json(__('Conflict'))) + '</span>';
            const errorList = row.errors.length
                ? '<ul class="promotion-error-list">' + row.errors.map(function (error) { return '<li>' + escapeHtml(error) + '</li>'; }).join('') + '</ul>'
                : '';
            const warningList = row.warnings.length
                ? '<ul class="promotion-warning-list">' + row.warnings.map(function (warning) { return '<li>' + escapeHtml(warning) + '</li>'; }).join('') + '</ul>'
                : '';
            const eligibilityClass = row.eligibility.tone === 'success' ? 'is-success' : (row.eligibility.tone === 'danger' ? 'is-danger' : 'is-warning');

            return '<tr>' +
                '<td><strong>' + escapeHtml(row.student_name) + '</strong><div class="text-muted-2">' + escapeHtml(row.student_code || '') + '</div></td>' +
                '<td>' + current + '</td>' +
                '<td class="text-center text-muted-2"><i class="bi bi-arrow-right"></i></td>' +
                '<td>' + target + '</td>' +
                '<td>' + (row.to_roll_no ?? '—') + '</td>' +
                '<td>' + result + '<div class="mt-1"><span class="badge-soft ' + eligibilityClass + '">' + escapeHtml(row.eligibility.label) + '</span></div></td>' +
                '<td>' + status + errorList + warningList + '</td>' +
                '</tr>';
        }).join('');

        if (plan.conflict_count > 0) {
            previewAlert.className = 'alert alert-danger';
            previewAlert.innerHTML = '<strong>' + escapeHtml(@json(__('This batch cannot be processed yet.'))) + '</strong> ' + escapeHtml(@json(__('Resolve the conflicts shown below, then preview again. Nothing has been written.')));
            previewAlert.classList.remove('d-none');
        } else if (plan.summary.ineligible + plan.summary.unavailable > 0) {
            previewAlert.className = 'alert alert-warning';
            previewAlert.innerHTML = '<strong>' + escapeHtml(@json(__('Review advisory'))) + '</strong> ' + escapeHtml(@json(__('Some students are not marked eligible. You may still confirm after checking their results.')));
            previewAlert.classList.remove('d-none');
        } else {
            previewAlert.classList.add('d-none');
            previewAlert.innerHTML = '';
        }

        if (confirmButton) {
            confirmButton.disabled = plan.conflict_count > 0;
        }
    };

    const badge = function (label, value, tone) {
        return '<span class="badge-soft ' + (tone || '') + '">' + escapeHtml(label) + ': ' + escapeHtml(value) + '</span>';
    };

    const openPreview = function () {
        if (previewLoading) return;
        if (!validateBeforePreview()) return;

        clearFeedback();
        previewLoading = true;
        if (previewButton) {
            previewButton.disabled = true;
            previewButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="presentation"></span>' + escapeHtml(@json(__('Checking...')));
        }

        const payload = new FormData(form);
        payload.set('notes', notes ? notes.value : '');

        fetch(previewUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload
        })
            .then(function (response) {
                if (!response.ok) {
                    return response.json().then(function (data) {
                        const firstError = data.errors ? Object.values(data.errors).flat()[0] : data.message;
                        throw new Error(firstError || @json(__('The preview could not be prepared.')));
                    });
                }
                return response.json();
            })
            .then(function (plan) {
                renderPreview(plan);
                previewModal?.show();
            })
            .catch(function (error) {
                showFeedback(error.message || @json(__('The preview could not be prepared.')), 'danger');
            })
            .finally(function () {
                previewLoading = false;
                if (previewButton) {
                    previewButton.disabled = false;
                    previewButton.innerHTML = '<i class="bi bi-eye"></i>' + escapeHtml(@json(__('Preview')));
                }
            });
    };

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxList.forEach(function (checkbox) {
                if (!checkbox.disabled) checkbox.checked = selectAll.checked;
            });
            refreshSelection();
        });
    }

    checkboxList.forEach(function (checkbox) {
        checkbox.addEventListener('change', refreshSelection);
    });

    document.querySelectorAll('[data-row-select]').forEach(function (button) {
        button.addEventListener('click', function () {
            const row = button.closest('.promotion-row');
            if (!row) return;

            checkboxList.forEach(function (checkbox) {
                checkbox.checked = false;
            });

            const checkbox = row.querySelector('.promotion-checkbox');
            if (checkbox && !checkbox.disabled) checkbox.checked = true;

            actionField.value = 'promote';
            updateTargetVisibility();
            filterSections();

            const rowLevel = Number(row.dataset.fromClassLevel || 0);
            if (targetClass && !targetClass.value) {
                const choices = Array.from(targetClass.options).filter(function (option) { return option.value; });
                const higher = choices.filter(function (option) { return Number(option.dataset.level) > rowLevel; });
                const sameOrHigher = higher.length ? higher : choices.filter(function (option) { return Number(option.dataset.level) >= rowLevel; });
                if (sameOrHigher.length) {
                    targetClass.value = sameOrHigher[0].value;
                    filterSections();
                }
            }

            if (targetSection && row.dataset.fromSectionName) {
                const matching = Array.from(targetSection.options).find(function (option) {
                    return !option.disabled && option.dataset.name === row.dataset.fromSectionName;
                });
                if (matching) targetSection.value = matching.value;
            }

            refreshSelection();
            document.querySelector('.promotion-toolbar')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            window.setTimeout(openPreview, 120);
        });
    });

    actionField?.addEventListener('change', function () {
        updateTargetVisibility();
        if (actionNeedsTarget(actionField.value)) filterSections();
    });

    targetClass?.addEventListener('change', function () {
        if (targetSection) targetSection.value = '';
        filterSections();
    });

    rollStrategy?.addEventListener('change', updateTargetVisibility);

    previewButton?.addEventListener('click', openPreview);

    form.addEventListener('submit', function (event) {
        if (submitting) {
            event.preventDefault();
            return;
        }

        if (!validateBeforePreview()) {
            event.preventDefault();
            return;
        }

        if (notesHidden && notes) notesHidden.value = notes.value;
        submitting = true;
        if (confirmButton) confirmButton.disabled = true;
        if (progress) progress.classList.remove('d-none');
    });

    updateTargetVisibility();
    filterSections();
    refreshSelection();
})();
</script>
@endpush
