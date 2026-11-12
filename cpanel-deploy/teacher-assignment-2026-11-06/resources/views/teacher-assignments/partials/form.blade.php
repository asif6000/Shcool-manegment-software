@php
    $assignment = $assignment ?? null;
    $selectedTeacher = old('teacher_id', $assignment?->teacher_id);
    $selectedClass = old('class_id', $assignment?->class_id);
    $selectedSection = old('section_id', $assignment?->section_id);
    $selectedSubject = old('subject_id', $assignment?->subject_id);
    $selectedStatus = old('status', $assignment?->status ?? \App\Models\TeacherAssignment::STATUS_ACTIVE);
    $assignedOn = old('assigned_on', $assignment?->assigned_on?->format('Y-m-d') ?? now()->format('Y-m-d'));
@endphp

<form method="POST" action="{{ $action }}" data-teacher-assignment-form>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <input type="hidden" name="academic_session_id" value="{{ old('academic_session_id', $session?->id) }}">

    <div class="mb-3">
        <label class="form-label" for="assignment-teacher">{{ __('Teacher') }}<span class="required">*</span></label>
        <select id="assignment-teacher" name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror" required>
            <option value="">{{ __('Choose a teacher') }}</option>
            @foreach ($teachers as $teacher)
                <option value="{{ $teacher->id }}" @selected((int) $selectedTeacher === $teacher->id)>
                    {{ $teacher->full_name }}@if ($teacher->employee_code) · {{ $teacher->employee_code }}@endif
                </option>
            @endforeach
        </select>
        @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label" for="assignment-class">{{ __('Class') }}<span class="required">*</span></label>
        <select id="assignment-class" name="class_id" class="form-select @error('class_id') is-invalid @enderror"
                required data-assignment-class>
            <option value="">{{ __('Choose a class') }}</option>
            @foreach ($classes as $class)
                <option value="{{ $class->id }}" @selected((int) $selectedClass === $class->id)>{{ $class->name }}</option>
            @endforeach
        </select>
        @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label" for="assignment-section">{{ __('Section') }}<span class="required">*</span></label>
        <select id="assignment-section" name="section_id" class="form-select @error('section_id') is-invalid @enderror"
                required data-assignment-section>
            <option value="">{{ __('Choose a section') }}</option>
            @foreach ($sections as $section)
                <option value="{{ $section->id }}" data-class="{{ $section->class_id }}"
                        @selected((int) $selectedSection === $section->id)>
                    {{ __('Section') }} {{ $section->name }}
                </option>
            @endforeach
        </select>
        @error('section_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label" for="assignment-subject">{{ __('Subject') }}<span class="required">*</span></label>
        <select id="assignment-subject" name="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
            <option value="">{{ __('Choose a subject') }}</option>
            @foreach ($subjects as $subject)
                <option value="{{ $subject->id }}" @selected((int) $selectedSubject === $subject->id)>{{ $subject->name }}</option>
            @endforeach
        </select>
        @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label class="form-label" for="assignment-date">{{ __('Assignment Date') }}<span class="required">*</span></label>
        <input id="assignment-date" type="date" name="assigned_on"
               class="form-control @error('assigned_on') is-invalid @enderror"
               value="{{ $assignedOn }}" required>
        @error('assigned_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    @if ($assignment !== null)
        <div class="mb-3">
            <label class="form-label" for="assignment-status">{{ __('Status') }}<span class="required">*</span></label>
            <select id="assignment-status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected($selectedStatus === $status)>
                        {{ $status === \App\Models\TeacherAssignment::STATUS_ACTIVE ? __('Active') : __('Inactive') }}
                    </option>
                @endforeach
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">{{ __('Only active assignments appear in the teacher portal.') }}</div>
        </div>
    @endif

    <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-check2-circle"></i>{{ $submitLabel }}
    </button>
</form>

@once
    @push('scripts')
        <script>
            (function () {
                var form = document.querySelector('[data-teacher-assignment-form]');

                if (!form) {
                    return;
                }

                var classField = form.querySelector('[data-assignment-class]');
                var sectionField = form.querySelector('[data-assignment-section]');

                if (!classField || !sectionField) {
                    return;
                }

                var options = Array.prototype.slice.call(sectionField.querySelectorAll('option[data-class]'));

                function applyFilter() {
                    var classId = classField.value;
                    var selectionIsValid = false;

                    options.forEach(function (option) {
                        var matches = option.getAttribute('data-class') === classId;
                        option.hidden = !matches;
                        option.disabled = !matches;

                        if (matches && option.selected) {
                            selectionIsValid = true;
                        }
                    });

                    if (!selectionIsValid) {
                        var first = options.find(function (option) {
                            return option.getAttribute('data-class') === classId;
                        });

                        sectionField.value = first ? first.value : '';
                    }
                }

                classField.addEventListener('change', applyFilter);
                applyFilter();
            })();
        </script>
    @endpush
@endonce
