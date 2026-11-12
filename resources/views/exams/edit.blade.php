@extends('layouts.app')

@section('title', __('Edit :name', ['name' => $exam->name]))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-journal-check"></i>{{ __('Examinations') }}</span>
            <h1 class="page-title">{{ __('Edit exam') }}</h1>
            <p class="page-subtitle">{{ $exam->name }} · {{ $exam->academicSession?->name }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('exams.show', $exam) }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Exam') }}</a>
        </div>
    </div>

    @if ($exam->isPublished())
        <div class="alert alert-warning">
            <i class="bi bi-lock-fill"></i>
            <div>{{ __('Published exams are locked. Unpublish the results before changing the exam details.') }}</div>
        </div>
    @endif

    @include('exams.partials.form', ['exam' => $exam, 'currentSession' => null])
@endsection
