    @php
        $canMark = auth()->user()->can('attendance.mark');
        $statuses = \App\Services\StudentAttendanceService::STATUSES;
        $statusLabels = [
            'present' => __('Present'),
            'absent' => __('Absent'),
            'late' => __('Late'),
            'leave' => __('On leave'),
            'excused' => __('Excused'),
        ];
        $statusTones = [
            'present' => 'is-success',
            'absent' => 'is-danger',
            'late' => 'is-warning',
            'leave' => 'is-info',
            'excused' => 'is-purple',
        ];
        $holidayLocked = $holiday !== null && ! old('override_holiday');
        $locked = ! $canMark || $holidayLocked;
        $canSubmit = $canMark && ! $holidayLocked && auth()->user()->can('attendance.submit');
        // A submitted day is a reviewed document, so the browser asks for the
        // reason the server will demand before it lets the form go.
        $needsReasonOnEdit = $submission !== null && ! auth()->user()->can('attendance.approve');

        $rowStatus = [];
        $initialCounts = array_fill_keys($statuses, 0);
        foreach ($sheet as $row) {
            $studentId = $row['enrollment']->student_id;
            $status = (string) old('statuses.'.$studentId, $row['attendance']?->status ?? 'present');
            if (! array_key_exists($status, $initialCounts)) {
                $status = 'present';
            }
            $rowStatus[$studentId] = $status;
            $initialCounts[$status]++;
        }

        $filterQuery = array_filter([
            'session_id' => $session?->id,
            'class_id' => $class?->id,
            'section_id' => $section?->id,
            'date' => $date->toDateString(),
        ], fn ($value) => filled($value));
    @endphp

    @if (! ($panel ?? false))
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-calendar2-check"></i>{{ __('Daily operations') }}</span>
            <h1 class="page-title">{{ __('Attendance') }}</h1>
            <p class="page-subtitle">{{ __('Mark daily attendance for a class or section. Re-saving the same date updates the existing records.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('academic.index') }}" class="btn btn-light-soft">
                <i class="bi bi-calendar3"></i>{{ __('Academic setup') }}
            </a>
            @can('attendance.report')
                <a href="{{ route('attendance.students.report', $filterQuery) }}" class="btn btn-primary">
                    <i class="bi bi-clipboard-data"></i>{{ __('Monthly report') }}
                </a>
            @endcan
        </div>
    </div>

    @include('attendance.partials.tabs', ['active' => 'students'])
    @endif

    @if ($submission !== null)
        @php
            $submissionTones = [
                \App\Models\AttendanceSubmission::STATUS_SUBMITTED => 'is-warning',
                \App\Models\AttendanceSubmission::STATUS_APPROVED => 'is-success',
                \App\Models\AttendanceSubmission::STATUS_REJECTED => 'is-danger',
            ];
        @endphp
        <div class="alert alert-light border d-flex flex-wrap align-items-center gap-3 mb-3" role="status">
            <span class="badge-soft {{ $submissionTones[$submission->status] ?? '' }}">{{ $submission->statusLabel() }}</span>
            <div class="flex-grow-1" style="font-size:.82rem;">
                {{ __('Submitted :time by :teacher · :present present, :absent absent, :late late of :total students.', [
                    'time' => $submission->submitted_at?->translatedFormat('d M, H:i') ?? '—',
                    'teacher' => $submission->submittedBy?->name ?? '—',
                    'present' => $submission->present_count,
                    'absent' => $submission->absent_count,
                    'late' => $submission->late_count,
                    'total' => $submission->student_count,
                ]) }}
                @if ($submission->review_note)
                    <div class="text-muted-2 mt-1">{{ $submission->reviewedBy?->name }}: {{ $submission->review_note }}</div>
                @endif
                @if ($submission->revision_count > 0)
                    <div class="text-muted-2 mt-1">{{ trans_choice('Edited :count time after submission.|Edited :count times after submission.', $submission->revision_count, ['count' => $submission->revision_count]) }}</div>
                @endif
            </div>
            @can('attendance.approve')
                @if ($submission->isAwaitingReview())
                    <a href="{{ route('attendance.submissions.index', ['status' => 'submitted']) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-clipboard-check"></i>{{ __('Review queue') }}
                    </a>
                @endif
            @endcan
        </div>
    @endif

    <section class="ui-card p-lg mb-3">
        <div class="card-head mb-3">
            <div>
                <h2 class="card-title mb-1">{{ __('Choose class and date') }}</h2>
                <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('The class list only shows active enrollments for the selected session.') }}</p>
            </div>
            <span class="badge-soft is-brand">{{ $date->translatedFormat('l, d F Y') }}</span>
        </div>

        <form method="GET" action="{{ route('attendance.students.index') }}" class="filter-form">
            <div>
                <label class="form-label" for="attendance-session">{{ __('Session') }}</label>
                <select id="attendance-session" name="session_id" class="form-select @error('academic_session_id') is-invalid @enderror" data-auto-submit>
                    @forelse ($sessions as $option)
                        <option value="{{ $option->id }}" @selected($session?->id === $option->id)>{{ $option->name }}</option>
                    @empty
                        <option value="">{{ __('No sessions') }}</option>
                    @endforelse
                </select>
            </div>

            <div>
                <label class="form-label" for="attendance-class">{{ __('Class') }}</label>
                <select id="attendance-class" name="class_id" class="form-select @error('class_id') is-invalid @enderror" data-auto-submit>
                    <option value="">{{ __('Select a class') }}</option>
                    @foreach ($classes as $option)
                        <option value="{{ $option->id }}" @selected($class?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label" for="attendance-section">{{ __('Section') }}</label>
                <select id="attendance-section" name="section_id" class="form-select @error('section_id') is-invalid @enderror" data-auto-submit @disabled($class === null)>
                    <option value="">{{ __('All sections') }}</option>
                    @foreach ($class?->sections ?? [] as $option)
                        <option value="{{ $option->id }}" @selected($section?->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label" for="attendance-date">{{ __('Date') }}</label>
                <input id="attendance-date" type="date" name="date" class="form-control @error('attendance_date') is-invalid @enderror" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" data-auto-submit>
            </div>

            <div class="align-self-end d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Show') }}</button>
                @if ($class !== null || $section !== null || request()->filled('date'))
                    <a href="{{ route('attendance.students.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </div>
        </form>
    </section>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <div class="fw-semibold mb-1">{{ __('Attendance could not be saved.') }}</div>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($session === null)
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-calendar3"></i></div>
                <h3>{{ __('No academic session yet') }}</h3>
                <p>{{ __('Create an academic session, classes and sections before marking attendance.') }}</p>
                @can('academic.manage')
                    <a href="{{ route('academic.index') }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i>{{ __('Open academic setup') }}</a>
                @else
                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Ask the school office to set up this session.') }}</p>
                @endcan
            </div>
        </section>
    @elseif ($class === null)
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-door-open"></i></div>
                <h3>{{ __('Select a class to begin') }}</h3>
                <p>{{ __('Choose a class above; a section is optional when you want to mark the whole class.') }}</p>
            </div>
        </section>
    @elseif ($sheet->isEmpty())
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-people"></i></div>
                <h3>{{ __('No active enrollments') }}</h3>
                <p>{{ __('This class has no active students for :session. Enroll students or pick another class.', ['session' => $session->name]) }}</p>
                @can('students.view')
                    <a href="{{ route('students.index', ['class_id' => $class->id, 'session_id' => $session->id]) }}" class="btn btn-primary mt-3"><i class="bi bi-mortarboard"></i>{{ __('Open students') }}</a>
                @endcan
            </div>
        </section>
    @else
        <section class="ui-card table-card">
            <form method="POST" action="{{ route('attendance.students.store') }}" id="attendance-form">
                @csrf
                @if ($panel ?? false)
                    <input type="hidden" name="workspace" value="teacher">
                    <input type="hidden" name="panel" value="attendance">
                @endif
                <input type="hidden" name="academic_session_id" value="{{ $session->id }}">
                <input type="hidden" name="class_id" value="{{ $class->id }}">
                <input type="hidden" name="section_id" value="{{ $section?->id }}">
                <input type="hidden" name="attendance_date" value="{{ $date->toDateString() }}">

                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ $class->name }}@if ($section) · {{ $section->name }}@endif</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.78rem;">
                            {{ $session->name }} · {{ $date->translatedFormat('l, d F Y') }} ·
                            {{ trans_choice(':count student|:count students', $sheet->count(), ['count' => $sheet->count()]) }}
                            @if ($holiday)
                                · <span class="badge-soft is-warning"><i class="bi bi-calendar-x"></i>{{ __('Holiday') }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap" aria-live="polite">
                        <label class="visually-hidden" for="attendance-search">{{ __('Search students') }}</label>
                        <input id="attendance-search" type="search" class="form-control form-control-sm" style="max-width:220px;" placeholder="{{ __('Search name or roll') }}" autocomplete="off">
                        @foreach ($statusLabels as $value => $label)
                            <span class="badge-soft {{ $statusTones[$value] }}">{{ $label }} <strong data-count-status="{{ $value }}">{{ $initialCounts[$value] }}</strong></span>
                        @endforeach
                        @if ($canMark)
                            <div class="btn-group btn-group-sm" role="group" aria-label="{{ __('Bulk status') }}">
                                <button type="button" class="btn btn-light-soft" data-bulk="present"><i class="bi bi-check2-all"></i>{{ __('All present') }}</button>
                                <button type="button" class="btn btn-light-soft" data-bulk="absent"><i class="bi bi-x-circle"></i>{{ __('All absent') }}</button>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($holiday)
                    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-3 m-3 mb-0" role="status">
                        <i class="bi bi-calendar-x fs-5"></i>
                        <div class="flex-grow-1">
                            <strong>{{ __('Holiday') }}:</strong> {{ $holiday->name }}
                            <div style="font-size:.8rem;">{{ __('Attendance is normally not marked on this date. Confirm the override to continue.') }}</div>
                        </div>
                        @if ($canMark)
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="override_holiday" value="1" id="attendance-override" @checked((bool) old('override_holiday'))>
                                <label class="form-check-label" for="attendance-override">{{ __('Override holiday') }}</label>
                            </div>
                        @endif
                    </div>
                @endif

                <fieldset id="attendance-fieldset" class="border-0 p-0 m-0" @disabled($locked)>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col" style="width:56px;">#</th>
                                    <th scope="col">{{ __('Student') }}</th>
                                    <th scope="col">{{ __('Section') }}</th>
                                    <th scope="col">{{ __('Roll') }}</th>
                                    <th scope="col" style="min-width:160px;">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($sheet as $row)
                                    @php($enrollment = $row['enrollment'])
                                    @php($student = $enrollment->student)
                                    <tr>
                                        <td class="text-muted-2">{{ $loop->iteration }}</td>
                                        <td>
                                            <div class="cell-person">
                                                <span class="avatar avatar-sm">
                                                    @if ($student?->photoUrl())
                                                        <img src="{{ $student->photoUrl() }}" alt="">
                                                    @else
                                                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($student?->first_name ?? '', 0, 1).\Illuminate\Support\Str::substr($student?->last_name ?? '', 0, 1)) }}
                                                    @endif
                                                </span>
                                                <span>
                                                    <span class="name">{{ $student?->full_name ?? '—' }}</span>
                                                    <span class="email">
                                                        {{ $student?->student_id }}
                                                        @if ($row['attendance'])
                                                            · {{ __('Marked') }} {{ $row['attendance']->updated_at?->format('d M, H:i') }}
                                                            · {{ $row['attendance']->sourceLabel() }}
                                                            @if ($row['attendance']->isLocked())
                                                                · <span class="badge-soft is-warning"><i class="bi bi-lock"></i>{{ __('Corrected') }}</span>
                                                            @endif
                                                        @endif
                                                    </span>
                                                </span>
                                            </div>
                                        </td>
                                        <td>{{ $enrollment->section?->name ?? '—' }}</td>
                                        <td>{{ $enrollment->roll_no ?: '—' }}</td>
                                        <td>
                                            <label class="visually-hidden" for="status-{{ $student?->id }}">{{ __('Attendance status for :name', ['name' => $student?->full_name]) }}</label>
                                            <select id="status-{{ $student?->id }}" name="statuses[{{ $student?->id }}]" class="form-select form-select-sm" data-attendance-status data-original="{{ $rowStatus[$student?->id] }}" @if ($row['attendance']?->isLocked()) data-locked="1" @endif>
                                                @foreach ($statusLabels as $value => $label)
                                                    <option value="{{ $value }}" @selected($rowStatus[$student?->id] === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="attendance-savebar">
                        <div class="attendance-savebar__hint">
                            <span class="attendance-savebar__icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
                            <p>
                                @if ($canMark)
                                    {{ __('Saving again updates the same records — never duplicates. A corrected record stays locked against QR and device overwrites.') }}
                                @else
                                    {{ __('You can review attendance but not change it.') }}
                                @endif
                            </p>
                        </div>
                        @if ($canMark)
                            <div class="attendance-savebar__actions">
                                <div class="attendance-savebar__reason">
                                    <label for="correction-reason">{{ __('Correction reason') }}</label>
                                    <input id="correction-reason" name="correction_reason" class="attendance-savebar__input @error('correction_reason') is-invalid @enderror" maxlength="255" value="{{ old('correction_reason') }}"
                                           data-submitted="{{ $needsReasonOnEdit ? 1 : 0 }}"
                                           placeholder="{{ $needsReasonOnEdit ? __('Required — this day has been submitted') : __('Needed only to change a corrected record') }}">
                                    @error('correction_reason')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn btn-primary attendance-savebar__submit">
                                    <i class="bi bi-check2-circle"></i>{{ __('Save attendance') }}
                                </button>
                            </div>
                        @endif
                    </div>
                </fieldset>
            </form>

            @if ($canSubmit)
                <form method="POST" action="{{ route('attendance.submissions.store') }}" class="attendance-submitbar">
                    @csrf
                    @if ($panel ?? false)
                        <input type="hidden" name="workspace" value="teacher">
                        <input type="hidden" name="panel" value="attendance">
                    @endif
                    <input type="hidden" name="academic_session_id" value="{{ $session->id }}">
                    <input type="hidden" name="class_id" value="{{ $class->id }}">
                    <input type="hidden" name="section_id" value="{{ $section?->id }}">
                    <input type="hidden" name="attendance_date" value="{{ $date->toDateString() }}">
                    @if ($holiday)
                        <input type="hidden" name="override_holiday" value="1">
                    @endif
                    <div class="attendance-submitbar__hint">
                        <span class="attendance-savebar__icon" aria-hidden="true"><i class="bi bi-send-check"></i></span>
                        <p>
                            {{ $submission === null
                                ? __('Once every student is marked, hand the day over. Saving after that needs a reason and puts the day back in the queue.')
                                : ($submission->isAwaitingReview()
                                    ? __('This day is already in the review queue. Saving again updates the numbers the office sees.')
                                    : ($submission->status === \App\Models\AttendanceSubmission::STATUS_APPROVED
                                        ? __('This day is approved. Change a marked student with a correction reason to send it back for another decision.')
                                        : __('Resubmitting sends the corrected day back to the office for another decision.'))) }}
                        </p>
                    </div>
                    <button type="submit" class="btn btn-outline-primary attendance-savebar__submit"
                            @if ($submission?->isAwaitingReview() || $submission?->status === \App\Models\AttendanceSubmission::STATUS_APPROVED) disabled @endif>
                        <i class="bi bi-send-check"></i>
                        {{ $submission === null ? __('Submit for review') : __('Resubmit for review') }}
                    </button>
                </form>
            @endif
        </section>
    @endif
