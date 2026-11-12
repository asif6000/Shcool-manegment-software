@extends('layouts.student')

@section('title', __('Results'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-journal-check"></i>{{ __('Results') }}</span>
            <h1 class="page-title">{{ __('My results') }}</h1>
            <p class="page-subtitle">{{ __('Published exam results and report cards for :name.', ['name' => $student->full_name]) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('student.dashboard') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Overview') }}</a>
        </div>
    </div>

    @if ($results->isEmpty())
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-journal-check"></i></div>
                <h3>{{ __('No published results yet') }}</h3>
                <p>{{ __('Your report cards will appear here after the school publishes each exam result.') }}</p>
            </div>
        </section>
    @else
        <section class="ui-card table-card">
            <div class="table-toolbar">
                <div>
                    <h2 class="card-title mb-1">{{ __('Published results') }}</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Open a result to view or print the full report card.') }}</p>
                </div>
                <span class="badge-soft is-success">{{ trans_choice(':count result|:count results', $results->count(), ['count' => $results->count()]) }}</span>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Exam') }}</th>
                            <th scope="col">{{ __('Session') }}</th>
                            <th scope="col">{{ __('Published') }}</th>
                            <th scope="col" class="text-end">{{ __('GPA') }}</th>
                            <th scope="col">{{ __('Grade') }}</th>
                            <th scope="col" class="text-end">{{ __('Position') }}</th>
                            <th scope="col" class="text-end">{{ __('Report') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results as $result)
                            <tr>
                                <td>
                                    <a href="{{ route('student.results.show', $result->exam_id) }}" class="fw-600 text-decoration-none">
                                        {{ $result->exam?->name ?? '—' }}
                                    </a>
                                    <div class="text-muted-2" style="font-size:.76rem;">
                                        {{ __(ucfirst(str_replace('_', ' ', $result->exam?->exam_type ?? 'term'))) }}
                                    </div>
                                </td>
                                <td>{{ $result->exam?->academicSession?->name ?? '—' }}</td>
                                <td>{{ $result->published_at?->format('d M Y') ?? '—' }}</td>
                                <td class="text-end fw-600">{{ $result->gpa !== null ? number_format((float) $result->gpa, 2) : '—' }}</td>
                                <td>
                                    <span class="badge-soft {{ $result->grade_letter === 'F' ? 'is-danger' : 'is-success' }}">
                                        {{ $result->grade_letter ?? '—' }}
                                    </span>
                                </td>
                                <td class="text-end">{{ $result->position ?? '—' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('student.results.show', $result->exam_id) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('View report card') }}" aria-label="{{ __('View report card for :exam', ['exam' => $result->exam?->name]) }}">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
