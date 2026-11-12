@extends('layouts.app')

@section('title', __('Exams & results'))

@section('content')
    @php
        $statusClasses = [
            'draft' => 'is-warning',
            'published' => 'is-success',
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-journal-check"></i>{{ __('Examinations') }}</span>
            <h1 class="page-title">{{ __('Exams & results') }}</h1>
            <p class="page-subtitle">{{ __('Schedule exams, enter marks and publish school-scoped results.') }}</p>
        </div>
        <div class="page-actions">
            @can('exams.manage')
                <a href="{{ route('exams.grade-scale.edit') }}" class="btn btn-light-soft">
                    <i class="bi bi-sliders"></i>{{ __('Grading scale') }}
                </a>
                <a href="{{ route('exams.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i>{{ __('New exam') }}
                </a>
            @endcan
        </div>
    </div>

    <section class="stats-grid mb-3" aria-label="{{ __('Exam summary') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Exams') }}</p>
                    <p class="stat-value">{{ number_format($summary['total']) }}</p>
                </div>
                <span class="stat-icon" aria-hidden="true"><i class="bi bi-journal-text"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Across all sessions') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Published') }}</p>
                    <p class="stat-value">{{ number_format($summary['published']) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-patch-check"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Results visible to students') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('In progress') }}</p>
                    <p class="stat-value">{{ number_format($summary['draft']) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-pencil-square"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Draft exams accepting marks') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Subject papers') }}</p>
                    <p class="stat-value">{{ number_format($summary['papers']) }}</p>
                </div>
                <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-list-check"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Scheduled across exams') }}</span></div>
        </article>
    </section>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('exams.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="exam-search">{{ __('Search exams') }}</label>
                    <input id="exam-search" type="search" name="search" class="form-control" value="{{ $filters['search'] }}" placeholder="{{ __('Search by exam name') }}">
                </div>
                <label class="visually-hidden" for="filter-session">{{ __('Session') }}</label>
                <select id="filter-session" name="session_id" class="form-select" data-auto-submit>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected($filters['session_id'] === (string) $session->id)>{{ $session->name }}</option>
                    @endforeach
                    <option value="" @selected($filters['session_id'] === '')>{{ __('All sessions') }}</option>
                </select>
                <label class="visually-hidden" for="filter-status">{{ __('Status') }}</label>
                <select id="filter-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (\App\Models\Exam::STATUSES as $status)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __(ucfirst($status)) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('exams.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count exam|:count exams', $exams->total(), ['count' => $exams->total()]) }}</span>
        </div>

        @if ($exams->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-journal-check"></i></div>
                <h3>{{ __('No exams found') }}</h3>
                <p>{{ array_filter($filters) ? __('No exams match these filters.') : __('Create an exam, add subject papers and publish results when marks are complete.') }}</p>
                @can('exams.manage')
                    <a href="{{ route('exams.create') }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i>{{ __('New exam') }}</a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Exam') }}</th>
                            <th scope="col">{{ __('Session') }}</th>
                            <th scope="col">{{ __('Type') }}</th>
                            <th scope="col">{{ __('Dates') }}</th>
                            <th scope="col" class="text-end">{{ __('Papers') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($exams as $exam)
                            <tr>
                                <td>
                                    <a href="{{ route('exams.show', $exam) }}" class="fw-600 text-decoration-none">{{ $exam->name }}</a>
                                    @if ($exam->published_at)
                                        <div class="text-muted-2" style="font-size:.76rem;">{{ __('Published :date', ['date' => $exam->published_at->format('d M Y')]) }}</div>
                                    @endif
                                </td>
                                <td>{{ $exam->academicSession?->name ?? '—' }}</td>
                                <td>{{ __(ucfirst(str_replace('_', ' ', $exam->exam_type))) }}</td>
                                <td>
                                    <div>{{ $exam->start_date?->format('d M Y') ?? '—' }}</div>
                                    <div class="text-muted-2" style="font-size:.76rem;">{{ __('to') }} {{ $exam->end_date?->format('d M Y') ?? '—' }}</div>
                                </td>
                                <td class="text-end">{{ number_format($exam->papers_count) }}</td>
                                <td>
                                    <span class="badge-soft {{ $statusClasses[$exam->status] ?? '' }}">{{ __(ucfirst($exam->status)) }}</span>
                                </td>
                                <td class="text-end">
                                    <div class="row-actions">
                                        <a href="{{ route('exams.show', $exam) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('View exam') }}" aria-label="{{ __('View :name', ['name' => $exam->name]) }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @can('marks.enter')
                                            <a href="{{ route('exams.marks.index', $exam) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Enter marks') }}" aria-label="{{ __('Enter marks for :name', ['name' => $exam->name]) }}">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                        @endcan
                                        @can('results.view')
                                            <a href="{{ route('exams.results.index', $exam) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Results') }}" aria-label="{{ __('Results for :name', ['name' => $exam->name]) }}">
                                                <i class="bi bi-table"></i>
                                            </a>
                                        @endcan
                                        @can('exams.manage')
                                            @if (! $exam->isPublished())
                                                <a href="{{ route('exams.edit', $exam) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Edit exam') }}" aria-label="{{ __('Edit :name', ['name' => $exam->name]) }}">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $exams->firstItem(), 'to' => $exams->lastItem(), 'total' => $exams->total()]) }}</span>
                {{ $exams->links() }}
            </div>
        @endif
    </section>
@endsection
