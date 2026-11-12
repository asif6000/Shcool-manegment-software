    @php
        $statusBadges = [
            'submitted' => ['is-success', __('Submitted')],
            'late' => ['is-warning', __('Late')],
            'reviewed' => ['is-brand', __('Reviewed')],
        ];
        $percent = $expected > 0 ? round($submissions->total() / $expected * 100) : 0;
    @endphp

    <div class="page-header{{ ($panel ?? false) ? ' panel-header' : '' }}">
        <div>
            @if (! ($panel ?? false))
                <span class="page-eyebrow"><i class="bi bi-pencil-square"></i>{{ __('Homework') }}</span>
            @endif
            <h1 class="page-title">{{ $homework->title }}</h1>
            <p class="page-subtitle">
                {{ $homework->schoolClass?->name ?? '—' }}
                · {{ $homework->section?->name ?? __('Whole class') }}
                · {{ $homework->subject?->name ?? '—' }}
                · {{ __('Due') }} {{ $homework->due_date?->format('d M Y') ?? '—' }}
                @if ($homework->isPastDue())
                    <span class="badge-soft is-danger ms-1">{{ __('Deadline passed') }}</span>
                @endif
                @if ($homework->status === \App\Models\Homework::DRAFT)
                    <span class="badge-soft is-warning ms-1">{{ __('Draft — not visible to students yet') }}</span>
                @elseif ($homework->status === \App\Models\Homework::CANCELLED)
                    <span class="badge-soft is-danger ms-1">{{ __('Cancelled') }}</span>
                @endif
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('teacher.homework.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('All homework') }}</a>
            @can('update', $homework)
                @if ($homework->status === \App\Models\Homework::DRAFT)
                    <form method="POST" action="{{ route('teacher.homework.publish', $homework) }}" class="d-inline"
                          data-confirm="{{ __('Publish this homework? The class is notified now.') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary"><i class="bi bi-megaphone"></i>{{ __('Publish') }}</button>
                    </form>
                @elseif ($homework->status === \App\Models\Homework::PUBLISHED)
                    <form method="POST" action="{{ route('teacher.homework.unpublish', $homework) }}" class="d-inline"
                          data-confirm="{{ __('Move this homework back to draft? Students stop seeing it until you publish again.') }}">
                        @csrf
                        <button type="submit" class="btn btn-light-soft"><i class="bi bi-arrow-counterclockwise"></i>{{ __('Unpublish') }}</button>
                    </form>
                    <form method="POST" action="{{ route('teacher.homework.cancel', $homework) }}" class="d-inline"
                          data-confirm="{{ __('Cancel this homework? Students stop seeing it; submissions already made are kept.') }}">
                        @csrf
                        <button type="submit" class="btn btn-light-soft"><i class="bi bi-slash-circle"></i>{{ __('Cancel') }}</button>
                    </form>
                @endif
                <a href="{{ route('teacher.homework.edit', $homework) }}" class="btn btn-primary">
                    <i class="bi bi-pencil"></i>{{ __('Edit') }}
                </a>
            @endcan
        </div>
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
            <section class="ui-card p-lg mb-3">
                <h2 class="card-title">{{ __('What the students were told') }}</h2>
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
                    <div><dt>{{ __('Deadline') }}</dt><dd>{{ $homework->due_date?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>{{ __('Teacher') }}</dt><dd>{{ $homework->teacher?->full_name ?? __('Not assigned') }}</dd></div>
                    <div><dt>{{ __('Created by') }}</dt><dd>{{ $homework->creator?->name ?? '—' }}</dd></div>
                </dl>

                @if ($attachments !== [])
                    @foreach ($attachments as $file)
                        <a href="{{ $file['route'] }}" class="btn btn-light-soft w-100 mt-3">
                            <i class="bi bi-download"></i>{{ __('Open attachment') }}
                        </a>
                    @endforeach
                @endif

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

            @can('update', $homework)
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Delete') }}</h2>
                    <p class="text-muted-2" style="font-size:.78rem;">{{ __('Removing the assignment deletes it and the answers students sent for it. The portal link closes straight away.') }}</p>
                    <form method="POST" action="{{ route('teacher.homework.destroy', $homework) }}"
                          data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                          data-confirm-delete="1"
                          data-confirm-label="{{ __('Permanently Delete') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-light-soft text-danger w-100">
                            <i class="bi bi-trash"></i>{{ __('Delete homework') }}
                        </button>
                    </form>
                </section>
            @endcan
        </div>

        <div class="col-lg-8">
            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Student answers') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Marks and notes are visible to that student in their portal.') }}</p>
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
                                                <a href="{{ route('teacher.homework.submissions.file', $submission) }}"
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
                                            @can('update', $homework)
                                                <button class="btn btn-sm btn-light-soft mt-2" type="button" data-bs-toggle="collapse"
                                                        data-bs-target="#review-{{ $submission->id }}" aria-expanded="false">
                                                    <i class="bi bi-pencil-square"></i>{{ $submission->isReviewed() ? __('Change review') : __('Review') }}
                                                </button>
                                            @endcan
                                        </td>
                                    </tr>
                                    @can('update', $homework)
                                        <tr>
                                            <td colspan="2" class="p-0 border-top-0">
                                                <div class="collapse" id="review-{{ $submission->id }}">
                                                    <form method="POST" action="{{ route('teacher.homework.submissions.review', $submission) }}"
                                                          class="p-3 border-bottom">
                                                        @csrf
                                                        <div class="row g-2 align-items-end">
                                                            <div class="col-md-3">
                                                                <label class="form-label" for="marks-{{ $submission->id }}">{{ __('Marks') }}</label>
                                                                <input id="marks-{{ $submission->id }}" type="number" step="0.01" min="0" max="1000"
                                                                       name="marks" class="form-control form-control-sm"
                                                                       value="{{ old('marks', $submission->marks) }}" placeholder="{{ __('0–1000') }}">
                                                            </div>
                                                            <div class="col-md-7">
                                                                <label class="form-label" for="feedback-{{ $submission->id }}">{{ __('Feedback for the student') }}</label>
                                                                <textarea id="feedback-{{ $submission->id }}" name="feedback" rows="2"
                                                                          class="form-control form-control-sm" maxlength="2000">{{ old('feedback', $submission->feedback) }}</textarea>
                                                            </div>
                                                            <div class="col-md-2">
                                                                <button type="submit" class="btn btn-sm btn-primary w-100">
                                                                    {{ __('Save review') }}
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="text-muted-2 mt-2" style="font-size:.72rem;">
                                                            {{ __('Saving marks the answer as reviewed, which locks further edits by the student.') }}
                                                        </div>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endcan
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