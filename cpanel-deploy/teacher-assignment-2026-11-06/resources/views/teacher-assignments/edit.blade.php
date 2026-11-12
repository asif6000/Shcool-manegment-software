@extends('layouts.app')

@section('title', __('Edit Teacher Assignment'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-user-round-check"></i>{{ __('Teacher Management') }}</span>
            <h1 class="page-title">{{ __('Edit Teacher Assignment') }}</h1>
            <p class="page-subtitle">{{ __('Change the class, section, subject, date or status. Removing an assignment uses the list screen.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('teacher_assignments.index', ['session' => $session?->id]) }}" class="btn btn-light-soft">
                <i class="bi bi-arrow-left"></i>{{ __('Back to assignments') }}
            </a>
        </div>
    </div>

    <section class="ui-card p-lg">
        <p class="text-muted-2" style="font-size:.78rem;">
            {{ __('Session') }}: <strong>{{ $session?->name ?? '—' }}</strong>
            @if ($assignment->creator)
                · {{ __('Added by') }} {{ $assignment->creator->name }}
            @endif
        </p>

        @include('teacher-assignments.partials.form', [
            'action' => route('teacher_assignments.update', $assignment),
            'method' => 'PUT',
            'submitLabel' => __('Save changes'),
        ])
    </section>
@endsection
