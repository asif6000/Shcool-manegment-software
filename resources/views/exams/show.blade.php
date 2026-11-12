@extends('layouts.app')

@section('title', $exam->name)

@section('content')
    @php
        $paperClassIds = $papers->pluck('class_id')->unique();
        $studentCount = $paperClassIds->sum(fn ($id) => $studentsByClass[$id] ?? 0);
        $statusClass = $exam->isPublished() ? 'is-success' : 'is-warning';
        $grouped = $papers->groupBy('class_id');
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-journal-check"></i>{{ __('Examination') }}</span>
            <h1 class="page-title mb-1">{{ $exam->name }}</h1>
            <p class="page-subtitle mb-0">
                {{ $exam->academicSession?->name ?? '—' }}
                · {{ __(ucfirst(str_replace('_', ' ', $exam->exam_type))) }}
                <span class="badge-soft {{ $statusClass }} ms-1">{{ __(ucfirst($exam->status)) }}</span>
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('exams.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Exams') }}</a>
            @can('marks.enter')
                @if (! $exam->isPublished())
                    <a href="{{ route('exams.marks.index', $exam) }}" class="btn btn-light-soft"><i class="bi bi-pencil-square"></i>{{ __('Enter marks') }}</a>
                @endif
            @endcan
            @can('results.view')
                <a href="{{ route('exams.results.index', $exam) }}" class="btn btn-primary"><i class="bi bi-table"></i>{{ __('Results') }}</a>
            @endcan
        </div>
    </div>

    @if ($exam->isPublished())
        <div class="alert alert-success">
            <i class="bi bi-lock-fill"></i>
            <div>
                <strong>{{ __('Results are published.') }}</strong>
                {{ __('Marks and papers are frozen. Published on :date.', ['date' => $exam->published_at?->format('d M Y, h:i A') ?? '—']) }}
            </div>
        </div>
    @endif

    <section class="stats-grid mb-3" aria-label="{{ __('Exam summary') }}">
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Subject papers') }}</p>
                    <p class="stat-value">{{ number_format($papers->count()) }}</p>
                </div>
                <span class="stat-icon" aria-hidden="true"><i class="bi bi-journal-text"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Across :count classes', ['count' => $paperClassIds->count()]) }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Students') }}</p>
                    <p class="stat-value">{{ number_format($studentCount) }}</p>
                </div>
                <span class="stat-icon is-teal" aria-hidden="true"><i class="bi bi-people"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Active enrollments in paper classes') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Marks recorded') }}</p>
                    <p class="stat-value">{{ number_format($entered->sum()) }}</p>
                </div>
                <span class="stat-icon is-amber" aria-hidden="true"><i class="bi bi-pencil-square"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Student papers with marks or absence') }}</span></div>
        </article>
        <article class="stat-card">
            <div class="stat-top">
                <div>
                    <p class="stat-label">{{ __('Published results') }}</p>
                    <p class="stat-value">{{ number_format($resultsCount) }}</p>
                </div>
                <span class="stat-icon is-purple" aria-hidden="true"><i class="bi bi-patch-check"></i></span>
            </div>
            <div class="stat-foot"><span>{{ __('Snapshots available to students') }}</span></div>
        </article>
    </section>

    @if ($papers->isEmpty())
        <section class="ui-card mb-3">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-list-check"></i></div>
                <h3>{{ __('No subject papers yet') }}</h3>
                <p>{{ __('Add a paper for each class and subject, then enter marks.') }}</p>
            </div>
        </section>
    @else
        <section class="ui-card table-card mb-3">
            <div class="table-toolbar">
                <div>
                    <h2 class="card-title mb-1">{{ __('Subject papers') }}</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Each paper is scheduled per class. Marks must be complete before results can be published.') }}</p>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Class') }}</th>
                            <th scope="col">{{ __('Subject') }}</th>
                            <th scope="col">{{ __('Schedule') }}</th>
                            <th scope="col" class="text-end">{{ __('Full / pass') }}</th>
                            <th scope="col" class="text-end">{{ __('Entered') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($grouped as $classId => $classPapers)
                            @php $class = $classPapers->first()?->schoolClass; @endphp
                            <tr class="table-section-row">
                                <td colspan="6">
                                    <span class="fw-700">{{ $class?->name ?? __('Class') }}</span>
                                    <span class="text-muted-2 ms-2" style="font-size:.78rem;">
                                        {{ trans_choice(':count active student|:count active students', $studentsByClass[$classId] ?? 0, ['count' => $studentsByClass[$classId] ?? 0]) }}
                                    </span>
                                </td>
                            </tr>
                            @foreach ($classPapers as $paper)
                                @php
                                    $recorded = (int) ($entered[$paper->id] ?? 0);
                                    $enrolled = (int) ($studentsByClass[$paper->class_id] ?? 0);
                                @endphp
                                <tr>
                                    <td>{{ $class?->name ?? '—' }}</td>
                                    <td>
                                        <div class="fw-600">{{ $paper->subject?->name ?? '—' }}</div>
                                        <div class="text-muted-2" style="font-size:.76rem;">{{ $paper->subject?->code }}</div>
                                    </td>
                                    <td>
                                        @if ($paper->exam_date)
                                            <div>{{ $paper->exam_date->format('d M Y') }}</div>
                                            <div class="text-muted-2" style="font-size:.76rem;">
                                                {{ $paper->start_time ? \Illuminate\Support\Carbon::parse($paper->start_time)->format('h:i A') : '' }}
                                                @if ($paper->start_time && $paper->end_time) – @endif
                                                {{ $paper->end_time ? \Illuminate\Support\Carbon::parse($paper->end_time)->format('h:i A') : '' }}
                                            </div>
                                        @else
                                            <span class="text-muted-2">{{ __('Not scheduled') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format((float) $paper->full_marks, 2) }} / {{ number_format((float) $paper->pass_marks, 2) }}</td>
                                    <td class="text-end">
                                        <span class="badge-soft {{ $recorded >= $enrolled && $enrolled > 0 ? 'is-success' : ($recorded > 0 ? 'is-warning' : '') }}">
                                            {{ $recorded }} / {{ $enrolled }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="row-actions">
                                            @can('marks.enter')
                                                @if (! $exam->isPublished())
                                                    <a href="{{ route('exams.marks.index', ['exam' => $exam, 'class_id' => $paper->class_id, 'paper_id' => $paper->id]) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Enter marks') }}" aria-label="{{ __('Enter marks for :subject', ['subject' => $paper->subject?->name]) }}">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                @endif
                                            @endcan
                                            @can('exams.manage')
                                                @if (! $exam->isPublished() && $recorded === 0)
                                                    <form method="POST" action="{{ route('exams.papers.destroy', [$exam, $paper]) }}"
                                                          data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                          data-confirm-delete="1"
                                                          data-confirm-label="{{ __('Permanently Delete') }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="icon-action is-danger" data-bs-toggle="tooltip" title="{{ __('Remove paper') }}" aria-label="{{ __('Remove :subject paper', ['subject' => $paper->subject?->name]) }}">
                                                            <i class="bi bi-trash3"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <div class="row g-3">
        <div class="col-lg-8">
            @can('exams.manage')
                @if (! $exam->isPublished())
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('Add a subject paper') }}</h2>
                        <p class="text-muted-2" style="font-size:.8rem;">{{ __('A class can have one paper per subject. Full marks default to 100 and pass marks to 33.') }}</p>

                        @if ($classes->isEmpty() || $subjects->isEmpty())
                            <div class="empty-state py-4">
                                <div class="empty-icon"><i class="bi bi-sliders"></i></div>
                                <h3>{{ __('Set up classes and subjects first') }}</h3>
                                <p>{{ __('Papers need at least one class and one subject in academic setup.') }}</p>
                                <a href="{{ route('academic.index') }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i>{{ __('Open academic setup') }}</a>
                            </div>
                        @else
                            <form method="POST" action="{{ route('exams.papers.store', $exam) }}" class="row g-3 mt-1">
                                @csrf
                                <div class="col-md-6">
                                    <label class="form-label" for="paper-class">{{ __('Class') }}</label>
                                    <select id="paper-class" name="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
                                        <option value="">{{ __('Choose a class') }}</option>
                                        @foreach ($classes as $class)
                                            <option value="{{ $class->id }}" @selected((int) old('class_id') === $class->id)>
                                                {{ $class->name }} ({{ $studentsByClass[$class->id] ?? 0 }} {{ __('students') }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="paper-subject">{{ __('Subject') }}</label>
                                    <select id="paper-subject" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                                        <option value="">{{ __('Choose a subject') }}</option>
                                        @foreach ($subjects as $subject)
                                            <option value="{{ $subject->id }}" @selected((int) old('subject_id') === $subject->id)>{{ $subject->name }}@if ($subject->code) · {{ $subject->code }}@endif</option>
                                        @endforeach
                                    </select>
                                    @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="paper-date">{{ __('Exam date') }} <span class="text-muted-2 fw-normal">({{ __('optional') }})</span></label>
                                    <input id="paper-date" type="date" name="exam_date" class="form-control @error('exam_date') is-invalid @enderror"
                                        value="{{ old('exam_date') }}" min="{{ $exam->start_date?->format('Y-m-d') }}" max="{{ $exam->end_date?->format('Y-m-d') }}">
                                    @error('exam_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="paper-start">{{ __('Start time') }}</label>
                                    <input id="paper-start" type="time" name="start_time" class="form-control @error('start_time') is-invalid @enderror" value="{{ old('start_time') }}">
                                    @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="paper-end">{{ __('End time') }}</label>
                                    <input id="paper-end" type="time" name="end_time" class="form-control @error('end_time') is-invalid @enderror" value="{{ old('end_time') }}">
                                    @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="paper-full">{{ __('Full marks') }}</label>
                                    <input id="paper-full" type="number" step="0.01" min="1" name="full_marks" class="form-control @error('full_marks') is-invalid @enderror" value="{{ old('full_marks', 100) }}" required>
                                    @error('full_marks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="paper-pass">{{ __('Pass marks') }}</label>
                                    <input id="paper-pass" type="number" step="0.01" min="0" name="pass_marks" class="form-control @error('pass_marks') is-invalid @enderror" value="{{ old('pass_marks', 33) }}" required>
                                    @error('pass_marks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i>{{ __('Add paper') }}</button>
                                </div>
                            </form>
                        @endif
                    </section>
                @endif
            @endcan
        </div>

        <div class="col-lg-4">
            <section class="ui-card p-lg mb-3">
                <h2 class="card-title">{{ __('Exam details') }}</h2>
                <dl class="detail-list mt-2 mb-0">
                    <div><dt>{{ __('Session') }}</dt><dd>{{ $exam->academicSession?->name ?? '—' }}</dd></div>
                    <div><dt>{{ __('Dates') }}</dt><dd>{{ $exam->start_date?->format('d M Y') }} – {{ $exam->end_date?->format('d M Y') }}</dd></div>
                    <div><dt>{{ __('Created by') }}</dt><dd>{{ $exam->creator?->name ?? '—' }}</dd></div>
                    <div><dt>{{ __('Published') }}</dt><dd>{{ $exam->published_at?->format('d M Y, h:i A') ?? __('Not yet') }}</dd></div>
                </dl>
                @if ($exam->description)
                    <p class="text-muted-2 mt-3 mb-0" style="font-size:.82rem;">{{ $exam->description }}</p>
                @endif
            </section>

            @can('exams.manage')
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Manage exam') }}</h2>
                    <div class="d-grid gap-2 mt-3">
                        @if (! $exam->isPublished())
                            <a href="{{ route('exams.edit', $exam) }}" class="btn btn-light-soft"><i class="bi bi-pencil"></i>{{ __('Edit exam details') }}</a>
                            <form method="POST" action="{{ route('exams.destroy', $exam) }}"
                                  data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                  data-confirm-delete="1" data-confirm-type="1"
                                  data-confirm-label="{{ __('Permanently Delete') }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="permanent_delete" value="DELETE">
                                <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-trash3"></i>{{ __('Delete exam') }}</button>
                            </form>
                        @else
                            <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Unpublish results from the results page to edit or delete this exam.') }}</p>
                        @endif
                    </div>
                </section>
            @endcan
        </div>
    </div>
@endsection
