@php
    // The same body the class reads, so a teacher's preview cannot drift away
    // from the portal page. Attachments come through a route the caller passes:
    // the portal and the staff desk each stream their own checked download.
    $objectives = collect(preg_split('/\R/', (string) $lesson->objectives) ?: [])
        ->map(fn (string $line): string => trim($line))
        ->filter()
        ->values();
@endphp

<article class="lsn-read">
    <header class="lsn-read-head">
        <span class="page-eyebrow"><i class="bi bi-easel2"></i>{{ __('Lesson') }}</span>
        <h1 class="lsn-read-title">{{ $lesson->title }}</h1>
        <p class="lsn-read-meta">
            <span><i class="bi bi-diagram-3"></i>{{ $lesson->schoolClass?->name ?? __('Class') }}</span>
            <span><i class="bi bi-collection"></i>{{ $lesson->section?->name ?? __('Whole class') }}</span>
            <span><i class="bi bi-book"></i>{{ $lesson->subject?->name ?? __('Subject') }}</span>
            @if ($lesson->chapterLabel())
                <span><i class="bi bi-journal-section"></i>{{ $lesson->chapterLabel() }}</span>
            @endif
            @if ($lesson->published_at)
                <span><i class="bi bi-calendar-event"></i>{{ $lesson->published_at->translatedFormat('d M Y') }}</span>
            @endif
        </p>
    </header>

    @if ($lesson->description)
        <p class="lsn-read-lede">{{ $lesson->description }}</p>
    @endif

    @if ($objectives->isNotEmpty())
        <section class="lsn-objectives">
            <h2><i class="bi bi-bullseye"></i>{{ __('What you will learn') }}</h2>
            <ul>
                @foreach ($objectives as $objective)
                    <li>{{ $objective }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($lesson->content)
        <div class="lesson-body">{!! $lesson->content !!}</div>
    @else
        <p class="text-muted-2">{{ __('The teacher has not written the body of this lesson yet.') }}</p>
    @endif

    @if ($lesson->playerUrl())
        <section class="mt-4">
            <h2 class="h6 text-navy"><i class="bi bi-camera-reels"></i>{{ __('Lesson video') }}</h2>
            <div class="lsn-video">
                <iframe src="{{ $lesson->playerUrl() }}" title="{{ $lesson->title }}" loading="lazy"
                        allow="accelerometer; clipboard-write; encrypted-media; picture-in-picture"
                        allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
            </div>
        </section>
    @elseif ($lesson->video_url)
        <p class="mt-3 mb-0">
            <a href="{{ $lesson->video_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light-soft">
                <i class="bi bi-box-arrow-up-right"></i>{{ __('Open the lesson video') }}
            </a>
        </p>
    @endif

    @if ($lesson->materials->isNotEmpty())
        <section class="mt-4">
            <h2 class="h6 text-navy"><i class="bi bi-paperclip"></i>{{ __('Files for this lesson') }}</h2>
            <ul class="lsn-material-list">
                @foreach ($lesson->materials as $material)
                    <li class="lsn-material-row">
                        <i class="bi {{ $material->isImage() ? 'bi-file-earmark-image' : 'bi-file-earmark-text' }}"></i>
                        <span class="flex-grow-1 text-truncate">{{ $material->title }}</span>
                        <span class="text-muted-2" style="font-size:.74rem;">{{ $material->sizeLabel() }}</span>
                        <a href="{{ $fileUrl($material) }}" class="btn btn-sm btn-light-soft" @if ($material->isImage()) target="_blank" rel="noopener" @endif>
                            <i class="bi bi-download"></i>{{ $material->isImage() ? __('Open') : __('Download') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (collect($links ?? [])->isNotEmpty())
        <section class="mt-4">
            <h2 class="h6 text-navy"><i class="bi bi-link-45deg"></i>{{ __('Reference links') }}</h2>
            <ul class="lsn-links">
                @foreach ($links as $link)
                    <li>
                        <a href="{{ $link }}" target="_blank" rel="noopener noreferrer nofollow">{{ $link }}</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</article>
