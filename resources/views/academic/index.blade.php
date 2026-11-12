@extends('layouts.app')

@section('title', __('Academic setup'))

@section('content')
    @php($activeTab = request('tab', 'sessions'))

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-mortarboard"></i>{{ __('School setup') }}</span>
            <h1 class="page-title">{{ __('Academic setup') }}</h1>
            <p class="page-subtitle">{{ __('Define sessions, classes, sections, subjects, curriculum and holidays for this school.') }}</p>
        </div>
        @if ($currentSession)
            <div class="page-actions">
                <span class="badge-soft is-success"><i class="bi bi-calendar-check"></i>{{ __('Current session') }}: {{ $currentSession->name }}</span>
            </div>
        @endif
    </div>

    <ul class="nav nav-pills app-tabs mb-3" role="tablist">
        @foreach ([
            'sessions' => __('Sessions'),
            'classes' => __('Classes & sections'),
            'subjects' => __('Subjects'),
            'curriculum' => __('Curriculum'),
            'holidays' => __('Holidays'),
        ] as $key => $label)
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ $activeTab === $key ? 'active' : '' }}" href="{{ route('academic.index', ['tab' => $key, 'session' => $selectedSession?->id, 'class' => $selectedClassId]) }}">
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>

    @if ($activeTab === 'sessions')
        <section class="ui-card table-card">
            <div class="table-toolbar">
                <div>
                    <h2 class="card-title mb-1">{{ __('Academic sessions') }}</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Only one session can be current. Switching is transactional and audited.') }}</p>
                </div>
                @can('academic.manage')
                    <button type="button" class="btn btn-primary btn-sm" data-form-target="#sessionModal" data-store-action="{{ route('academic.sessions.store') }}">
                        <i class="bi bi-plus-lg"></i>{{ __('Add session') }}
                    </button>
                @endcan
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Session') }}</th>
                            <th>{{ __('Dates') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Students') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sessions as $session)
                            <tr>
                                <td>
                                    <strong>{{ $session->name }}</strong>
                                    @if ($session->is_current)
                                        <span class="badge-soft is-success ms-1">{{ __('Current') }}</span>
                                    @endif
                                </td>
                                <td>{{ $session->start_date->format('d M Y') }} – {{ $session->end_date->format('d M Y') }}</td>
                                <td><span class="badge-soft {{ $session->status === 'active' ? 'is-success' : ($session->status === 'upcoming' ? 'is-info' : '') }}">{{ __(ucfirst($session->status)) }}</span></td>
                                <td>{{ $session->enrollments_count }}</td>
                                <td class="text-end">
                                    @can('academic.manage')
                                        <div class="d-inline-flex gap-1">
                                            @unless ($session->is_current)
                                                <form method="POST" action="{{ route('academic.sessions.current', $session) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn btn-sm btn-light-soft" type="submit">{{ __('Make current') }}</button>
                                                </form>
                                            @endunless
                                            <button type="button" class="btn btn-sm btn-light-soft"
                                                data-form-target="#sessionModal"
                                                data-action="{{ route('academic.sessions.update', $session) }}"
                                                data-method="PUT"
                                                data-field-name="{{ $session->name }}"
                                                data-field-start-date="{{ $session->start_date->toDateString() }}"
                                                data-field-end-date="{{ $session->end_date->toDateString() }}"
                                                data-field-status="{{ $session->status }}"
                                                data-field-is-current="{{ $session->is_current ? '1' : '0' }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <form method="POST" action="{{ route('academic.sessions.destroy', $session) }}"
                                                  data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                  data-confirm-delete="1" data-confirm-type="1"
                                                  data-confirm-label="{{ __('Permanently Delete') }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="permanent_delete" value="DELETE">
                                                <button class="btn btn-sm btn-light-soft text-danger" type="submit"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty-state py-4"><p class="mb-0">{{ __('No academic sessions yet.') }}</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($activeTab === 'classes')
        <div class="row g-3">
            <div class="col-lg-7">
                <section class="ui-card table-card h-100">
                    <div class="table-toolbar">
                        <h2 class="card-title mb-0">{{ __('Classes') }}</h2>
                        @can('academic.manage')
                            <button type="button" class="btn btn-primary btn-sm" data-form-target="#classModal" data-store-action="{{ route('academic.classes.store') }}">
                                <i class="bi bi-plus-lg"></i>{{ __('Add class') }}
                            </button>
                        @endcan
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>{{ __('Class') }}</th><th>{{ __('Level') }}</th><th>{{ __('Sections') }}</th><th>{{ __('Students') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                            <tbody>
                                @forelse ($classes as $class)
                                    <tr>
                                        <td><strong>{{ $class->name }}</strong></td>
                                        <td>{{ $class->level }}</td>
                                        <td>{{ $class->sections->pluck('name')->join(', ') ?: '—' }}</td>
                                        <td>{{ $class->enrollments_count }}</td>
                                        <td class="text-end">
                                            @can('academic.manage')
                                                <div class="d-inline-flex gap-1">
                                                    <button type="button" class="btn btn-sm btn-light-soft"
                                                        data-form-target="#classModal"
                                                        data-action="{{ route('academic.classes.update', $class) }}"
                                                        data-method="PUT"
                                                        data-field-name="{{ $class->name }}"
                                                        data-field-level="{{ $class->level }}"
                                                        data-field-is-active="{{ $class->is_active ? '1' : '0' }}"><i class="bi bi-pencil"></i></button>
                                                    <form method="POST" action="{{ route('academic.classes.destroy', $class) }}"
                                                          data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                          data-confirm-delete="1" data-confirm-type="1"
                                                          data-confirm-label="{{ __('Permanently Delete') }}">
                                                        @csrf @method('DELETE')
                                                        <input type="hidden" name="permanent_delete" value="DELETE">
                                                        <button class="btn btn-sm btn-light-soft text-danger" type="submit"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </div>
                                            @endcan
                                        </td>
                                    </tr>
                                    @foreach ($class->sections as $section)
                                        <tr class="table-subrow">
                                            <td class="ps-4"><i class="bi bi-diagram-3 me-1 text-muted-2"></i>{{ __('Section') }} {{ $section->name }}</td>
                                            <td colspan="2">{{ __('Capacity') }}: {{ $section->capacity ?? '—' }}</td>
                                            <td colspan="2" class="text-end">
                                                @can('academic.manage')
                                                    <div class="d-inline-flex gap-1">
                                                        <button type="button" class="btn btn-sm btn-light-soft"
                                                            data-form-target="#sectionModal"
                                                            data-action="{{ route('academic.sections.update', $section) }}"
                                                            data-method="PUT"
                                                            data-field-class-id="{{ $class->id }}"
                                                            data-field-name="{{ $section->name }}"
                                                            data-field-capacity="{{ $section->capacity }}"
                                                            data-field-is-active="{{ $section->is_active ? '1' : '0' }}"><i class="bi bi-pencil"></i></button>
                                                        <form method="POST" action="{{ route('academic.sections.destroy', $section) }}"
                                                              data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                              data-confirm-delete="1" data-confirm-type="1"
                                                              data-confirm-label="{{ __('Permanently Delete') }}">
                                                            @csrf @method('DELETE')
                                                            <input type="hidden" name="permanent_delete" value="DELETE">
                                                            <button class="btn btn-sm btn-light-soft text-danger" type="submit"><i class="bi bi-trash"></i></button>
                                                        </form>
                                                    </div>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                @empty
                                    <tr><td colspan="5"><div class="empty-state py-4"><p class="mb-0">{{ __('No classes yet.') }}</p></div></td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
            <div class="col-lg-5">
                @can('academic.manage')
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('Add a section') }}</h2>
                        <p class="text-muted-2" style="font-size:.8rem;">{{ __('Sections belong to a class and hold the roll numbers for each session.') }}</p>
                        <form method="POST" action="{{ route('academic.sections.store') }}" class="row g-3">
                            @csrf
                            <input type="hidden" name="_modal" value="classes">
                            <div class="col-12">
                                <label class="form-label" for="section-class">{{ __('Class') }}</label>
                                <select id="section-class" name="class_id" class="form-select" required>
                                    <option value="">{{ __('Choose a class') }}</option>
                                    @foreach ($classes as $class)
                                        <option value="{{ $class->id }}" @selected((string) old('class_id') === (string) $class->id)>{{ $class->name }}</option>
                                    @endforeach
                                </select>
                                @error('class_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="section-name">{{ __('Section name') }}</label>
                                <input id="section-name" name="name" class="form-control" value="{{ old('name') }}" placeholder="A" required>
                                @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="section-capacity">{{ __('Capacity') }}</label>
                                <input id="section-capacity" name="capacity" type="number" min="1" max="500" class="form-control" value="{{ old('capacity') }}">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg"></i>{{ __('Save section') }}</button>
                            </div>
                        </form>
                    </section>
                @endcan
            </div>
        </div>
    @endif

    @if ($activeTab === 'subjects')
        <section class="ui-card table-card">
            <div class="table-toolbar">
                <h2 class="card-title mb-0">{{ __('Subjects') }}</h2>
                @can('academic.manage')
                    <button type="button" class="btn btn-primary btn-sm" data-form-target="#subjectModal" data-store-action="{{ route('academic.subjects.store') }}">
                        <i class="bi bi-plus-lg"></i>{{ __('Add subject') }}
                    </button>
                @endcan
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Code') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @forelse ($subjects as $subject)
                            <tr>
                                <td><strong>{{ $subject->name }}</strong></td>
                                <td>{{ $subject->code ?? '—' }}</td>
                                <td><span class="badge-soft">{{ __(ucfirst($subject->type)) }}</span></td>
                                <td><span class="badge-soft {{ $subject->is_active ? 'is-success' : '' }}">{{ $subject->is_active ? __('Active') : __('Inactive') }}</span></td>
                                <td class="text-end">
                                    @can('academic.manage')
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-light-soft"
                                                data-form-target="#subjectModal"
                                                data-action="{{ route('academic.subjects.update', $subject) }}"
                                                data-method="PUT"
                                                data-field-name="{{ $subject->name }}"
                                                data-field-code="{{ $subject->code }}"
                                                data-field-type="{{ $subject->type }}"
                                                data-field-is-active="{{ $subject->is_active ? '1' : '0' }}"><i class="bi bi-pencil"></i></button>
                                            <form method="POST" action="{{ route('academic.subjects.destroy', $subject) }}"
                                                  data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                  data-confirm-delete="1" data-confirm-type="1"
                                                  data-confirm-label="{{ __('Permanently Delete') }}">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="permanent_delete" value="DELETE">
                                                <button class="btn btn-sm btn-light-soft text-danger" type="submit"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty-state py-4"><p class="mb-0">{{ __('No subjects yet.') }}</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($activeTab === 'curriculum')
        <section class="ui-card table-card">
            <div class="table-toolbar">
                <div>
                    <h2 class="card-title mb-1">{{ __('Class curriculum') }}</h2>
                    <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Choose a session and class, then tick the subjects taught and set marks.') }}</p>
                </div>
            </div>
            <form method="GET" action="{{ route('academic.index') }}" class="filter-form p-3 border-bottom">
                <input type="hidden" name="tab" value="curriculum">
                <select name="session" class="form-select" aria-label="{{ __('Session') }}">
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected($selectedSession?->id === $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
                <select name="class" class="form-select" aria-label="{{ __('Class') }}">
                    <option value="">{{ __('Choose a class') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected((int) $selectedClassId === $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-light-soft" type="submit"><i class="bi bi-arrow-repeat"></i>{{ __('Load') }}</button>
            </form>

            @if ($selectedSession && $selectedClassId)
                @php($classCurriculum = $curriculum->get($selectedClassId, collect())->keyBy('subject_id'))
                <form method="POST" action="{{ route('academic.curriculum.update') }}" class="p-3">
                    @csrf
                    <input type="hidden" name="academic_session_id" value="{{ $selectedSession->id }}">
                    <input type="hidden" name="class_id" value="{{ $selectedClassId }}">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>{{ __('Include') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Full marks') }}</th><th>{{ __('Pass marks') }}</th><th>{{ __('Optional') }}</th></tr></thead>
                            <tbody>
                                @foreach ($subjects as $subject)
                                    @php($row = $classCurriculum->get($subject->id))
                                    <tr>
                                        <td><input class="form-check-input" type="checkbox" name="subjects[{{ $subject->id }}][enabled]" value="1" @checked($row)></td>
                                        <td><strong>{{ $subject->name }}</strong> <span class="text-muted-2">{{ $subject->code }}</span></td>
                                        <td><input type="number" step="0.01" min="1" max="1000" class="form-control form-control-sm" name="subjects[{{ $subject->id }}][full_marks]" value="{{ $row?->full_marks ?? 100 }}"></td>
                                        <td><input type="number" step="0.01" min="0" max="1000" class="form-control form-control-sm" name="subjects[{{ $subject->id }}][pass_marks]" value="{{ $row?->pass_marks ?? 33 }}"></td>
                                        <td><input class="form-check-input" type="checkbox" name="subjects[{{ $subject->id }}][is_optional]" value="1" @checked($row?->is_optional)></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i>{{ __('Save curriculum') }}</button>
                    </div>
                </form>
            @else
                <div class="empty-state py-4"><p class="mb-0">{{ __('Select a session and class to edit the curriculum.') }}</p></div>
            @endif
        </section>
    @endif

    @if ($activeTab === 'holidays')
        <section class="ui-card table-card">
            <div class="table-toolbar">
                <h2 class="card-title mb-0">{{ __('Holidays') }}</h2>
                @can('academic.manage')
                    <button type="button" class="btn btn-primary btn-sm" data-form-target="#holidayModal" data-store-action="{{ route('academic.holidays.store') }}">
                        <i class="bi bi-plus-lg"></i>{{ __('Add holiday') }}
                    </button>
                @endcan
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>{{ __('Holiday') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Type') }}</th><th>{{ __('Session') }}</th><th class="text-end">{{ __('Actions') }}</th></tr></thead>
                    <tbody>
                        @forelse ($holidays as $holiday)
                            <tr>
                                <td><strong>{{ $holiday->name }}</strong></td>
                                <td>{{ $holiday->start_date->format('d M Y') }} – {{ $holiday->end_date->format('d M Y') }}</td>
                                <td><span class="badge-soft">{{ __(ucfirst($holiday->type)) }}</span></td>
                                <td>{{ $holiday->academicSession?->name ?? __('All sessions') }}</td>
                                <td class="text-end">
                                    @can('academic.manage')
                                        <div class="d-inline-flex gap-1">
                                            <button type="button" class="btn btn-sm btn-light-soft"
                                                data-form-target="#holidayModal"
                                                data-action="{{ route('academic.holidays.update', $holiday) }}"
                                                data-method="PUT"
                                                data-field-name="{{ $holiday->name }}"
                                                data-field-academic-session-id="{{ $holiday->academic_session_id }}"
                                                data-field-start-date="{{ $holiday->start_date->toDateString() }}"
                                                data-field-end-date="{{ $holiday->end_date->toDateString() }}"
                                                data-field-type="{{ $holiday->type }}"
                                                data-field-description="{{ $holiday->description }}"><i class="bi bi-pencil"></i></button>
                                            <form method="POST" action="{{ route('academic.holidays.destroy', $holiday) }}"
                                                  data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                  data-confirm-delete="1"
                                                  data-confirm-label="{{ __('Permanently Delete') }}">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-light-soft text-danger" type="submit"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><div class="empty-state py-4"><p class="mb-0">{{ __('No holidays recorded.') }}</p></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Modals --}}
    @can('academic.manage')
        <div class="modal fade" id="sessionModal" tabindex="-1" aria-hidden="true" data-modal-key="sessions">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" class="modal-content" data-store-action="{{ route('academic.sessions.store') }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <input type="hidden" name="_modal" value="sessions">
                    <div class="modal-header"><h2 class="modal-title h5">{{ __('Academic session') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                    <div class="modal-body row g-3">
                        <div class="col-12"><label class="form-label" for="session-name">{{ __('Name') }}</label><input id="session-name" name="name" class="form-control" required placeholder="2026"></div>
                        <div class="col-sm-6"><label class="form-label" for="session-start">{{ __('Start date') }}</label><input id="session-start" name="start_date" type="date" class="form-control" required></div>
                        <div class="col-sm-6"><label class="form-label" for="session-end">{{ __('End date') }}</label><input id="session-end" name="end_date" type="date" class="form-control" required></div>
                        <div class="col-sm-6"><label class="form-label" for="session-status">{{ __('Status') }}</label>
                            <select id="session-status" name="status" class="form-select">
                                <option value="upcoming">{{ __('Upcoming') }}</option>
                                <option value="active">{{ __('Active') }}</option>
                                <option value="completed">{{ __('Completed') }}</option>
                            </select>
                        </div>
                        <div class="col-sm-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_current" value="1" id="session-current"><label class="form-check-label" for="session-current">{{ __('Set as current') }}</label></div></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-primary" type="submit">{{ __('Save session') }}</button></div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="classModal" tabindex="-1" aria-hidden="true" data-modal-key="classes">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" class="modal-content" data-store-action="{{ route('academic.classes.store') }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <input type="hidden" name="_modal" value="classes">
                    <div class="modal-header"><h2 class="modal-title h5">{{ __('Class') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                    <div class="modal-body row g-3">
                        <div class="col-sm-6"><label class="form-label" for="class-name">{{ __('Name') }}</label><input id="class-name" name="name" class="form-control" required></div>
                        <div class="col-sm-6"><label class="form-label" for="class-level">{{ __('Level') }}</label><input id="class-level" name="level" type="number" min="0" max="20" class="form-control" value="0" required></div>
                        <div class="col-sm-6 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="class-active" checked><label class="form-check-label" for="class-active">{{ __('Active') }}</label></div></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-primary" type="submit">{{ __('Save class') }}</button></div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="sectionModal" tabindex="-1" aria-hidden="true" data-modal-key="classes">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" class="modal-content" data-store-action="{{ route('academic.sections.store') }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <input type="hidden" name="_modal" value="classes">
                    <div class="modal-header"><h2 class="modal-title h5">{{ __('Section') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                    <div class="modal-body row g-3">
                        <div class="col-12"><label class="form-label" for="modal-section-class">{{ __('Class') }}</label>
                            <select id="modal-section-class" name="class_id" class="form-select" required>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6"><label class="form-label" for="modal-section-name">{{ __('Section name') }}</label><input id="modal-section-name" name="name" class="form-control" required></div>
                        <div class="col-sm-6"><label class="form-label" for="modal-section-capacity">{{ __('Capacity') }}</label><input id="modal-section-capacity" name="capacity" type="number" min="1" max="500" class="form-control"></div>
                        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="modal-section-active" checked><label class="form-check-label" for="modal-section-active">{{ __('Active') }}</label></div></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-primary" type="submit">{{ __('Save section') }}</button></div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="subjectModal" tabindex="-1" aria-hidden="true" data-modal-key="subjects">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" class="modal-content" data-store-action="{{ route('academic.subjects.store') }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <input type="hidden" name="_modal" value="subjects">
                    <div class="modal-header"><h2 class="modal-title h5">{{ __('Subject') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                    <div class="modal-body row g-3">
                        <div class="col-sm-6"><label class="form-label" for="subject-name">{{ __('Name') }}</label><input id="subject-name" name="name" class="form-control" required></div>
                        <div class="col-sm-6"><label class="form-label" for="subject-code">{{ __('Code') }}</label><input id="subject-code" name="code" class="form-control"></div>
                        <div class="col-sm-6"><label class="form-label" for="subject-type">{{ __('Type') }}</label>
                            <select id="subject-type" name="type" class="form-select">
                                <option value="theory">{{ __('Theory') }}</option>
                                <option value="practical">{{ __('Practical') }}</option>
                                <option value="both">{{ __('Both') }}</option>
                            </select>
                        </div>
                        <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="subject-active" checked><label class="form-check-label" for="subject-active">{{ __('Active') }}</label></div></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-primary" type="submit">{{ __('Save subject') }}</button></div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="holidayModal" tabindex="-1" aria-hidden="true" data-modal-key="holidays">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" class="modal-content" data-store-action="{{ route('academic.holidays.store') }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <input type="hidden" name="_modal" value="holidays">
                    <div class="modal-header"><h2 class="modal-title h5">{{ __('Holiday') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                    <div class="modal-body row g-3">
                        <div class="col-12"><label class="form-label" for="holiday-name">{{ __('Name') }}</label><input id="holiday-name" name="name" class="form-control" required></div>
                        <div class="col-12"><label class="form-label" for="holiday-session">{{ __('Session') }}</label>
                            <select id="holiday-session" name="academic_session_id" class="form-select">
                                <option value="">{{ __('All sessions') }}</option>
                                @foreach ($sessions as $session)
                                    <option value="{{ $session->id }}">{{ $session->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6"><label class="form-label" for="holiday-start">{{ __('Start date') }}</label><input id="holiday-start" name="start_date" type="date" class="form-control" required></div>
                        <div class="col-sm-6"><label class="form-label" for="holiday-end">{{ __('End date') }}</label><input id="holiday-end" name="end_date" type="date" class="form-control" required></div>
                        <div class="col-sm-6"><label class="form-label" for="holiday-type">{{ __('Type') }}</label>
                            <select id="holiday-type" name="type" class="form-select">
                                <option value="public">{{ __('Public') }}</option>
                                <option value="school">{{ __('School') }}</option>
                                <option value="exam">{{ __('Exam') }}</option>
                                <option value="vacation">{{ __('Vacation') }}</option>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label" for="holiday-description">{{ __('Description') }}</label><textarea id="holiday-description" name="description" class="form-control" rows="2"></textarea></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light-soft" data-bs-dismiss="modal">{{ __('Cancel') }}</button><button class="btn btn-primary" type="submit">{{ __('Save holiday') }}</button></div>
                </form>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-form-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            const modalElement = document.querySelector(button.dataset.formTarget);
            if (!modalElement) return;
            const form = modalElement.querySelector('form');
            form.reset();
            form.action = button.dataset.action || form.dataset.storeAction;
            const methodField = form.querySelector('input[name="_method"]');
            if (methodField) methodField.value = button.dataset.method || 'POST';
            Object.entries(button.dataset).forEach(function ([key, value]) {
                if (!key.startsWith('field')) return;
                const name = key.slice(5).replace(/[A-Z]/g, function (letter) { return '_' + letter.toLowerCase(); });
                const input = form.elements[name];
                if (!input) return;
                if (input.type === 'checkbox') input.checked = value === '1' || value === 'true';
                else input.value = value;
            });
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        });
    });

    const oldModal = @json(old('_modal'));
    if (oldModal) {
        const modalElement = document.querySelector('[data-modal-key="' + oldModal + '"]');
        if (modalElement) bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
})();
</script>
@endpush
