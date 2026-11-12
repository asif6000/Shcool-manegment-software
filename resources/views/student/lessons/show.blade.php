@extends('layouts.student')

@section('title', $lesson->title)

@section('content')
    @include('lessons.partials.style')

    @php
        $fileUrl = fn (App\Models\LessonMaterial $material): string => route('student.lessons.materials.file', [$lesson, $material]);
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-easel2"></i>{{ $lesson->subject?->name ?? __('Lesson') }}</span>
            <h1 class="page-title">{{ $lesson->title }}</h1>
            <p class="page-subtitle">{{ $lesson->chapterLabel() ?: __('Class :class', ['class' => $lesson->schoolClass?->name ?? '—']) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('student.lessons.index') }}" class="btn btn-light-soft">
                <i class="bi bi-arrow-left"></i>{{ __('All lessons') }}
            </a>
        </div>
    </div>

    @include('student.lessons.partials.lesson-body', ['links' => $links, 'fileUrl' => $fileUrl])

    @if ($related->isNotEmpty())
        <section class="ui-card p-lg mt-3">
            <h2 class="card-title">{{ __('More lessons from :subject', ['subject' => $lesson->subject?->name ?? __('this subject')]) }}</h2>
            <ul class="lsn-links mt-2">
                @foreach ($related as $other)
                    <li>
                        <a href="{{ route('student.lessons.show', $other) }}">
                            <i class="bi bi-journal-text"></i>
                            {{ $other->title }}
                            @if ($other->chapterLabel())
                                <span class="text-muted-2">· {{ $other->chapterLabel() }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
