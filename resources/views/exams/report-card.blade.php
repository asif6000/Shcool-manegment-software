@extends('layouts.app')

@section('title', __('Report card') . ' · ' . $student->full_name)

@section('content')
    <div class="page-header no-print">
        <div>
            <span class="page-eyebrow"><i class="bi bi-file-earmark-text"></i>{{ __('Academic report card') }}</span>
            <h1 class="page-title">{{ $student->full_name }}</h1>
            <p class="page-subtitle">
                {{ $exam->name }} · {{ $exam->academicSession?->name }}
                <span class="badge-soft {{ $published ? 'is-success' : 'is-warning' }} ms-1">{{ $published ? __('Published') : __('Draft') }}</span>
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('exams.results.index', $exam) }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Results') }}</a>
            <a href="{{ route('exams.show', $exam) }}" class="btn btn-light-soft"><i class="bi bi-journal-check"></i>{{ __('Exam') }}</a>
            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i>{{ __('Print') }}</button>
        </div>
    </div>

    @if (! $published)
        <div class="alert alert-warning no-print">
            <i class="bi bi-eye-fill"></i>
            <div>{{ __('This is a draft preview. Publish the results to make the report card final and visible to the student.') }}</div>
        </div>
    @endif

    @include('exams.partials.report-card', [
        'snapshotPublishedAt' => $result?->published_at?->format('d M Y, h:i A'),
    ])
@endsection
