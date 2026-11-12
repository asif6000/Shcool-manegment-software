@extends('layouts.app')

@section('title', __('Notices'))

@section('content')
    @php
        $statuses = [
            'draft' => __('Draft'),
            'scheduled' => __('Scheduled'),
            'published' => __('Live'),
            'expired' => __('Expired'),
        ];

        $stateOf = function ($notice) use ($statuses): array {
            if ($notice->published_at === null) {
                return ['key' => 'draft', 'label' => $statuses['draft'], 'class' => ''];
            }

            if ($notice->published_at->isFuture()) {
                return ['key' => 'scheduled', 'label' => $statuses['scheduled'], 'class' => 'is-warning'];
            }

            if ($notice->expires_at !== null && $notice->expires_at->isPast()) {
                return ['key' => 'expired', 'label' => $statuses['expired'], 'class' => 'is-danger'];
            }

            return ['key' => 'published', 'label' => $statuses['published'], 'class' => 'is-success'];
        };
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-megaphone"></i>{{ __('School communication') }}</span>
            <h1 class="page-title">{{ __('Notices') }}</h1>
            <p class="page-subtitle">{{ __('Published notices appear on the public website immediately and in the audience inbox.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('public.notices.index') }}" target="_blank" rel="noopener" class="btn btn-light-soft">
                <i class="bi bi-window"></i>{{ __('Website notice board') }}
            </a>
            @can('notices.manage')
                <a href="{{ route('notices.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i>{{ __('Write a notice') }}
                </a>
            @endcan
        </div>
    </div>

    <div class="mini-stats mb-3">
        <div class="mini-stat">
            <span class="value">{{ $websiteCount }}</span>
            <span class="label">{{ __('Live on the website') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ $notices->total() }}</span>
            <span class="label">{{ __('Notices for this filter') }}</span>
        </div>
        <div class="mini-stat text-start">
            <span class="label">{{ __('Everyone, Students and Guardians are published to the website. Teachers and Staff stay in the signed-in inbox.') }}</span>
        </div>
    </div>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('notices.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="notice-search">{{ __('Search notices') }}</label>
                    <input id="notice-search" type="search" name="q" class="form-control"
                           value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Search title or body') }}">
                </div>
                <label class="visually-hidden" for="filter-audience">{{ __('Audience') }}</label>
                <select id="filter-audience" name="audience" class="form-select" data-auto-submit>
                    <option value="">{{ __('All audiences') }}</option>
                    @foreach (\App\Models\Notice::AUDIENCES as $audience)
                        <option value="{{ $audience }}" @selected(($filters['audience'] ?? '') === $audience)>
                            {{ \Illuminate\Support\Str::headline($audience) }}
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-state">{{ __('Status') }}</label>
                <select id="filter-state" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('notices.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">
                {{ trans_choice(':count notice|:count notices', $notices->total(), ['count' => $notices->total()]) }}
            </span>
        </div>

        @if ($notices->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-megaphone"></i></div>
                <h3>{{ __('No notices yet') }}</h3>
                <p>{{ __('Write a notice, then publish it to put it on the website and into the audience inbox.') }}</p>
                @can('notices.manage')
                    <a href="{{ route('notices.create') }}" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-lg"></i>{{ __('Write a notice') }}
                    </a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Notice') }}</th>
                            <th>{{ __('Audience') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Timing') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($notices as $notice)
                            @php($state = $stateOf($notice))
                            <tr>
                                <td>
                                    <div class="d-flex align-items-start gap-2">
                                        @if ($notice->is_pinned)
                                            <i class="bi bi-pin-angle-fill text-primary" title="{{ __('Pinned') }}"></i>
                                        @endif
                                        <div>
                                            <div class="fw-semibold">{{ $notice->title }}</div>
                                            <div class="text-muted-2" style="font-size:.75rem;">
                                                {{ \Illuminate\Support\Str::limit(strip_tags($notice->body), 90) }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-soft">{{ $notice->audienceLabel() }}</span>
                                    @if ($notice->isLiveOnWebsite())
                                        <div class="text-muted-2" style="font-size:.72rem;">{{ __('Visible on website') }}</div>
                                    @endif
                                </td>
                                <td><span class="badge-soft {{ $state['class'] }}">{{ $state['label'] }}</span></td>
                                <td style="font-size:.78rem;">
                                    @if ($notice->published_at !== null)
                                        <div>{{ $notice->published_at->format('d M Y, g:i a') }}</div>
                                    @else
                                        <div class="text-muted-2">{{ __('Not published') }}</div>
                                    @endif
                                    @if ($notice->expires_at !== null)
                                        <div class="text-muted-2">
                                            {{ __('Until') }} {{ $notice->expires_at->format('d M Y, g:i a') }}
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @can('notices.manage')
                                        <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                            @if ($state['key'] === 'draft' || $state['key'] === 'scheduled' || $state['key'] === 'expired')
                                                <form method="POST" action="{{ route('notices.publish', $notice) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-broadcast"></i>{{ __('Publish now') }}
                                                    </button>
                                                </form>
                                            @elseif ($state['key'] === 'published')
                                                <form method="POST" action="{{ route('notices.unpublish', $notice) }}"
                                                      data-confirm="{{ __('Unpublish :title? It leaves the website.', ['title' => $notice->title]) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-light-soft">
                                                        <i class="bi bi-eye-slash"></i>{{ __('Unpublish') }}
                                                    </button>
                                                </form>
                                            @endif

                                            <form method="POST" action="{{ route('notices.pin', $notice) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="icon-action"
                                                        title="{{ $notice->is_pinned ? __('Unpin') : __('Pin to top') }}"
                                                        aria-label="{{ $notice->is_pinned ? __('Unpin notice') : __('Pin notice') }}">
                                                    <i class="bi {{ $notice->is_pinned ? 'bi-pin-angle' : 'bi-pin' }}"></i>
                                                </button>
                                            </form>

                                            <a href="{{ route('notices.edit', $notice) }}" class="icon-action"
                                               title="{{ __('Edit') }}" aria-label="{{ __('Edit notice') }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>

                                            <form method="POST" action="{{ route('notices.destroy', $notice) }}"
                                                  data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                  data-confirm-delete="1"
                                                  data-confirm-label="{{ __('Permanently Delete') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="icon-action is-danger"
                                                        title="{{ __('Delete') }}" aria-label="{{ __('Delete notice') }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <a href="{{ route('notices.edit', $notice) }}" class="btn btn-sm btn-light-soft">
                                            {{ __('View') }}
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                {{ $notices->links() }}
            </div>
        @endif
    </section>
@endsection
