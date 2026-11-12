@extends('layouts.student')

@section('title', $homework->title)

@section('content')
    @php
        $badges = [
            'submitted' => ['is-success', __('Submitted')],
            'late' => ['is-warning', __('Late')],
            'reviewed' => ['is-brand', __('Reviewed')],
        ];
        $badge = $submission ? ($badges[$submission->status] ?? null) : null;
        $canSubmit = $submission === null
            ? true
            : (! $submission->isReviewed() && ! $homework->isPastDue());
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-pencil-square"></i>{{ $homework->typeLabel() }}</span>
            <h1 class="page-title">{{ $homework->title }}</h1>
            <p class="page-subtitle">
                <span class="badge-soft {{ $homework->isClasswork() ? 'is-brand' : 'is-info' }}">{{ $homework->typeLabel() }}</span>
                {{ $homework->subject?->name ?? __('Subject') }}
                · {{ $homework->schoolClass?->name }}@if ($homework->section) ({{ __('Section') }} {{ $homework->section->name }})@endif
                @if ($homework->teacher) · {{ $homework->teacher->full_name }} @endif
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('student.homework.index') }}" class="btn btn-light-soft">
                <i class="bi bi-arrow-left"></i>{{ __('All work') }}
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="ui-card p-lg mb-3">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">{{ __('Instructions') }}</h2>
                        <p class="card-subtitle">{{ __('Set by your teacher') }}</p>
                    </div>
                    @if ($badge)
                        <span class="badge-soft {{ $badge[0] }}">{{ $badge[1] }}</span>
                    @else
                        <span class="badge-soft is-warning"><span class="dot"></span>{{ __('Not submitted') }}</span>
                    @endif
                </div>

                <p class="notice-body" style="font-size:.88rem;">{{ $homework->description }}</p>

                @if ($homework->instructions)
                    <div class="mt-3">
                        <div class="text-muted-2" style="font-size:.74rem;text-transform:uppercase;letter-spacing:.04em;">{{ __('Instructions') }}</div>
                        <p class="mb-0 mt-1" style="font-size:.84rem;white-space:pre-line;">{{ $homework->instructions }}</p>
                    </div>
                @endif

                <dl class="detail-list mt-3 mb-0">
                    <div><dt>{{ __('Assigned on') }}</dt><dd>{{ $homework->assigned_date?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>{{ __('Deadline') }}</dt><dd>{{ $homework->due_date?->format('d M Y') ?? __('No deadline') }}</dd></div>
                    <div><dt>{{ __('Session') }}</dt><dd>{{ $homework->academicSession?->name ?? '—' }}</dd></div>
                </dl>

                @if ($homework->attachment_path)
                    <a href="{{ route('student.homework.attachment', $homework->id) }}" class="btn btn-sm btn-light-soft mt-3">
                        <i class="bi bi-download"></i>{{ __('Download attachment') }}
                    </a>
                @endif
            </section>

            @if ($submission && $submission->isReviewed())
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Teacher feedback') }}</h2>
                    <div class="d-flex align-items-baseline gap-2 mt-2">
                        <strong style="font-size:1.6rem;">
                            {{ $submission->marks !== null ? rtrim(rtrim(number_format((float) $submission->marks, 2), '0'), '.') : '—' }}
                        </strong>
                        <span class="text-muted-2" style="font-size:.78rem;">{{ __('marks') }}</span>
                    </div>
                    <p class="notice-body mt-2">{{ $submission->feedback ?: __('No written feedback was recorded.') }}</p>
                    <p class="text-muted-2 mb-0" style="font-size:.74rem;">
                        {{ __('Reviewed :date', ['date' => $submission->reviewed_at?->format('d M Y, h:i A') ?? '—']) }}
                    </p>
                </section>
            @endif
        </div>

        <div class="col-lg-5">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ $canSubmit ? __('Submit your answer') : __('Your submission') }}</h2>

                @if ($canSubmit)
                    @if ($homework->isPastDue() && $submission === null)
                        <div class="alert alert-warning py-2 px-3 mt-3 mb-0" style="font-size:.78rem;">
                            <i class="bi bi-exclamation-triangle"></i>
                            {{ __('The deadline has passed. You can still submit, but it will be marked late.') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('student.homework.submit', $homework->id) }}"
                          enctype="multipart/form-data" class="submit-box mt-3">
                        @csrf
                        <div>
                            <label class="form-label" for="text_answer">{{ __('Answer') }}</label>
                            <textarea class="form-control" id="text_answer" name="text_answer" rows="7"
                                      maxlength="8000" placeholder="{{ __('Write your answer here…') }}">{{ old('text_answer', $submission?->text_answer) }}</textarea>
                            @error('text_answer')
                                <div class="text-danger mt-1" style="font-size:.75rem;">{{ $message }}</div>
                            @enderror
                        </div>

                        <div>
                            <label class="form-label" for="attachment">{{ __('Attachment (optional)') }}</label>
                            <input class="form-control" type="file" id="attachment" name="attachment"
                                   accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.webp">
                            <div class="text-muted-2 mt-1" style="font-size:.72rem;">
                                {{ __('PDF, Word, text or an image. Up to 4 MB.') }}
                            </div>
                            @error('attachment')
                                <div class="text-danger mt-1" style="font-size:.75rem;">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i>{{ $submission
                                ? __('Update submission')
                                : __('Submit :type', ['type' => mb_strtolower($homework->typeLabel())]) }}
                        </button>
                    </form>
                @else
                    <dl class="detail-list mt-3 mb-0">
                        <div><dt>{{ __('Status') }}</dt><dd><span class="badge-soft {{ $badge[0] ?? '' }}">{{ $badge[1] ?? __('Submitted') }}</span></dd></div>
                        <div><dt>{{ __('Submitted on') }}</dt><dd>{{ $submission?->submitted_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                        @if ($submission?->resubmitted_at)
                            <div><dt>{{ __('Updated on') }}</dt><dd>{{ $submission->resubmitted_at->format('d M Y, h:i A') }}</dd></div>
                        @endif
                        @if ($submission?->attachment_path)
                            <div>
                                <dt>{{ __('Attachment') }}</dt>
                                <dd><a href="{{ route('student.homework.submission.file', $submission->id) }}">{{ $submission->attachment_name ?: __('Download') }}</a></dd>
                            </div>
                        @endif
                    </dl>

                    @if ($submission?->text_answer)
                        <p class="notice-body mt-3">{{ $submission->text_answer }}</p>
                    @endif

                    <p class="text-muted-2 mt-3 mb-0" style="font-size:.76rem;">
                        {{ $submission->isReviewed()
                            ? __('This work has been reviewed, so it can no longer be changed.')
                            : __('The deadline has passed, so this submission can no longer be changed.') }}
                    </p>
                @endif
            </section>

            <section class="ui-card p-lg mt-3">
                <h2 class="card-title">{{ __('Before you submit') }}</h2>
                <ul class="text-muted-2 mt-2 mb-0" style="font-size:.78rem;padding-left:1.1rem;">
                    <li>{{ __('Only your own work can be submitted to this assignment.') }}</li>
                    <li>{{ __('You may update your answer any time before the deadline.') }}</li>
                    <li>{{ __('Once your teacher records marks, the answer is locked.') }}</li>
                </ul>
            </section>
        </div>
    </div>
@endsection
