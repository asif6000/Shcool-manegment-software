@extends('layouts.student')

@section('title', __('Report card') . ' · ' . $exam->name)

@section('content')
    <div class="page-header no-print">
        <div>
            <span class="page-eyebrow"><i class="bi bi-file-earmark-text"></i>{{ __('Report card') }}</span>
            <h1 class="page-title">{{ $exam->name }}</h1>
            <p class="page-subtitle">{{ $exam->academicSession?->name }} · {{ __('Published :date', ['date' => $result->published_at?->format('d M Y') ?? '—']) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('student.results.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Results') }}</a>
            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i>{{ __('Print') }}</button>
        </div>
    </div>

    @include('exams.partials.report-card', [
        'published' => true,
        'complete' => (bool) ($snapshot['complete'] ?? true),
        'snapshotPublishedAt' => $result->published_at?->format('d M Y, h:i A'),
    ])
@endsection
