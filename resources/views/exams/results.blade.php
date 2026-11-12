@extends('layouts.app')

@section('title', __('Results') . ' · ' . $exam->name)

@section('content')
    @php
        $exportQuery = array_filter(['class_id' => $class?->id]);
        $readyToPublish = $summary['students'] > 0 && $summary['pending'] === 0;
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-table"></i>{{ __('Examination') }}</span>
            <h1 class="page-title">{{ __('Results') }}</h1>
            <p class="page-subtitle">
                {{ $exam->name }} · {{ $exam->academicSession?->name }}
                <span class="badge-soft {{ $exam->isPublished() ? 'is-success' : 'is-warning' }} ms-1">{{ __(ucfirst($exam->status)) }}</span>
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('exams.show', $exam) }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Exam') }}</a>
            @if ($class)
                <a href="{{ route('exams.results.export', ['exam' => $exam] + $exportQuery) }}" class="btn btn-light-soft">
                    <i class="bi bi-filetype-csv"></i>{{ __('Export CSV') }}
                </a>
            @endif
            @can('results.publish')
                @if ($exam->isPublished())
                    <form method="POST" action="{{ route('exams.results.unpublish', $exam) }}" data-confirm="{{ __('Unpublish these results? Students will no longer see them and marks become editable again.') }}">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-unlock"></i>{{ __('Unpublish') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('exams.results.publish', $exam) }}" data-confirm="{{ __('Publish results? This freezes marks and stores an immutable result snapshot for every student.') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary" @disabled(! $readyToPublish)>
                            <i class="bi bi-patch-check"></i>{{ __('Publish results') }}
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @if ($exam->isPublished())
        <div class="alert alert-success">
            <i class="bi bi-patch-check-fill"></i>
            <div>{{ __('Results were published on :date. Snapshots are frozen; unpublish to correct marks.', ['date' => $exam->published_at?->format('d M Y, h:i A') ?? '—']) }}</div>
        </div>
    @elseif (! $readyToPublish && $summary['pending'] > 0)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>{{ __('Results cannot be published until every enrolled student has a mark or an absence for every paper. :count student records are still incomplete.', ['count' => $summary['pending']]) }}</div>
        </div>
    @endif

    @if ($classes->isEmpty())
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-list-check"></i></div>
                <h3>{{ __('No papers to report') }}</h3>
                <p>{{ __('Add subject papers to this exam before reviewing results.') }}</p>
                <a href="{{ route('exams.show', $exam) }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i>{{ __('Add papers') }}</a>
            </div>
        </section>
    @else
        <section class="ui-card p-lg mb-3">
            <div class="card-head mb-3">
                <div>
                    <h2 class="card-title mb-1">{{ __('Tabulation filters') }}</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Positions are calculated within each class. Equal totals and GPA share a position.') }}</p>
                </div>
                @if ($class)
                    <span class="badge-soft is-brand">{{ $class->name }}</span>
                @endif
            </div>

            <form method="GET" action="{{ route('exams.results.index', $exam) }}" class="filter-form">
                <div>
                    <label class="form-label" for="results-class">{{ __('Class') }}</label>
                    <select id="results-class" name="class_id" class="form-select" data-auto-submit>
                        @foreach ($classes as $option)
                            <option value="{{ $option->id }}" @selected($class?->id === $option->id)>{{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="align-self-end d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Show') }}</button>
                    @if ($exam->isPublished())
                        <a href="{{ route('exams.results.index', $exam) }}" class="btn btn-sm btn-link">{{ __('Reset') }}</a>
                    @endif
                </div>
            </form>
        </section>

        @if ($class === null || $papers->isEmpty())
            <section class="ui-card">
                <div class="empty-state">
                    <div class="empty-icon"><i class="bi bi-journal-x"></i></div>
                    <h3>{{ __('No papers for this class') }}</h3>
                    <p>{{ __('Add subject papers for this class to calculate results.') }}</p>
                </div>
            </section>
        @else
            <section class="stats-grid mb-3" aria-label="{{ __('Result summary') }}">
                <article class="stat-card">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">{{ __('Students') }}</p>
                            <p class="stat-value">{{ number_format($summary['students']) }}</p>
                        </div>
                        <span class="stat-icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                    </div>
                    <div class="stat-foot"><span>{{ __('Active enrollments in :class', ['class' => $class->name]) }}</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">{{ __('Complete') }}</p>
                            <p class="stat-value">{{ number_format($summary['complete']) }}</p>
                        </div>
                        <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-check2-circle"></i></span>
                    </div>
                    <div class="stat-foot"><span>{{ __('Students with every paper recorded') }}</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">{{ __('Pending') }}</p>
                            <p class="stat-value">{{ number_format($summary['pending']) }}</p>
                        </div>
                        <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
                    </div>
                    <div class="stat-foot"><span>{{ __('Missing one or more papers') }}</span></div>
                </article>
                <article class="stat-card">
                    <div class="stat-top">
                        <div>
                            <p class="stat-label">{{ __('Average GPA') }}</p>
                            <p class="stat-value">{{ $summary['average_gpa'] !== null ? number_format((float) $summary['average_gpa'], 2) : '—' }}</p>
                        </div>
                        <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-graph-up-arrow"></i></span>
                    </div>
                    <div class="stat-foot">
                        <span>
                            {{ __('Passed :passed', ['passed' => number_format($summary['passed'])]) }}
                            @if ($summary['highest_gpa'] !== null)
                                · {{ __('Highest') }} {{ number_format((float) $summary['highest_gpa'], 2) }}
                            @endif
                        </span>
                    </div>
                </article>
            </section>

            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Tabulation sheet') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Absent papers score zero. A single F grade makes the overall GPA 0.00.') }}</p>
                    </div>
                    <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count paper|:count papers', $papers->count(), ['count' => $papers->count()]) }}</span>
                </div>

                @if ($rows->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-people"></i></div>
                        <h3>{{ __('No enrolled students') }}</h3>
                        <p>{{ __('This class has no active enrollments in the exam session.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Pos') }}</th>
                                    <th scope="col">{{ __('Roll') }}</th>
                                    <th scope="col">{{ __('Student') }}</th>
                                    @foreach ($papers as $paper)
                                        <th scope="col" class="text-end" title="{{ $paper->subject?->name }}">
                                            {{ $paper->subject?->code ?: $paper->subject?->name }}
                                        </th>
                                    @endforeach
                                    <th scope="col" class="text-end">{{ __('Total') }}</th>
                                    <th scope="col" class="text-end">{{ __('GPA') }}</th>
                                    <th scope="col">{{ __('Grade') }}</th>
                                    <th scope="col" class="text-end">{{ __('Report') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td class="fw-600">{{ $row['position'] ?? '—' }}</td>
                                        <td>{{ $row['enrollment']->roll_no ?? '—' }}</td>
                                        <td>
                                            <div class="fw-600">{{ $row['student']->full_name }}</div>
                                            <div class="text-muted-2" style="font-size:.76rem;">
                                                {{ $row['student']->student_id }}
                                                @if ($row['enrollment']->section)
                                                    · {{ __('Section') }} {{ $row['enrollment']->section->name }}
                                                @endif
                                            </div>
                                        </td>
                                        @foreach ($papers as $paper)
                                            @php $cell = $row['cells'][$paper->id] ?? null; @endphp
                                            <td class="text-end">
                                                @if ($cell === null || ! $cell['recorded'])
                                                    <span class="text-muted-2">—</span>
                                                @elseif ($cell['absent'])
                                                    <span class="badge-soft is-warning" title="{{ __('Absent') }}">A</span>
                                                @else
                                                    {{ number_format((float) $cell['obtained'], 2) }}
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="text-end">
                                            <div class="fw-600">{{ number_format((float) $row['total_obtained'], 2) }}</div>
                                            <div class="text-muted-2" style="font-size:.72rem;">{{ __('of') }} {{ number_format((float) $row['total_full'], 2) }}</div>
                                        </td>
                                        <td class="text-end fw-600">{{ $row['gpa'] !== null ? number_format((float) $row['gpa'], 2) : '—' }}</td>
                                        <td>
                                            @if ($row['grade_letter'])
                                                <span class="badge-soft {{ $row['grade_letter'] === 'F' ? 'is-danger' : 'is-success' }}">{{ $row['grade_letter'] }}</span>
                                            @else
                                                <span class="text-muted-2">—</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('exams.results.show', [$exam, $row['student']]) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Report card') }}" aria-label="{{ __('Report card for :name', ['name' => $row['student']->full_name]) }}">
                                                <i class="bi bi-file-earmark-text"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        @endif
    @endif
@endsection
