@extends('layouts.app')

@section('title', __('Class videos'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-camera-reels"></i>{{ __('Academic') }}</span>
            <h1 class="page-title">{{ __('Class videos') }}</h1>
            <p class="page-subtitle">{{ __('A lesson clip for one class — a YouTube link or a file you upload. Switch a video on for the website and it appears on the school home page.') }}</p>
        </div>
        <div class="page-actions">
            @if (auth()->user()->hasRole('teacher') && ! auth()->user()->hasAnyRole(['school_admin', 'principal', 'super_admin']) && auth()->user()->can('materials.view'))
                <a href="{{ route('materials.index') }}" class="btn btn-light-soft">
                    <i class="bi bi-journal-bookmark"></i>{{ __('Study materials') }}
                </a>
            @endif
            @can('videos.manage')
                <a href="{{ route('videos.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i>{{ __('Add video') }}
                </a>
            @endcan
        </div>
    </div>

    <div class="mini-stats mb-3">
        <div class="mini-stat">
            <span class="value">{{ $videos->total() }}</span>
            <span class="label">{{ __('Videos for this filter') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ $onWebsite }}</span>
            <span class="label">{{ __('On the website now') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ $youtubeCount }}</span>
            <span class="label">{{ __('From YouTube links') }}</span>
        </div>
        <div class="mini-stat text-start">
            <span class="label">{{ __('A teacher sees the videos of the classes on their own timetable; the office sees every class.') }}</span>
        </div>
    </div>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('videos.index') }}" class="filter-form" role="search">
                <label class="visually-hidden" for="filter-class">{{ __('Class') }}</label>
                <select id="filter-class" name="class" class="form-select" data-auto-submit>
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected((int) ($filters['class'] ?? 0) === $class->id)>
                            {{ $class->name }}
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-section">{{ __('Section') }}</label>
                <select id="filter-section" name="section" class="form-select" data-auto-submit>
                    <option value="">{{ __('All sections') }}</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}" @selected((int) ($filters['section'] ?? 0) === $section->id)>
                            {{ $section->name }}
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-source">{{ __('Source') }}</label>
                <select id="filter-source" name="source" class="form-select" data-auto-submit>
                    <option value="">{{ __('YouTube & uploads') }}</option>
                    <option value="youtube" @selected(($filters['source'] ?? '') === 'youtube')>{{ __('YouTube link') }}</option>
                    <option value="upload" @selected(($filters['source'] ?? '') === 'upload')>{{ __('Uploaded file') }}</option>
                </select>
                <label class="visually-hidden" for="filter-website">{{ __('Website') }}</label>
                <select id="filter-website" name="website" class="form-select" data-auto-submit>
                    <option value="">{{ __('Shown & hidden') }}</option>
                    <option value="1" @selected((string) ($filters['website'] ?? '') === '1')>{{ __('On the website') }}</option>
                    <option value="0" @selected((string) ($filters['website'] ?? '') === '0')>{{ __('Not on the website') }}</option>
                </select>
                <label class="visually-hidden" for="filter-q">{{ __('Search') }}</label>
                <input id="filter-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control"
                       placeholder="{{ __('Search title or note…') }}">
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-search"></i>{{ __('Search') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('videos.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">
                {{ trans_choice(':count video|:count videos', $videos->total(), ['count' => $videos->total()]) }}
            </span>
        </div>

        @if ($videos->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-camera-reels"></i></div>
                <h3>{{ __('No class video yet') }}</h3>
                <p>{{ __('Paste a YouTube link or upload a clip for a class, then switch it on for the website.') }}</p>
                @can('videos.manage')
                    <a href="{{ route('videos.create') }}" class="btn btn-primary mt-3">
                        <i class="bi bi-plus-lg"></i>{{ __('Add video') }}
                    </a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Video') }}</th>
                            <th>{{ __('Class') }}</th>
                            <th>{{ __('Subject') }}</th>
                            <th>{{ __('Website') }}</th>
                            <th>{{ __('Added') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($videos as $entry)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $entry->title }}</div>
                                    <span class="badge-soft {{ $entry->isYoutube() ? 'is-danger' : 'is-info' }} mt-1">
                                        <i class="bi {{ $entry->isYoutube() ? 'bi-youtube' : 'bi-file-earmark-play' }}"></i>
                                        {{ $entry->isYoutube() ? __('YouTube') : __('Upload') }}
                                    </span>
                                    <div class="text-muted-2" style="font-size:.74rem;">
                                        @if ($entry->isYoutube())
                                            <i class="bi bi-link-45deg"></i> {{ $entry->youtube_url ?: '—' }}
                                        @else
                                            <i class="bi bi-file-earmark"></i> {{ $entry->fileLabel() ?: '—' }}
                                            @if ($entry->file_size)
                                                · {{ number_format($entry->file_size / 1024, 0) }} KB
                                            @endif
                                        @endif
                                    </div>
                                    @if ($entry->description)
                                        <div class="text-muted-2" style="font-size:.74rem;">{{ Str::limit($entry->description, 90) }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $entry->schoolClass?->name ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.74rem;">
                                        {{ $entry->section?->name ?? __('Whole class') }}
                                        <span class="d-block">{{ $entry->academicSession?->name ?? '—' }}</span>
                                    </div>
                                </td>
                                <td style="font-size:.82rem;">{{ $entry->subject?->name ?? __('All subjects') }}</td>
                                <td>
                                    <span class="badge-soft {{ $entry->show_on_website ? 'is-success' : 'is-muted' }}">
                                        {{ $entry->show_on_website ? __('On home page') : __('Hidden') }}
                                    </span>
                                </td>
                                <td style="font-size:.8rem;">
                                    {{ $entry->created_at?->format('d M Y') ?? '—' }}
                                    <div class="text-muted-2" style="font-size:.72rem;">
                                        {{ $entry->creator?->name ?? '—' }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                        @if ($entry->openUrl())
                                            <a href="{{ $entry->openUrl() }}" target="_blank" rel="noopener noreferrer"
                                               class="icon-action" title="{{ __('Play') }}" aria-label="{{ __('Play :title', ['title' => $entry->title]) }}">
                                                <i class="bi bi-play-circle"></i>
                                            </a>
                                        @endif
                                        @can('videos.manage')
                                            <form method="POST" action="{{ route('videos.website', $entry) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="icon-action"
                                                        title="{{ $entry->show_on_website ? __('Take off the website') : __('Put on the website') }}"
                                                        aria-label="{{ $entry->show_on_website ? __('Hide :title' , ['title' => $entry->title]) : __('Show :title', ['title' => $entry->title]) }}">
                                                    <i class="bi {{ $entry->show_on_website ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                                </button>
                                            </form>
                                            <a href="{{ route('videos.edit', $entry) }}" class="icon-action"
                                               title="{{ __('Edit') }}" aria-label="{{ __('Edit video') }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="{{ route('videos.destroy', $entry) }}"
                                                  data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                  data-confirm-delete="1"
                                                  data-confirm-label="{{ __('Permanently Delete') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="icon-action is-danger"
                                                        title="{{ __('Permanently Delete') }}" aria-label="{{ __('Withdraw video') }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">{{ $videos->links() }}</div>
        @endif
    </section>
@endsection
