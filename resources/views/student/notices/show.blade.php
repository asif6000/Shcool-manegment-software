@extends('layouts.student')

@section('title', $notice->title)

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-megaphone"></i>{{ __('Notice') }}</span>
            <h1 class="page-title">{{ $notice->title }}</h1>
            <p class="page-subtitle">
                {{ $notice->published_at?->translatedFormat('j F Y, h:i A') }} · {{ $notice->audienceLabel() }}
                @if ($notice->expires_at)
                    · {{ __('Until') }} {{ $notice->expires_at->format('d M Y') }}
                @endif
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('student.notices.index') }}" class="btn btn-light-soft">
                <i class="bi bi-arrow-left"></i>{{ __('All notices') }}
            </a>
        </div>
    </div>

    <section class="ui-card p-lg">
        <div class="d-flex align-items-start gap-3">
            <span class="notice-icon"><i class="bi {{ $notice->is_pinned ? 'bi-pin-angle' : 'bi-megaphone' }}"></i></span>
            <div style="min-width:0;">
                <p class="notice-body" style="font-size:.9rem;">{{ $notice->body }}</p>
                @if ($notice->academicSession)
                    <p class="text-muted-2 mt-3 mb-0" style="font-size:.76rem;">
                        {{ __('Session') }}: {{ $notice->academicSession->name }}
                    </p>
                @endif
            </div>
        </div>
    </section>
@endsection
