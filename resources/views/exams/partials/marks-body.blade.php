    @php
        $paperClasses = $papers->pluck('schoolClass')->filter()->unique('id')->sortBy('level')->values();
        $totalStudents = $sheet['enrollments']->count();
        $canSubmit = $canSubmit && $paper !== null && ! $exam->isPublished();
        // A handed-over sheet is a reviewed document, so the browser asks for the
        // reason the server will demand before it lets the form go.
        $needsReasonOnEdit = $submission !== null && ! auth()->user()->can('marks.approve');
    @endphp

    <div class="page-header{{ ($panel ?? false) ? ' panel-header' : '' }}">
        @if (! ($panel ?? false))
            <div>
                <span class="page-eyebrow"><i class="bi bi-pencil-square"></i>{{ __('Examination') }}</span>
                <h1 class="page-title">{{ __('Enter marks') }}</h1>
                <p class="page-subtitle">
                    {{ $exam->name }} · {{ $exam->academicSession?->name }}
                    <span class="badge-soft {{ $exam->isPublished() ? 'is-success' : 'is-warning' }} ms-1">{{ __(ucfirst($exam->status)) }}</span>
                </p>
            </div>
        @endif
        <div class="page-actions">
            @if ($panel ?? false)
                <span class="badge-soft is-brand">{{ $exam->name }}</span>
                <a href="{{ route('dashboard.results') }}" class="btn btn-light-soft">
                    <i class="bi bi-arrow-left"></i>{{ __('All exams') }}
                </a>
            @endif
            @can('exams.view')
                <a href="{{ route('exams.show', $exam) }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Exam') }}</a>
            @endcan
            @can('results.view')
                <a href="{{ route('exams.results.index', $exam) }}" class="btn btn-primary"><i class="bi bi-table"></i>{{ __('Results') }}</a>
            @endcan
        </div>
    </div>

    @if ($exam->isPublished())
        <div class="alert alert-warning">
            <i class="bi bi-lock-fill"></i>
            <div>{{ __('Marks are locked because results are published. Unpublish the results to make changes.') }}</div>
        </div>
    @endif

    @if ($papers->isEmpty())
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-list-check"></i></div>
                <h3>{{ __('No subject papers yet') }}</h3>
                <p>{{ __('Add subject papers to this exam before entering marks.') }}</p>
                <a href="{{ route('exams.show', $exam) }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i>{{ __('Add papers') }}</a>
            </div>
        </section>
    @else
        <section class="ui-card p-lg mb-3">
            <div class="card-head mb-3">
                <div>
                    <h2 class="card-title mb-1">{{ __('Choose a paper') }}</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Marks are saved per class and subject. Absent students can be marked without a score.') }}</p>
                </div>
                @if ($paper)
                    <span class="badge-soft is-brand">{{ $paper->subject?->name }} · {{ $paper->schoolClass?->name }}</span>
                @endif
            </div>

            <form method="GET" action="{{ route('exams.marks.index', $exam) }}" class="filter-form">
                <div>
                    <label class="form-label" for="marks-class">{{ __('Class') }}</label>
                    <select id="marks-class" name="class_id" class="form-select" data-auto-submit>
                        @foreach ($paperClasses as $class)
                            <option value="{{ $class->id }}" @selected($classId === $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="marks-paper">{{ __('Subject paper') }}</label>
                    <select id="marks-paper" name="paper_id" class="form-select" data-auto-submit>
                        @foreach ($classPapers as $option)
                            <option value="{{ $option->id }}" @selected($paper?->id === $option->id)>
                                {{ $option->subject?->name }}@if ($option->exam_date) · {{ $option->exam_date->format('d M') }}@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="marks-section">{{ __('Section') }}</label>
                    <select id="marks-section" name="section_id" class="form-select" data-auto-submit>
                        @if ($wholeClassAllowed ?? true)
                            <option value="">{{ __('All sections') }}</option>
                        @endif
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected($sectionId === $section->id)>{{ __('Section') }} {{ $section->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="align-self-end d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Show') }}</button>
                </div>
            </form>
        </section>

        @if ($submission !== null)
            @php
                $submissionTones = [
                    \App\Models\MarkSubmission::STATUS_SUBMITTED => 'is-warning',
                    \App\Models\MarkSubmission::STATUS_APPROVED => 'is-success',
                    \App\Models\MarkSubmission::STATUS_REJECTED => 'is-danger',
                ];
            @endphp
            <div class="alert alert-light border d-flex flex-wrap align-items-center gap-3 mb-3" role="status">
                <span class="badge-soft {{ $submissionTones[$submission->status] ?? '' }}">{{ $submission->statusLabel() }}</span>
                <div class="flex-grow-1" style="font-size:.82rem;">
                    {{ __('Submitted :time by :teacher · :entered of :total marks, :absent absent.', [
                        'time' => $submission->submitted_at?->translatedFormat('d M, H:i') ?? '—',
                        'teacher' => $submission->submittedBy?->name ?? '—',
                        'entered' => $submission->entered_count,
                        'total' => $submission->enrolled_count,
                        'absent' => $submission->absent_count,
                    ]) }}
                    @if ($submission->review_note)
                        <div class="text-muted-2 mt-1">{{ $submission->reviewedBy?->name }}: {{ $submission->review_note }}</div>
                    @endif
                    @if ($submission->revision_count > 0)
                        <div class="text-muted-2 mt-1">{{ trans_choice('Edited :count time after submission.|Edited :count times after submission.', $submission->revision_count, ['count' => $submission->revision_count]) }}</div>
                    @endif
                </div>
                @if (auth()->user()->can('marks.approve') && $submission->isAwaitingReview())
                    <a href="{{ route('marks.submissions.index', ['status' => 'submitted']) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-clipboard-check"></i>{{ __('Review queue') }}
                    </a>
                @endif
            </div>
        @endif

        @if ($paper === null)
            <section class="ui-card">
                <div class="empty-state">
                    <div class="empty-icon"><i class="bi bi-journal-x"></i></div>
                    <h3>{{ __('No papers for this class') }}</h3>
                    <p>{{ __('Add a subject paper for this class from the exam page.') }}</p>
                </div>
            </section>
        @else
            <section class="ui-card table-card">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ $paper->subject?->name }} · {{ $paper->schoolClass?->name }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">
                            {{ __('Full marks :full · pass marks :pass', ['full' => number_format((float) $paper->full_marks, 2), 'pass' => number_format((float) $paper->pass_marks, 2)]) }}
                            @if ($paper->exam_date)
                                · {{ $paper->exam_date->format('d M Y') }}
                            @endif
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge-soft {{ $enteredCount >= $totalStudents && $totalStudents > 0 ? 'is-success' : ($enteredCount > 0 ? 'is-warning' : '') }}">
                            {{ $enteredCount }} / {{ $totalStudents }} {{ __('entered') }}
                        </span>
                    </div>
                </div>

                @if ($sheet['enrollments']->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-people"></i></div>
                        <h3>{{ __('No active students') }}</h3>
                        <p>{{ __('This class has no active enrollments in the exam session.') }}</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('exams.marks.store', $exam) }}" id="marks-form">
                        @csrf
                        <input type="hidden" name="paper_id" value="{{ $paper->id }}">
                        <input type="hidden" name="section_id" value="{{ $sectionId }}">

                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col" style="width:70px;">{{ __('Roll') }}</th>
                                        <th scope="col">{{ __('Student') }}</th>
                                        <th scope="col" style="width:150px;">{{ __('Marks') }}</th>
                                        <th scope="col" style="width:110px;">{{ __('Absent') }}</th>
                                        <th scope="col" style="width:120px;">{{ __('Grade') }}</th>
                                        <th scope="col">{{ __('Remarks') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($sheet['enrollments'] as $row)
                                        @php
                                            $enrollment = $row['enrollment'];
                                            $mark = $row['mark'];
                                            $studentId = $enrollment->student_id;
                                            $oldMarks = old("marks.$studentId.marks_obtained", $mark?->marks_obtained);
                                            $oldAbsent = (bool) old("marks.$studentId.is_absent", $mark?->is_absent);
                                        @endphp
                                        <tr>
                                            <td>{{ $enrollment->roll_no ?? '—' }}</td>
                                            <td>
                                                <div class="fw-600">{{ $enrollment->student?->full_name ?? '—' }}</div>
                                                <div class="text-muted-2" style="font-size:.76rem;">
                                                    {{ $enrollment->student?->student_id }}
                                                    @if ($enrollment->section)
                                                        · {{ __('Section') }} {{ $enrollment->section->name }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td>
                                                <label class="visually-hidden" for="mark-{{ $studentId }}">{{ __('Marks for :name', ['name' => $enrollment->student?->full_name]) }}</label>
                                                <input id="mark-{{ $studentId }}" type="number" step="0.01" min="0" max="{{ (float) $paper->full_marks }}"
                                                    name="marks[{{ $studentId }}][marks_obtained]" class="form-control form-control-sm"
                                                    value="{{ old('marks.'.$studentId.'.marks_obtained', $mark?->marks_obtained) }}"
                                                    @disabled($exam->isPublished())>
                                            </td>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="1"
                                                        id="absent-{{ $studentId }}"
                                                        name="marks[{{ $studentId }}][is_absent]"
                                                        data-absent-toggle="mark-{{ $studentId }}"
                                                        @checked($oldAbsent)
                                                        @disabled($exam->isPublished())>
                                                    <label class="form-check-label" for="absent-{{ $studentId }}">{{ __('Absent') }}</label>
                                                </div>
                                            </td>
                                            <td>
                                                @if ($mark?->grade_letter)
                                                    <span class="badge-soft {{ $mark->grade_letter === 'F' ? 'is-danger' : 'is-success' }}">
                                                        {{ $mark->grade_letter }} · {{ number_format((float) $mark->grade_point, 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted-2">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <label class="visually-hidden" for="remark-{{ $studentId }}">{{ __('Remarks for :name', ['name' => $enrollment->student?->full_name]) }}</label>
                                                <input id="remark-{{ $studentId }}" type="text" maxlength="160"
                                                    name="marks[{{ $studentId }}][remarks]" class="form-control form-control-sm"
                                                    value="{{ old('marks.'.$studentId.'.remarks', $mark?->remarks) }}"
                                                    @disabled($exam->isPublished())>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if (! $exam->isPublished())
                            <div class="table-footer">
                                <span class="text-muted-2" style="font-size:.78rem;">{{ __('Leave a score empty if the paper has not been marked yet.') }}</span>
                                @if ($submission !== null)
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="form-label mb-0" for="marks-correction-reason" style="font-size:.78rem;">{{ __('Correction reason') }}</label>
                                        <input id="marks-correction-reason" name="correction_reason" type="text" maxlength="255"
                                            class="form-control form-control-sm" style="min-width:280px;"
                                            placeholder="{{ $needsReasonOnEdit ? __('Required — this sheet has been submitted') : '' }}"
                                            value="{{ old('correction_reason') }}">
                                    </div>
                                @endif
                                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i>{{ __('Save marks') }}</button>
                            </div>
                        @endif
                    </form>

                    @if ($canSubmit)
                        <form method="POST" action="{{ route('exams.marks.submit', $exam) }}" class="marks-submitbar">
                            @csrf
                            <input type="hidden" name="paper_id" value="{{ $paper->id }}">
                            <input type="hidden" name="section_id" value="{{ $sectionId }}">
                            <div class="marks-submitbar__hint">
                                <span class="marks-submitbar__icon" aria-hidden="true"><i class="bi bi-send-check"></i></span>
                                <p>
                                    {{ $submission === null
                                        ? __('Once every score is in, hand the sheet over. Saving after that needs a reason and puts the sheet back in the queue.')
                                        : ($submission->isAwaitingReview()
                                            ? __('This sheet is already in the review queue. Saving again updates the numbers the office sees.')
                                            : __('Resubmitting sends the corrected sheet back to the office for another decision.')) }}
                                </p>
                            </div>
                            <button type="submit" class="btn btn-outline-primary"
                                    @if ($submission?->isAwaitingReview()) disabled @endif>
                                <i class="bi bi-send-check"></i>
                                {{ $submission === null ? __('Submit for review') : __('Resubmit for review') }}
                            </button>
                        </form>
                    @endif
                @endif
            </section>
        @endif
    @endif
