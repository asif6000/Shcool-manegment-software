@extends('layouts.app')

@section('title', __('Teacher Assignment'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-user-round-check"></i>{{ __('Teacher Management') }}</span>
            <h1 class="page-title">{{ __('Teacher Assignment') }}</h1>
            <p class="page-subtitle">{{ __('Give a teacher the classes, sections and subjects they may teach this session. Only these combinations appear in the teacher portal.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('staff.index', ['type' => 'teachers']) }}" class="btn btn-light-soft">
                <i class="bi bi-people"></i>{{ __('Teachers') }}
            </a>
        </div>
    </div>

    @if (! $ready)
        <div class="alert alert-warning" role="alert">
            <strong>{{ __('Setup is incomplete.') }}</strong>
            {{ __('The assignment table has not been created on this server yet. Run the prepared SQL, then reload this page.') }}
        </div>
    @endif

    @if ($session === null)
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-calendar-x"></i></div>
                <h3>{{ __('No academic session yet') }}</h3>
                <p>{{ __('Assignments belong to one academic session. Create a session and make it current first.') }}</p>
                <a href="{{ route('academic.index') }}" class="btn btn-primary mt-3">
                    <i class="bi bi-plus-lg"></i>{{ __('Open academic setup') }}
                </a>
            </div>
        </section>
    @else
        <div class="row g-3">
            <div class="col-xl-4">
                <section class="ui-card p-lg">
                    <h2 class="card-title">{{ __('Assign Subject') }}</h2>
                    <p class="text-muted-2" style="font-size:.78rem;">
                        {{ __('Session') }}: <strong>{{ $session->name }}</strong>@if ($session->is_current) · {{ __('Current') }}@endif
                    </p>

                    @if (! $ready)
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('The form opens once the assignment table exists.') }}</p>
                    @elseif ($teachers->isEmpty() || $classes->isEmpty() || $subjects->isEmpty())
                        <div class="empty-state py-4">
                            <div class="empty-icon"><i class="bi bi-diagram-3"></i></div>
                            <h3>{{ __('Add the basics first') }}</h3>
                            <p>{{ __('Assignments need at least one teacher, class, section and subject.') }}</p>
                        </div>
                    @else
                        @include('teacher-assignments.partials.form', [
                            'action' => route('teacher_assignments.store'),
                            'method' => 'POST',
                            'submitLabel' => __('Assign Subject'),
                        ])
                    @endif
                </section>
            </div>

            <div class="col-xl-8">
                <section class="ui-card table-card">
                    <div class="table-toolbar">
                        <form method="GET" action="{{ route('teacher_assignments.index') }}" class="filter-form">
                            <label class="visually-hidden" for="filter-session">{{ __('Academic session') }}</label>
                            <select id="filter-session" name="session" class="form-select" data-auto-submit>
                                @foreach ($sessions as $option)
                                    <option value="{{ $option->id }}" @selected($session->id === $option->id)>
                                        {{ $option->name }}@if ($option->is_current) · {{ __('Current') }}@endif
                                    </option>
                                @endforeach
                            </select>

                            <label class="visually-hidden" for="filter-search">{{ __('Search teacher') }}</label>
                            <input id="filter-search" type="search" name="search" class="form-control"
                                   value="{{ $filters['search'] }}" placeholder="{{ __('Search teacher') }}">

                            <label class="visually-hidden" for="filter-teacher">{{ __('Teacher') }}</label>
                            <select id="filter-teacher" name="teacher" class="form-select" data-auto-submit>
                                <option value="">{{ __('All teachers') }}</option>
                                @foreach ($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" @selected($filters['teacher'] === $teacher->id)>
                                        {{ $teacher->full_name }}
                                    </option>
                                @endforeach
                            </select>

                            <label class="visually-hidden" for="filter-class">{{ __('Class') }}</label>
                            <select id="filter-class" name="class" class="form-select" data-auto-submit>
                                <option value="">{{ __('All classes') }}</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}" @selected($filters['class'] === $class->id)>
                                        {{ $class->name }}
                                    </option>
                                @endforeach
                            </select>

                            <label class="visually-hidden" for="filter-subject">{{ __('Subject') }}</label>
                            <select id="filter-subject" name="subject" class="form-select" data-auto-submit>
                                <option value="">{{ __('All subjects') }}</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->id }}" @selected($filters['subject'] === $subject->id)>
                                        {{ $subject->name }}
                                    </option>
                                @endforeach
                            </select>

                            <label class="visually-hidden" for="filter-status">{{ __('Status') }}</label>
                            <select id="filter-status" name="status" class="form-select" data-auto-submit>
                                <option value="">{{ __('All statuses') }}</option>
                                <option value="active" @selected($filters['status'] === 'active')>{{ __('Active') }}</option>
                                <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('Inactive') }}</option>
                            </select>

                            <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                            @if (array_filter($filters))
                                <a href="{{ route('teacher_assignments.index', ['session' => $session->id]) }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                            @endif
                        </form>
                        <span class="text-muted-2" style="font-size:.78rem;">
                            {{ trans_choice(':count assignment|:count assignments', $assignments->total(), ['count' => $assignments->total()]) }}
                        </span>
                    </div>

                    @if ($assignments->isEmpty())
                        <div class="empty-state">
                            <div class="empty-icon"><i class="bi bi-clipboard-x"></i></div>
                            <h3>{{ __('No assignments here yet') }}</h3>
                            <p>{{ __('Use the form to give a teacher their first class, section and subject for :session.', ['session' => $session->name]) }}</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Teacher') }}</th>
                                        <th>{{ __('Class') }}</th>
                                        <th>{{ __('Section') }}</th>
                                        <th>{{ __('Subject') }}</th>
                                        <th>{{ __('Assignment Date') }}</th>
                                        <th>{{ __('Status') }}</th>
                                        <th class="text-end">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($assignments as $assignment)
                                        <tr>
                                            <td style="min-width:0;">
                                                <span class="fw-semibold">{{ $assignment->teacher?->full_name ?? '—' }}</span>
                                                @if ($assignment->teacher?->employee_code)
                                                    <div class="text-muted-2" style="font-size:.74rem;">{{ $assignment->teacher->employee_code }}</div>
                                                @endif
                                            </td>
                                            <td>{{ $assignment->schoolClass?->name ?? '—' }}</td>
                                            <td>{{ __('Section') }} {{ $assignment->section?->name ?? '—' }}</td>
                                            <td>{{ $assignment->subject?->name ?? '—' }}</td>
                                            <td style="white-space:nowrap;">{{ $assignment->assigned_on?->format('d M Y') ?? '—' }}</td>
                                            <td>
                                                @if ($assignment->status === \App\Models\TeacherAssignment::STATUS_ACTIVE)
                                                    <span class="badge-soft is-success"><span class="dot"></span>{{ __('Active') }}</span>
                                                @else
                                                    <span class="badge-soft is-warning"><span class="dot"></span>{{ __('Inactive') }}</span>
                                                @endif
                                            </td>
                                            <td class="text-end" style="white-space:nowrap;">
                                                <a href="{{ route('teacher_assignments.edit', $assignment) }}"
                                                   class="btn btn-sm btn-light-soft" title="{{ __('Edit assignment') }}">
                                                    <i class="bi bi-pencil"></i>{{ __('Edit') }}
                                                </a>
                                                <form method="POST" action="{{ route('teacher_assignments.destroy', $assignment) }}"
                                                      class="d-inline"
                                                      data-confirm="{{ __('Remove this assignment? The teacher will no longer be able to create new work for this class, section and subject. Records already saved are kept.') }}"
                                                      data-confirm-delete="1"
                                                      data-confirm-label="{{ __('Remove Assignment') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-light-soft text-danger" title="{{ __('Remove assignment') }}">
                                                        <i class="bi bi-trash"></i>{{ __('Remove Assignment') }}
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="p-3">
                            {{ $assignments->links() }}
                        </div>
                    @endif
                </section>
            </div>
        </div>
    @endif
@endsection
