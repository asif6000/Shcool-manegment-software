@extends('layouts.student')

@section('title', __('Lessons'))

@section('content')
    @php
        $html = app(\App\Support\HtmlContent::class);
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-easel2"></i>{{ __('Studies') }}</span>
            <h1 class="page-title">{{ __('Lessons') }}</h1>
            <p class="page-subtitle">{{ __('What your teachers have written for your class — chapter by chapter, with the files, pictures and videos that go with each lesson.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('student.materials.index') }}" class="btn btn-light-soft">
                <i class="bi bi-journal-bookmark"></i>{{ __('Classwork & materials') }}
            </a>
        </div>
    </div>

    <section class="ui-card p-lg mb-3">
        <form method="GET" action="{{ route('student.lessons.index') }}" class="filter-form" role="search">
            <label class="visually-hidden" for="lsn-p-subject">{{ __('Subject') }}</label>
            <select id="lsn-p-subject" name="subject" class="form-select">
                <option value="">{{ __('All subjects') }}</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected((int) ($filters['subject'] ?? 0) === $subject->id)>{{ $subject->name }}</option>
                @endforeach
            </select>
            <label class="visually-hidden" for="lsn-p-chapter">{{ __('Chapter') }}</label>
            <select id="lsn-p-chapter" name="chapter" class="form-select">
                <option value="">{{ __('All chapters') }}</option>
                @foreach ($chapters as $chapter)
                    <option value="{{ $chapter }}" @selected((string) ($filters['chapter'] ?? '') === (string) $chapter)>
                        {{ __('Chapter :n', ['n' => $chapter]) }}
                    </option>
                @endforeach
            </select>
            <label class="visually-hidden" for="lsn-p-q">{{ __('Search') }}</label>
            <input id="lsn-p-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control"
                   placeholder="{{ __('Search a lesson by name…') }}">
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i>{{ __('Search') }}</button>
            @if (array_filter($filters))
                <a href="{{ route('student.lessons.index') }}" class="btn btn-link">{{ __('Clear') }}</a>
            @endif
        </form>
    </section>

    @if ($rows->isEmpty())
        <section class="ui-card">
            <div class="empty-state py-4">
                <div class="empty-icon"><i class="bi bi-easel2"></i></div>
                <h3>{{ __('No lesson published yet') }}</h3>
                <p>{{ array_filter($filters)
                    ? __('Nothing matches that filter for your class. Try clearing it.')
                    : __('Lessons your teachers publish for your class appear here.') }}</p>
            </div>
        </section>
    @else
        <div class="lsn-card-grid">
            @foreach ($rows as $lesson)
                <article class="lsn-card">
                    <div class="lsn-card-top">
                        <span class="badge-soft is-brand">{{ $lesson->subject?->name ?? __('Subject') }}</span>
                        <span class="text-muted-2" style="font-size:.74rem;">
                            {{ $lesson->published_at?->translatedFormat('d M Y') ?? '' }}
                        </span>
                    </div>
                    <h2 class="lsn-card-title">{{ $lesson->title }}</h2>
                    @if ($lesson->chapterLabel())
                        <p class="lsn-card-chapter"><i class="bi bi-journal-section"></i>{{ $lesson->chapterLabel() }}</p>
                    @endif
                    <p class="lsn-card-note">
                        {{ Str::limit($lesson->description ?: $html->excerpt($lesson->content), 120) ?: __('Open the lesson to read it.') }}
                    </p>
                    <div class="lsn-card-foot">
                        <span class="text-muted-2" style="font-size:.74rem;">
                            {{ $lesson->teacher?->full_name ?? __('Your teacher') }}
                            @if ($lesson->materials_count > 0)
                                · <i class="bi bi-paperclip"></i>{{ trans_choice(':count file|:count files', $lesson->materials_count, ['count' => $lesson->materials_count]) }}
                            @endif
                        </span>
                        <a href="{{ route('student.lessons.show', $lesson) }}" class="btn btn-sm btn-primary">
                            {{ __('Open lesson') }}<i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-3">{{ $rows->links() }}</div>
    @endif
@endsection
