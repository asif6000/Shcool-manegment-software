@extends('layouts.app')

@section('title', __('Class routine'))

@section('content')
    @php
        $canManage = auth()->user()->hasRole('school_admin') && auth()->user()->can('timetable.manage');
        $query = ['session' => $session?->id, 'class' => $classId, 'section' => $sectionId];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-calendar-week"></i>{{ __('Academic') }}</span>
            <h1 class="page-title">{{ __('Class routine') }}</h1>
            <p class="page-subtitle">{{ __('Weekly periods for one section. Students see this grid in their portal as soon as it is saved.') }}</p>
        </div>
        <div class="page-actions">
            @if (auth()->user()->hasRole('teacher') && ! auth()->user()->hasAnyRole(['school_admin', 'principal', 'super_admin']) && auth()->user()->can('homework.view'))
                <a href="{{ route('teacher.homework.index') }}" class="btn btn-light-soft">
                    <i class="bi bi-pencil-square"></i>{{ __('Homework') }}
                </a>
            @endif
            <a href="{{ route('public.routines.index') }}" target="_blank" rel="noopener" class="btn btn-light-soft">
                <i class="bi bi-window"></i>{{ __('Website routine page') }}
            </a>
        </div>
    </div>

    <section class="ui-card table-card mb-3">
        <div class="table-toolbar">
            <form method="GET" action="{{ route('timetable.index') }}" class="filter-form">
                <label class="visually-hidden" for="pick-session">{{ __('Academic session') }}</label>
                <select id="pick-session" name="session" class="form-select" data-auto-submit>
                    @foreach ($sessions as $option)
                        <option value="{{ $option->id }}" @selected($session?->id === $option->id)>
                            {{ $option->name }}@if ($option->is_current) · {{ __('Current') }}@endif
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="pick-class">{{ __('Class') }}</label>
                <select id="pick-class" name="class" class="form-select" data-auto-submit>
                    @foreach ($classes as $option)
                        <option value="{{ $option->id }}" @selected((int) $classId === $option->id)>
                            {{ $option->name }}
                        </option>
                    @endforeach
                </select>
                <label class="visually-hidden" for="pick-section">{{ __('Section') }}</label>
                <select id="pick-section" name="section" class="form-select" data-auto-submit>
                    @forelse ($sections as $option)
                        <option value="{{ $option->id }}" @selected((int) $sectionId === $option->id)>
                            {{ __('Section') }} {{ $option->name }}
                        </option>
                    @empty
                        <option value="">{{ __('No section') }}</option>
                    @endforelse
                </select>
            </form>
            <span class="text-muted-2" style="font-size:.78rem;">
                {{ trans_choice(':count period|:count periods', $totalSlots, ['count' => $totalSlots]) }}
            </span>
        </div>
    </section>

    @if ($session === null || $classes->isEmpty())
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-journal-x"></i></div>
                <h3>{{ __('Create a session and classes first') }}</h3>
                <p>{{ __('The routine needs an academic session, a class and at least one section before periods can be placed.') }}</p>
                <a href="{{ route('academic.index') }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i>{{ __('Open academic setup') }}</a>
            </div>
        </section>
    @elseif ($sections->isEmpty())
        <section class="ui-card">
            <div class="empty-state">
                <div class="empty-icon"><i class="bi bi-diagram-3"></i></div>
                <h3>{{ __('This class has no section yet') }}</h3>
                <p>{{ __('Periods are stored per section, so add a section to :class first.', ['class' => $classes->first()?->name ?? __('the class')]) }}</p>
                <a href="{{ route('academic.index') }}" class="btn btn-primary mt-3"><i class="bi bi-plus-lg"></i>{{ __('Open academic setup') }}</a>
            </div>
        </section>
    @else
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="timetable-week">
                    @foreach ($days as $index => $day)
                        @php($rows = $slots->get($index, collect()))
                        <div class="timetable-day">
                            <div class="timetable-day-head">
                                <span class="day-name">{{ __($day) }}</span>
                                <span class="day-date">{{ $rows->count() }}</span>
                            </div>
                            <div class="timetable-day-body">
                                @forelse ($rows as $slot)
                                    <div class="portal-slot">
                                        <span class="portal-slot-time">
                                            {{ \App\Models\ClassRoutine::timeLabel($slot->start_time) }}
                                            <span class="d-block text-muted-2" style="font-weight:500;">
                                                {{ \App\Models\ClassRoutine::timeLabel($slot->end_time) }}
                                            </span>
                                        </span>
                                        <span style="min-width:0;">
                                            <span class="portal-slot-title">{{ $slot->subject?->name ?? '—' }}</span>
                                            <span class="portal-slot-meta">{{ $slot->teacher?->full_name ?? __('No teacher') }}</span>
                                        </span>
                                        <span class="d-flex align-items-center gap-1">
                                            @if ($slot->room)
                                                <span class="portal-room" title="{{ __('Room') }}">{{ $slot->room }}</span>
                                            @endif
                                            @if ($canManage)
                                                <button type="button" class="icon-action" data-bs-toggle="collapse"
                                                        data-bs-target="#edit-slot-{{ $slot->id }}" aria-expanded="false"
                                                        title="{{ __('Edit') }}" aria-label="{{ __('Edit period') }}">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <form method="POST" action="{{ route('timetable.destroy', $slot) }}"
                                                      data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                                                      data-confirm-delete="1"
                                                      data-confirm-label="{{ __('Permanently Delete') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    @foreach ($query as $key => $value)
                                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                                    @endforeach
                                                    <button type="submit" class="icon-action is-danger"
                                                            title="{{ __('Delete') }}" aria-label="{{ __('Delete period') }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </span>
                                    </div>

                                    @if ($canManage)
                                        <div class="collapse" id="edit-slot-{{ $slot->id }}">
                                            <form method="POST" action="{{ route('timetable.update', $slot) }}" class="ui-card p-3 mt-2">
                                                @csrf
                                                @method('PUT')
                                                @foreach ($query as $key => $value)
                                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                                @endforeach
                                                <input type="hidden" name="academic_session_id" value="{{ $slot->academic_session_id }}">
                                                <input type="hidden" name="class_id" value="{{ $slot->class_id }}">
                                                <input type="hidden" name="section_id" value="{{ $slot->section_id }}">
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <label class="form-label" for="edit-subject-{{ $slot->id }}">{{ __('Subject') }}</label>
                                                        <select id="edit-subject-{{ $slot->id }}" name="subject_id" class="form-select form-select-sm">
                                                            @foreach ($subjects as $option)
                                                                <option value="{{ $option->id }}" @selected($slot->subject_id === $option->id)>
                                                                    {{ $option->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label" for="edit-day-{{ $slot->id }}">{{ __('Day') }}</label>
                                                        <select id="edit-day-{{ $slot->id }}" name="day_of_week" class="form-select form-select-sm">
                                                            @foreach ($days as $dayIndex => $dayLabel)
                                                                <option value="{{ $dayIndex }}" @selected((int) $slot->day_of_week === $dayIndex)>
                                                                    {{ __($dayLabel) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label" for="edit-start-{{ $slot->id }}">{{ __('Starts') }}</label>
                                                        <input id="edit-start-{{ $slot->id }}" type="time" name="start_time" class="form-control form-control-sm"
                                                               value="{{ \Illuminate\Support\Str::substr((string) $slot->start_time, 0, 5) }}">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label" for="edit-end-{{ $slot->id }}">{{ __('Ends') }}</label>
                                                        <input id="edit-end-{{ $slot->id }}" type="time" name="end_time" class="form-control form-control-sm"
                                                               value="{{ \Illuminate\Support\Str::substr((string) $slot->end_time, 0, 5) }}">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label" for="edit-room-{{ $slot->id }}">{{ __('Room') }}</label>
                                                        <input id="edit-room-{{ $slot->id }}" type="text" name="room" maxlength="40"
                                                               class="form-control form-control-sm" value="{{ $slot->room }}">
                                                    </div>
                                                    <div class="col-12">
                                                        <label class="form-label" for="edit-teacher-{{ $slot->id }}">{{ __('Teacher') }}</label>
                                                        <select id="edit-teacher-{{ $slot->id }}" name="teacher_id" class="form-select form-select-sm">
                                                            <option value="">{{ __('No teacher') }}</option>
                                                            @foreach ($teachers as $option)
                                                                <option value="{{ $option->id }}" @selected($slot->teacher_id === $option->id)>
                                                                    {{ $option->first_name }}{{ $option->last_name ? ' '.$option->last_name : '' }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                @error('start_time')
                                                    <div class="text-danger mt-2" style="font-size:.75rem;">{{ $message }}</div>
                                                @enderror
                                                <button type="submit" class="btn btn-sm btn-primary w-100 mt-3">{{ __('Save period') }}</button>
                                            </form>
                                        </div>
                                    @endif
                                @empty
                                    <div class="timetable-empty-day">{{ __('No period') }}</div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="col-xl-4">
                @if ($canManage)
                    <section class="ui-card p-lg">
                        <h2 class="card-title">{{ __('Add a period') }}</h2>
                        <p class="text-muted-2" style="font-size:.78rem;">{{ __('Two periods cannot start at the same minute in the same section on the same day.') }}</p>

                        <form method="POST" action="{{ route('timetable.store') }}" class="mt-3">
                            @csrf
                            @foreach ($query as $key => $value)
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endforeach
                            <input type="hidden" name="academic_session_id" value="{{ $session?->id }}">
                            <input type="hidden" name="class_id" value="{{ $classId }}">
                            <input type="hidden" name="section_id" value="{{ $sectionId }}">

                            <div class="mb-3">
                                <label class="form-label" for="new-subject">{{ __('Subject') }}<span class="required">*</span></label>
                                <select id="new-subject" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                                    @foreach ($subjects as $option)
                                        <option value="{{ $option->id }}" @selected((int) old('subject_id') === $option->id)>{{ $option->name }}</option>
                                    @endforeach
                                </select>
                                @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="row g-2">
                                <div class="col-12">
                                    <label class="form-label" for="new-day">{{ __('Day') }}<span class="required">*</span></label>
                                    <select id="new-day" name="day_of_week" class="form-select @error('day_of_week') is-invalid @enderror" required>
                                        @foreach ($days as $dayIndex => $dayLabel)
                                            <option value="{{ $dayIndex }}" @selected((int) old('day_of_week', '0') === $dayIndex)>{{ __($dayLabel) }}</option>
                                        @endforeach
                                    </select>
                                    @error('day_of_week')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="new-start">{{ __('Starts') }}<span class="required">*</span></label>
                                    <input id="new-start" type="time" name="start_time" class="form-control @error('start_time') is-invalid @enderror"
                                           value="{{ old('start_time', '09:00') }}" required>
                                    @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-6">
                                    <label class="form-label" for="new-end">{{ __('Ends') }}<span class="required">*</span></label>
                                    <input id="new-end" type="time" name="end_time" class="form-control @error('end_time') is-invalid @enderror"
                                           value="{{ old('end_time', '09:45') }}" required>
                                    @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="new-teacher">{{ __('Teacher') }}</label>
                                    <select id="new-teacher" name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror">
                                        <option value="">{{ __('No teacher') }}</option>
                                        @foreach ($teachers as $option)
                                            <option value="{{ $option->id }}" @selected((int) old('teacher_id') === $option->id)>
                                                {{ $option->first_name }}{{ $option->last_name ? ' '.$option->last_name : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="new-room">{{ __('Room') }}</label>
                                    <input id="new-room" type="text" name="room" maxlength="40" class="form-control @error('room') is-invalid @enderror"
                                           value="{{ old('room') }}" placeholder="{{ __('Room 3') }}">
                                    @error('room')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mt-4">
                                <i class="bi bi-plus-lg"></i>{{ __('Add period') }}
                            </button>
                        </form>
                    </section>
                @endif

                <section class="ui-card p-lg mt-3">
                    <h2 class="card-title">{{ __('How the week is stored') }}</h2>
                    <ul class="text-muted-2 mb-0 mt-2" style="font-size:.8rem;padding-left:1.1rem;">
                        <li>{{ __('The school week runs Saturday to Friday.') }}</li>
                        <li>{{ __('Periods belong to one section, so parallel sections each keep their own grid.') }}</li>
                        <li>{{ __('Students without a published routine see an empty day in their portal instead of a broken page.') }}</li>
                    </ul>
                </section>

                @if ($canManage)
                    <section class="ui-card p-lg mt-3" aria-label="{{ __('Subject assignment history') }}">
                        <h2 class="card-title">{{ __('Subject assignment history') }}</h2>
                        <p class="text-muted-2" style="font-size:.78rem;">{{ __('The most recent class, section and subject assignments made by the office.') }}</p>
                        <ul class="text-muted-2 mb-0 mt-2" style="font-size:.8rem;padding-left:1.1rem;">
                            @forelse ($assignmentHistory as $entry)
                                <li class="mb-2">
                                    {{ $entry->description }}
                                    <span class="d-block" style="font-size:.72rem;">
                                        {{ $entry->user?->name ?? __('System') }} · {{ $entry->created_at?->diffForHumans() }}
                                    </span>
                                </li>
                            @empty
                                <li>{{ __('No subject assignments have been recorded yet.') }}</li>
                            @endforelse
                        </ul>
                    </section>
                @endif
            </div>
        </div>
    @endif
@endsection
