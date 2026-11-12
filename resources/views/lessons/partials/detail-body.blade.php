    @php
        $preview = ($preview ?? false) === true;

        // A download is only ever a link this actor's own controller will stream:
        // the staff route re-checks the period, the portal route re-checks the
        // enrolment. Nothing here trusts the address in the browser bar.
        $fileUrl = fn (App\Models\LessonMaterial $material): string => route('lessons.materials.file', [$lesson, $material]);
    @endphp

    <div class="page-header{{ ($panel ?? false) ? ' panel-header' : '' }}">
        @if (! $preview)
            <div>
                <span class="page-eyebrow"><i class="bi bi-easel2"></i>{{ __('Lesson management') }}</span>
                <h1 class="page-title">{{ $lesson->title }}</h1>
                <p class="page-subtitle">{{ $lesson->chapterLabel() ?: __('No chapter marked yet') }}</p>
            </div>
        @else
            <div>
                <span class="page-eyebrow"><i class="bi bi-mortarboard"></i>{{ __('Preview') }}</span>
                <h1 class="page-title">{{ $lesson->title }}</h1>
            </div>
        @endif
        <div class="page-actions">
            <a href="{{ route('lessons.index') }}" class="btn btn-light-soft" data-td-open="lessons">
                <i class="bi bi-arrow-left"></i>{{ __('All lessons') }}
            </a>
            @if ($preview)
                <a href="{{ route('lessons.show', $lesson) }}" class="btn btn-light-soft">
                    <i class="bi bi-person-badge"></i>{{ __('Staff view') }}
                </a>
            @endif
            @if ($mayEdit)
                <a href="{{ route('lessons.edit', $lesson) }}" class="btn btn-primary" data-td-open="lessons">
                    <i class="bi bi-pencil"></i>{{ __('Edit lesson') }}
                </a>
            @endif
        </div>
    </div>

    <div class="lsn-banner" data-lsn-banner role="status" aria-live="polite"></div>
    <div class="lsn-error" data-lsn-global-error role="alert"></div>

    @if ($preview)
        <p class="lsn-preview-flag">
            <i class="bi bi-eye"></i>
            {{ $lesson->isPublished()
                ? __('This is the page the class sees. Nothing here is editable.')
                : __('Still a draft — this is what the class would see the moment you publish it.') }}
        </p>

        @include('student.lessons.partials.lesson-body', ['links' => $links, 'fileUrl' => $fileUrl])
    @else
        <div class="row g-3">
            <div class="col-lg-8">
                @if ($mayEdit)
                    <section class="ui-card p-lg mb-3" data-lsn-index="{{ route('lessons.index') }}">
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge-soft {{ $lesson->isPublished() ? 'is-brand' : '' }}">
                                <i class="bi {{ $lesson->isPublished() ? 'bi-broadcast' : 'bi-pencil' }}"></i>
                                {{ $lesson->isPublished() ? __('Published') : __('Draft') }}
                            </span>
                            <span class="text-muted-2 me-auto" style="font-size:.8rem;">
                                {{ $lesson->isPublished()
                                    ? __('Last published :date · visible in the student portal.', ['date' => $lesson->published_at?->translatedFormat('d M Y, h:i A') ?? '—'])
                                    : __('Only you and the office can open this lesson.') }}
                            </span>
                            @if ($lesson->isPublished())
                                <button type="button" class="btn btn-sm btn-light-soft" data-lsn-action="unpublish"
                                        data-lsn-url="{{ route('lessons.unpublish', $lesson) }}"
                                        data-lsn-title="{{ $lesson->title }}">
                                    <i class="bi bi-eye-slash"></i>{{ __('Unpublish') }}
                                </button>
                            @else
                                <button type="button" class="btn btn-sm btn-primary" data-lsn-action="publish"
                                        data-lsn-url="{{ route('lessons.publish', $lesson) }}"
                                        data-lsn-title="{{ $lesson->title }}">
                                    <i class="bi bi-broadcast"></i>{{ __('Publish now') }}
                                </button>
                                <button type="button" class="btn btn-sm btn-light-soft text-danger" data-lsn-action="delete"
                                        data-lsn-url="{{ route('lessons.destroy', $lesson) }}"
                                        data-lsn-title="{{ $lesson->title }}">
                                    <i class="bi bi-trash"></i>{{ __('Delete draft') }}
                                </button>
                            @endif
                        </div>
                    </section>
                @endif

                <section class="ui-card p-lg">
                    @include('student.lessons.partials.lesson-body', ['links' => $links, 'fileUrl' => $fileUrl])
                </section>
            </div>

            <div class="col-lg-4">
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Lesson details') }}</h2>
                    <dl class="lsn-detail-meta mt-3">
                        <div>
                            <dt>{{ __('Class') }}</dt>
                            <dd>{{ $lesson->schoolClass?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Section') }}</dt>
                            <dd>{{ $lesson->section?->name ?? __('Whole class') }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Subject') }}</dt>
                            <dd>{{ $lesson->subject?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Session') }}</dt>
                            <dd>{{ $lesson->academicSession?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Chapter') }}</dt>
                            <dd>{{ $lesson->chapterLabel() ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Words in body') }}</dt>
                            <dd>{{ number_format($words) }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Teacher') }}</dt>
                            <dd>{{ $lesson->teacher?->full_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Written by') }}</dt>
                            <dd>{{ $lesson->creator?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>{{ __('Last updated') }}</dt>
                            <dd>{{ $lesson->updated_at?->format('d M Y H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </section>

                @if ($history->isNotEmpty())
                    <section class="ui-card p-lg mt-3">
                        <h2 class="card-title">{{ __('Lesson history') }}</h2>
                        <p class="text-muted-2" style="font-size:.78rem;">
                            {{ __('Every write on this lesson, newest first.') }}
                        </p>
                        <ul class="lsn-history">
                            @foreach ($history as $entry)
                                <li>
                                    <span class="text-muted-2" style="min-width:5.5rem;">
                                        {{ $entry->created_at?->format('d M H:i') }}
                                    </span>
                                    <span class="flex-grow-1">
                                        {{ $entry->description }}
                                        <span class="d-block text-muted-2" style="font-size:.74rem;">
                                            {{ $entry->user?->name ?? __('Someone removed') }}
                                        </span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </div>
    @endif

    {{-- Publish or withdraw from this page and the panel re-fetches the same
         screen, so what is on the wall is what the server holds. --}}
    <a href="{{ route('lessons.show', ['lesson' => $lesson, 'as' => $preview ? 'student' : null]) }}"
       data-td-open="lessons" data-lsn-refresh hidden></a>
