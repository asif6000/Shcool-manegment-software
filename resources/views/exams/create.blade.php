@extends('layouts.app')

@section('title', __('New exam'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-journal-plus"></i>{{ __('Examinations') }}</span>
            <h1 class="page-title">{{ __('New exam') }}</h1>
            <p class="page-subtitle">{{ __('Define the exam window first; papers and marks come next.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('exams.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Exams') }}</a>
        </div>
    </div>

    @if ($sessions->isEmpty())
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-calendar3"></i></div>
                <h3>{{ __('Create an academic session first') }}</h3>
                <p>{{ __('Every exam belongs to an academic session. Set up the session and classes before scheduling exams.') }}</p>
                <a href="{{ route('academic.index') }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i>{{ __('Open academic setup') }}</a>
            </div>
        </section>
    @else
        @include('exams.partials.form', ['exam' => null, 'currentSession' => $currentSession])
    @endif
@endsection
