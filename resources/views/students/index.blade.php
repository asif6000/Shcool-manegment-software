@extends('layouts.app')

@section('title', __('Students'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-mortarboard"></i>{{ __('Student information') }}</span>
            <h1 class="page-title">{{ __('Students') }}</h1>
            <p class="page-subtitle">{{ __('Every student record belongs to this school. Search by name, ID, admission number or phone.') }}</p>
        </div>
        <div class="page-actions">
            @can('students.export')
                <a href="{{ route('students.export', $filters) }}" class="btn btn-light-soft">
                    <i class="bi bi-filetype-csv"></i>{{ __('Export CSV') }}
                </a>
            @endcan
            @can('admissions.view')
                <a href="{{ route('admissions.index') }}" class="btn btn-light-soft">
                    <i class="bi bi-clipboard-check"></i>{{ __('Admissions') }}
                </a>
            @endcan
            @can('students.create')
                <a href="{{ route('student_import.index') }}" class="btn btn-light-soft">
                    <i class="bi bi-upload"></i>{{ __('Import students') }}
                </a>
            @endcan
            <a href="{{ route('students.idcards.sheet', array_filter($filters)) }}" class="btn btn-light-soft">
                <i class="bi bi-person-vcard"></i>{{ __('Identity cards') }}
            </a>
            @can('students.create')
                <a href="{{ route('students.create') }}" class="btn btn-primary">
                    <i class="bi bi-person-plus"></i>{{ __('Admit student') }}
                </a>
            @endcan
        </div>
    </div>

    <section class="ui-card table-card">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('students.index') }}" class="filter-form" role="search">
                <div class="filter-search">
                    <i class="bi bi-search"></i>
                    <label class="visually-hidden" for="student-search">{{ __('Search students') }}</label>
                    <input id="student-search" type="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search name, ID or phone') }}">
                </div>
                <label class="visually-hidden" for="filter-class">{{ __('Class') }}</label>
                <select id="filter-class" name="class_id" class="form-select" data-auto-submit>
                    <option value="">{{ __('All classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected((string) ($filters['class_id'] ?? '') === (string) $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                @if ($sections->isNotEmpty())
                    <label class="visually-hidden" for="filter-section">{{ __('Section') }}</label>
                    <select id="filter-section" name="section_id" class="form-select" data-auto-submit>
                        <option value="">{{ __('All sections') }}</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" @selected((string) ($filters['section_id'] ?? '') === (string) $section->id)>{{ __('Section') }} {{ $section->name }}</option>
                        @endforeach
                    </select>
                @endif
                <label class="visually-hidden" for="filter-session">{{ __('Session') }}</label>
                <select id="filter-session" name="session_id" class="form-select" data-auto-submit>
                    <option value="">{{ __('All sessions') }}</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected((string) ($filters['session_id'] ?? '') === (string) $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="filter-status">{{ __('Status') }}</label>
                <select id="filter-status" name="status" class="form-select" data-auto-submit>
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach (['active' => __('Active'), 'inactive' => __('Inactive'), 'graduated' => __('Graduated'), 'transferred' => __('Transferred'), 'archived' => __('Archived')] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-sm btn-light-soft"><i class="bi bi-funnel"></i>{{ __('Filter') }}</button>
                @if (array_filter($filters))
                    <a href="{{ route('students.index') }}" class="btn btn-sm btn-link">{{ __('Clear') }}</a>
                @endif
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">{{ trans_choice(':count student|:count students', $students->total(), ['count' => $students->total()]) }}</span>
        </div>

        @if ($students->isEmpty())
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-mortarboard"></i></div>
                <h3>{{ __('No students found') }}</h3>
                <p>{{ __('Adjust the filters or admit the first student for this school.') }}</p>
                @can('students.create')
                    <a href="{{ route('students.create') }}" class="btn btn-primary mt-3"><i class="bi bi-person-plus"></i>{{ __('Admit student') }}</a>
                @endcan
            </div>
        @else
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Student') }}</th>
                            <th scope="col">{{ __('Class') }}</th>
                            <th scope="col">{{ __('Guardian') }}</th>
                            <th scope="col">{{ __('Status') }}</th>
                            <th scope="col">{{ __('Admitted') }}</th>
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            @php($enrollment = $student->enrollments->first())
                            @php($guardian = $student->primaryGuardian())
                            <tr>
                                <td>
                                    <div class="cell-person">
                                        <span class="avatar">
                                            @if ($student->photoUrl())
                                                <img src="{{ $student->photoUrl() }}" alt="">
                                            @else
                                                {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($student->first_name, 0, 1).\Illuminate\Support\Str::substr($student->last_name ?? '', 0, 1)) }}
                                            @endif
                                        </span>
                                        <span>
                                            <a href="{{ route('students.show', $student) }}" class="name text-decoration-none">{{ $student->full_name }}</a>
                                            <span class="email">{{ $student->student_id }} · {{ $student->admission_no }}</span>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    @if ($enrollment)
                                        <div>{{ $enrollment->schoolClass?->name ?? '—' }}</div>
                                        <div class="text-muted-2" style="font-size:.78rem;">
                                            {{ $enrollment->section?->name ? __('Section').' '.$enrollment->section->name : __('No section') }}
                                            @if ($enrollment->roll_no) · {{ __('Roll') }} {{ $enrollment->roll_no }} @endif
                                        </div>
                                    @else
                                        <span class="text-muted-2">{{ __('Not enrolled') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($guardian)
                                        <div>{{ $guardian->name }}</div>
                                        <div class="text-muted-2" style="font-size:.78rem;">{{ $guardian->phone }}</div>
                                    @else
                                        <span class="text-muted-2">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge-soft {{ $student->status === 'active' ? 'is-success' : ($student->status === 'inactive' ? 'is-warning' : '') }}">
                                        {{ __(ucfirst($student->status)) }}
                                    </span>
                                </td>
                                <td>{{ $student->admission_date?->format('d M Y') }}</td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('students.show', $student) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('View profile') }}" aria-label="{{ __('View :name', ['name' => $student->full_name]) }}">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @can('students.update')
                                            <a href="{{ route('students.edit', $student) }}" class="icon-action" data-bs-toggle="tooltip" title="{{ __('Edit') }}" aria-label="{{ __('Edit :name', ['name' => $student->full_name]) }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        @endcan
                                        @can('students.delete')
                                            @if ($student->status !== \App\Models\Student::STATUS_ARCHIVED)
                                                <form method="POST" action="{{ route('students.archive', $student) }}"
                                                    data-confirm="{{ __('Archive this student profile? The profile leaves the active register and portal access is disabled, but attendance, marks, results and audit history are kept.') }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="archive_confirmed" value="1">
                                                    <button type="submit" class="icon-action"
                                                        data-bs-toggle="tooltip" title="{{ __('Archive profile') }}"
                                                        aria-label="{{ __('Archive :name', ['name' => $student->full_name]) }}">
                                                        <i class="bi bi-archive"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if ($student->user_id)
                                                <form method="POST" action="{{ route('students.login.destroy', $student) }}"
                                                    data-confirm="{{ __('Permanently delete this student login account? The mobile number and password stop working and active sessions end. Academic history is kept.') }}"
                                                    data-confirm-delete="1"
                                                    data-confirm-type="1"
                                                    data-confirm-label="{{ __('Delete Login Account') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="permanent_delete" value="DELETE">
                                                    <button type="submit" class="icon-action is-danger"
                                                        data-bs-toggle="tooltip" title="{{ __('Delete login account') }}"
                                                        aria-label="{{ __('Delete login for :name', ['name' => $student->full_name]) }}">
                                                        <i class="bi bi-person-x"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('students.destroy', $student) }}"
                                                data-confirm="{{ __('Student: :name (ID: :id)', ['name' => $student->full_name, 'id' => $student->student_id]) }}&#10;{{ __('Are you sure you want to permanently delete this student?') }}&#10;{{ __('The profile, photo, attendance, fees, payments, marks, results, documents and the login account are removed from the database. This action cannot be undone.') }}"
                                                data-confirm-delete="1"
                                                data-confirm-type="1"
                                                data-confirm-label="{{ __('Permanently Delete') }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="permanent_delete" value="DELETE">
                                                <button type="submit" class="icon-action is-danger"
                                                    data-bs-toggle="tooltip" title="{{ __('Permanently Delete') }}"
                                                    aria-label="{{ __('Permanently delete :name', ['name' => $student->full_name]) }}">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span class="text-muted-2">{{ __('Showing :from–:to of :total', ['from' => $students->firstItem(), 'to' => $students->lastItem(), 'total' => $students->total()]) }}</span>
                {{ $students->links() }}
            </div>
        @endif
    </section>
@endsection
