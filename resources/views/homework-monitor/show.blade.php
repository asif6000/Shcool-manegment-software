@extends('layouts.app')

@section('title', __('Work:') . $homework->title)

@section('content')
    @php
        $statusBadges = [
            'submitted' => ['is-success', __('Submitted')],
            'late' => ['is-warning', __('Late')],
            'reviewed' => ['is-brand', __('Reviewed')],
        ];
        $percent = $expected > 0 ? round($submissions->total() / $expected * 100) : 0;
        $notSubmitted = max($expected - $submissions->total(), 0);
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-eye"></i>{{ $homework->typeLabel() }}</span>
            <h1 class="page-title">{{ $homework->title }}</h1>
            <p class="page-subtitle">
                <span class="badge-soft {{ $homework->isClasswork() ? 'is-brand' : 'is-info' }}">{{ $homework->typeLabel() }}</span>
                {{ $homework->schoolClass?->name ?? '—' }}
                · {{ $homework->section?->name ?? __('Whole class') }}
                · {{ $homework->subject?->name ?? '—' }}
                · {{ __('Assigned') }} {{ $homework->assigned_date?->format('d M Y') ?? '—' }}
                @if ($homework->due_date)
                    · {{ __('Due') }} {{ $homework->due_date->format('d M Y') }}
                @else
                    · {{ __('No deadline') }}
                @endif
                @if ($homework->status === \App\Models\Homework::DRAFT)
                    <span class="badge-soft is-warning ms-1">{{ __('Draft — students cannot see it yet') }}</span>
                @elseif ($homework->status === \App\Models\Homework::CANCELLED)
                    <span class="badge-soft is-danger ms-1">{{ __('Cancelled') }}</span>
                @endif
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('work-monitor.index', ['type' => $homework->category]) }}" class="btn btn-light-soft">
                <i class="bi bi-arrow-left"></i>{{ __('All work') }}
            </a>
            @if ($homework->attachment_path)
                <a href="{{ route('work-monitor.attachment', $homework) }}" class="btn btn-light-soft">
                    <i class="bi bi-download"></i>{{ __('Open attachment') }}
                </a>
            @endif
        </div>
    </div>

    <div class="alert alert-light d-flex align-items-center gap-2 mb-3" style="font-size:.8rem;">
        <i class="bi bi-lock"></i>
        {{ __('The office reads this record only. Changing it, publishing it or deleting the answers is the teacher who set it.') }}
    </div>

    <div class="mini-stats mb-3">
        <div class="mini-stat">
            <span class="value">{{ $submissions->total() }} / {{ $expected }}</span>
            <span class="label">{{ __('Answers received') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ $reviewed }}</span>
            <span class="label">{{ __('Reviewed') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ $late }}</span>
            <span class="label">{{ __('Submitted late') }}</span>
        </div>
        <div class="mini-stat">
            <span class="value">{{ $notSubmitted }}</span>
            <span class="label">{{ __('Nothing sent yet') }}</span>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ $homework->isClasswork() ? __('What was taught') : __('What the students were told') }}</h2>
                <p class="mt-3 mb-0" style="font-size:.86rem;white-space:pre-line;">{{ $homework->description }}</p>
                @if ($homework->instructions)
                    <div class="mt-3">
                        <div class="text-muted-2" style="font-size:.74rem;text-transform:uppercase;letter-spacing:.04em;">{{ __('Instructions') }}</div>
                        <p class="mb-0 mt-1" style="font-size:.84rem;white-space:pre-line;">{{ $homework->instructions }}</p>
                    </div>
                @endif
                <dl class="detail-list mt-3 mb-0">
                    <div><dt>{{ __('Session') }}</dt><dd>{{ $homework->academicSession?->name ?? '—' }}</dd></div>
                    <div><dt>{{ __('Assigned on') }}</dt><dd>{{ $homework->assigned_date?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>{{ __('Deadline') }}</dt><dd>{{ $homework->due_date?->format('d M Y') ?? __('No deadline') }}</dd></div>
                    <div><dt>{{ __('Teacher') }}</dt><dd>{{ $homework->teacher?->full_name ?? __('Not assigned') }}</dd></div>
                    <div><dt>{{ __('Created by') }}</dt><dd>{{ $homework->creator?->name ?? '—' }}</dd></div>
                    <div><dt>{{ __('Published on') }}</dt><dd>{{ $homework->published_at?->format('d M Y, g:i a') ?? __('Not published') }}</dd></div>
                </dl>

                <div class="mt-4">
                    <div class="d-flex justify-content-between" style="font-size:.78rem;">
                        <span class="text-muted-2">{{ __('Class coverage') }}</span>
                        <span class="fw-600">{{ $percent }}%</span>
                    </div>
                    <div class="progress mt-1" role="progressbar" aria-label="{{ __('Submissions received') }}"
                         aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100" style="height:8px;">
                        <div class="progress-bar {{ $percent >= 60 ? 'bg-success' : 'bg-warning' }}" style="width:{{ $percent }}%;"></div>
                    </div>
                    <div class="text-muted-2 mt-1" style="font-size:.72rem;">
                        {{ trans_choice(':count student on the roll|:count students on the roll', $expected, ['count' => $expected]) }}
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-8">
            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Student answers') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Marks and notes are recorded by the teacher; this screen only reads them back.') }}</p>
                    </div>
                </div>

                @if ($submissions->isEmpty())
                    <div class="empty-state py-4">
                        <div class="empty-icon"><i class="bi bi-inbox"></i></div>
                        <h3>{{ __('No answers yet') }}</h3>
                        <p>{{ __('Students submit from the portal. Answers appear here as they arrive, even after the deadline — late ones are flagged.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <tbody>
                                @foreach ($submissions as $submission)
                                    @php [$badgeClass, $badgeLabel] = $statusBadges[$submission->status] ?? ['', __(ucfirst($submission->status))]; @endphp
                                    <tr>
                                        <td style="min-width:0;">
                                            <div class="fw-semibold">{{ $submission->student?->full_name ?? '—' }}</div>
                                            <div class="text-muted-2" style="font-size:.74rem;">
                                                {{ $submission->student?->student_id ?? '—' }}
                                                · {{ __('Submitted') }} {{ $submission->submitted_at?->format('d M Y, g:i a') ?? '—' }}
                                                @if ($submission->resubmitted_at)
                                                    · {{ __('Updated') }} {{ $submission->resubmitted_at->format('d M Y, g:i a') }}
                                                @endif
                                            </div>

                                            @if ($submission->text_answer)
                                                <a class="d-inline-block mt-1" style="font-size:.76rem;" data-bs-toggle="collapse"
                                                   href="#answer-{{ $submission->id }}" role="button"
                                                   aria-expanded="false" aria-controls="answer-{{ $submission->id }}">
                                                    <i class="bi bi-chat-left-text"></i>{{ __('Read the answer') }}
                                                </a>
                                                <div class="collapse mt-2" id="answer-{{ $submission->id }}">
                                                    <div class="ui-card p-3" style="font-size:.82rem;white-space:pre-line;">
                                                        {{ $submission->text_answer }}
                                                    </div>
                                                </div>
                                            @endif

                                            @if ($submission->attachment_path)
                                                <a href="{{ route('work-monitor.submission.file', $submission) }}"
                                                   class="btn btn-sm btn-light-soft mt-2">
                                                    <i class="bi bi-paperclip"></i>{{ $submission->attachment_name ?? __('Attachment') }}
                                                </a>
                                            @endif
                                        </td>
                                        <td class="text-end" style="width:230px;">
                                            <span class="badge-soft {{ $badgeClass }}"><span class="dot"></span>{{ $badgeLabel }}</span>
                                            @if ($submission->isReviewed())
                                                <div class="text-muted-2 mt-1" style="font-size:.74rem;">
                                                    {{ $submission->marks !== null ? $submission->marks.' '.__('marks') : __('No marks') }}
                                                </div>
                                                <div class="text-muted-2" style="font-size:.72rem;">
                                                    {{ $submission->reviewer?->name }} · {{ $submission->reviewed_at?->format('d M Y') }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($submission->feedback)
                                        <tr>
                                            <td colspan="2" class="pt-0">
                                                <div class="text-muted-2" style="font-size:.76rem;">
                                                    <i class="bi bi-chat-quote"></i>{{ __('Teacher note:') }} {{ $submission->feedback }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($submissions->hasPages())
                        <div class="table-footer">{{ $submissions->links() }}</div>
                    @endif
                @endif
            </section>
        </div>
    </div>
@endsection
