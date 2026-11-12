@extends('layouts.student')

@section('title', __('Notices'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-megaphone"></i>{{ __('Notices') }}</span>
            <h1 class="page-title">{{ __('School notices') }}</h1>
            <p class="page-subtitle">{{ __('Announcements the school has published for students.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('notifications.index') }}" class="btn btn-light-soft">
                <i class="bi bi-bell"></i>{{ __('My notifications') }}
            </a>
        </div>
    </div>

    <div class="ui-card table-card">
        <div class="table-toolbar">
            <div>
                <h2 class="card-title mb-1">{{ trans_choice(':count notice|:count notices', $notices->total(), ['count' => $notices->total()]) }}</h2>
                <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Pinned notices stay at the top.') }}</p>
            </div>
            <form method="GET" action="{{ route('student.notices.index') }}" class="filter-form" role="search">
                <label class="visually-hidden" for="notice-search">{{ __('Search notices') }}</label>
                <input id="notice-search" type="search" name="q" value="{{ $term }}" placeholder="{{ __('Search notices…') }}">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Search') }}</button>
                @if ($term !== '')
                    <a href="{{ route('student.notices.index') }}" class="btn btn-sm btn-light-soft">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>

        @if ($notices->isEmpty())
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-megaphone"></i></div>
                <h3>{{ $term !== '' ? __('No notices found') : __('No notices yet') }}</h3>
                <p>{{ $term !== '' ? __('Try a different word.') : __('School announcements for students will appear here.') }}</p>
            </div>
        @else
            <div class="notice-list p-3">
                @foreach ($notices as $notice)
                    <a href="{{ route('student.notices.show', $notice->id) }}"
                       class="notice-item text-decoration-none {{ $notice->is_pinned ? 'is-pinned' : '' }}">
                        <span class="notice-icon"><i class="bi {{ $notice->is_pinned ? 'bi-pin-angle' : 'bi-megaphone' }}"></i></span>
                        <div>
                            <div class="notice-title">{{ $notice->title }}</div>
                            <div class="notice-meta">
                                {{ $notice->published_at?->format('d M Y') }} · {{ $notice->audienceLabel() }}
                                @if ($notice->expires_at)
                                    · {{ __('Until') }} {{ $notice->expires_at->format('d M Y') }}
                                @endif
                            </div>
                            <p class="notice-body">{{ \Illuminate\Support\Str::limit($notice->body, 180) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>

            @if ($notices->hasPages())
                <div class="p-3 border-top">{{ $notices->links() }}</div>
            @endif
        @endif
    </div>
@endsection
