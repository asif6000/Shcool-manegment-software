@php
    $student = $student ?? null;
    $guardian = $student?->primaryGuardian();
    $withEnrollment = $withEnrollment ?? false;
    $isCreate = $student === null;
@endphp

<div class="ui-card form-card">
    <div class="form-section">
        <div class="form-section-head">
            <h2>{{ __('Student details') }}</h2>
            <p>{{ __('The student ID and admission number are generated automatically for this school.') }}</p>
        </div>

        <div class="form-grid">
            <div>
                <label for="first_name" class="form-label">{{ __('First name') }}<span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $student?->first_name) }}" class="form-control @error('first_name') is-invalid @enderror" required autofocus autocomplete="off">
                @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="last_name" class="form-label">{{ __('Last name') }}</label>
                <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $student?->last_name) }}" class="form-control @error('last_name') is-invalid @enderror" autocomplete="off">
                @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="gender" class="form-label">{{ __('Gender') }}<span class="required">*</span></label>
                <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                    <option value="">{{ __('Select gender') }}</option>
                    @foreach (['male' => __('Male'), 'female' => __('Female'), 'other' => __('Other')] as $value => $label)
                        <option value="{{ $value }}" @selected(old('gender', $student?->gender) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="date_of_birth" class="form-label">{{ __('Date of birth') }}<span class="required">*</span></label>
                <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $student?->date_of_birth?->toDateString()) }}" class="form-control @error('date_of_birth') is-invalid @enderror" required>
                @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="blood_group" class="form-label">{{ __('Blood group') }}</label>
                <select id="blood_group" name="blood_group" class="form-select @error('blood_group') is-invalid @enderror">
                    <option value="">{{ __('Unknown') }}</option>
                    @foreach ($bloodGroups as $group)
                        <option value="{{ $group }}" @selected(old('blood_group', $student?->blood_group) === $group)>{{ $group }}</option>
                    @endforeach
                </select>
                @error('blood_group')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="religion" class="form-label">{{ __('Religion') }}</label>
                <input type="text" id="religion" name="religion" value="{{ old('religion', $student?->religion) }}" class="form-control @error('religion') is-invalid @enderror">
                @error('religion')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="nationality" class="form-label">{{ __('Nationality') }}</label>
                <input type="text" id="nationality" name="nationality" value="{{ old('nationality', $student?->nationality ?? 'Bangladeshi') }}" class="form-control @error('nationality') is-invalid @enderror">
                @error('nationality')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="status" class="form-label">{{ __('Status') }}<span class="required">*</span></label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    @foreach (['active' => __('Active'), 'inactive' => __('Inactive'), 'graduated' => __('Graduated'), 'transferred' => __('Transferred'), 'archived' => __('Archived')] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $student?->status ?? 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="admission_date" class="form-label">{{ __('Admission date') }}<span class="required">*</span></label>
                <input type="date" id="admission_date" name="admission_date" value="{{ old('admission_date', $student?->admission_date?->toDateString() ?? now()->toDateString()) }}" class="form-control @error('admission_date') is-invalid @enderror" required>
                @error('admission_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="phone" class="form-label">{{ __('Mobile number') }}<span class="required">*</span></label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $student?->phone) }}" class="form-control @error('phone') is-invalid @enderror" placeholder="01XXXXXXXXX" required autocomplete="off">
                <div class="form-hint">{{ __('The student signs in with this number. No email address is needed.') }}</div>
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="password" class="form-label">{{ $isCreate ? __('Password') : __('New password') }}@if($isCreate)<span class="required">*</span>@endif</label>
                <input type="password" id="password" name="password" value="" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" @required($isCreate)>
                @unless($isCreate)
                    <div class="form-hint">{{ __('Leave blank to keep the current password.') }}</div>
                @endunless
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}@if($isCreate)<span class="required">*</span>@endif</label>
                <input type="password" id="password_confirmation" name="password_confirmation" value="" class="form-control" autocomplete="new-password" @required($isCreate)>
            </div>

            <div>
                <label for="email" class="form-label">{{ __('Email address') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email', $student?->email) }}" class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="span-2">
                <label for="present_address" class="form-label">{{ __('Present address') }}@if($isCreate)<span class="required">*</span>@endif</label>
                <textarea id="present_address" name="present_address" rows="2" class="form-control @error('present_address') is-invalid @enderror" @required($isCreate)>{{ old('present_address', $student?->present_address) }}</textarea>
                @error('present_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="span-2">
                <label for="permanent_address" class="form-label">{{ __('Permanent address') }}</label>
                <textarea id="permanent_address" name="permanent_address" rows="2" class="form-control @error('permanent_address') is-invalid @enderror">{{ old('permanent_address', $student?->permanent_address) }}</textarea>
                @error('permanent_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="span-2">
                <label for="photo" class="form-label">{{ __('Photo') }}</label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control @error('photo') is-invalid @enderror">
                <div class="form-hint">{{ __('JPEG, PNG or WebP up to 2 MB. Photos are served only to signed-in users.') }}</div>
                @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if ($student?->photo_path)
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="remove_photo">
                        <label class="form-check-label" for="remove_photo">{{ __('Remove the current photo') }}</label>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="form-section">
        <div class="form-section-head">
            <h2>{{ __('Guardian') }}</h2>
            <p>{{ __('A primary guardian is saved with the student. Existing guardians are matched by phone.') }}</p>
        </div>

        <div class="form-grid">
            <div>
                <label for="guardian_name" class="form-label">{{ __('Guardian name') }}@if($isCreate)<span class="required">*</span>@endif</label>
                <input type="text" id="guardian_name" name="guardian_name" value="{{ old('guardian_name', $guardian?->name) }}" class="form-control @error('guardian_name') is-invalid @enderror" @required($isCreate)>
                @error('guardian_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="guardian_relation" class="form-label">{{ __('Relation') }}</label>
                <select id="guardian_relation" name="guardian_relation" class="form-select @error('guardian_relation') is-invalid @enderror">
                    @foreach (['father' => __('Father'), 'mother' => __('Mother'), 'guardian' => __('Guardian'), 'other' => __('Other')] as $value => $label)
                        <option value="{{ $value }}" @selected(old('guardian_relation', $guardian?->relation ?? 'father') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('guardian_relation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="guardian_phone" class="form-label">{{ __('Guardian mobile') }}@if($isCreate)<span class="required">*</span>@endif</label>
                <input type="tel" id="guardian_phone" name="guardian_phone" value="{{ old('guardian_phone', $guardian?->phone) }}" class="form-control @error('guardian_phone') is-invalid @enderror" placeholder="01XXXXXXXXX" @required($isCreate)>
                @error('guardian_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="guardian_email" class="form-label">{{ __('Guardian email') }}</label>
                <input type="email" id="guardian_email" name="guardian_email" value="{{ old('guardian_email', $guardian?->email) }}" class="form-control @error('guardian_email') is-invalid @enderror">
                @error('guardian_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="guardian_occupation" class="form-label">{{ __('Occupation') }}</label>
                <input type="text" id="guardian_occupation" name="guardian_occupation" value="{{ old('guardian_occupation', $guardian?->occupation) }}" class="form-control @error('guardian_occupation') is-invalid @enderror">
                @error('guardian_occupation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="guardian_address" class="form-label">{{ __('Guardian address') }}</label>
                <input type="text" id="guardian_address" name="guardian_address" value="{{ old('guardian_address', $guardian?->address) }}" class="form-control @error('guardian_address') is-invalid @enderror">
                @error('guardian_address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    @if ($withEnrollment)
        <div class="form-section">
            <div class="form-section-head">
                <h2>{{ __('First enrollment') }}</h2>
                <p>{{ $isCreate ? __('The student is placed in this class section for the chosen session.') : __('Optional. You can also enroll the student later from their profile.') }}</p>
            </div>

            <div class="form-grid">
                <div>
                    <label for="academic_session_id" class="form-label">{{ __('Session') }}@if($isCreate)<span class="required">*</span>@endif</label>
                    <select id="academic_session_id" name="academic_session_id" class="form-select @error('academic_session_id') is-invalid @enderror" @required($isCreate)>
                        @unless($isCreate)
                            <option value="">{{ __('Enroll later') }}</option>
                        @endunless
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}" @selected((string) old('academic_session_id', $session->is_current ? $session->id : '') === (string) $session->id)>
                                {{ $session->name }}{{ $session->is_current ? ' · '.__('Current') : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('academic_session_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="class_id" class="form-label">{{ __('Class') }}@if($isCreate)<span class="required">*</span>@endif</label>
                    <select id="class_id" name="class_id" class="form-select @error('class_id') is-invalid @enderror" data-class-select @required($isCreate)>
                        <option value="">{{ __('Choose a class') }}</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected((string) old('class_id') === (string) $class->id)>{{ $class->name }}</option>
                        @endforeach
                    </select>
                    @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="section_id" class="form-label">{{ __('Section') }}@if($isCreate)<span class="required">*</span>@endif</label>
                    <select id="section_id" name="section_id" class="form-select @error('section_id') is-invalid @enderror" data-section-select @required($isCreate)>
                        <option value="">{{ __('Choose a section') }}</option>
                        @foreach ($classes as $class)
                            @foreach ($class->sections as $section)
                                <option value="{{ $section->id }}" data-class-id="{{ $class->id }}" @selected((string) old('section_id') === (string) $section->id)>
                                    {{ $class->name }} · {{ $section->name }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                    @error('section_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="roll_no" class="form-label">{{ __('Roll number') }}@if($isCreate)<span class="required">*</span>@endif</label>
                    <input type="number" id="roll_no" name="roll_no" min="1" max="9999" value="{{ old('roll_no', $student?->currentEnrollment()?->roll_no) }}" class="form-control @error('roll_no') is-invalid @enderror" placeholder="{{ $isCreate ? '' : __('Automatic') }}" @required($isCreate)>
                    @unless($isCreate)
                        <div class="form-hint">{{ __('Leave blank to assign the next roll number in the section.') }}</div>
                    @endunless
                    @error('roll_no')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    @endif

    <div class="form-actions">
        <a href="{{ $student ? route('students.show', $student) : route('students.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-person-check"></i>{{ $student ? __('Save changes') : __('Admit student') }}
        </button>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const classSelect = document.querySelector('[data-class-select]');
    const sectionSelect = document.querySelector('[data-section-select]');

    if (!classSelect || !sectionSelect) return;

    function filterSections() {
        const classId = classSelect.value;
        Array.from(sectionSelect.options).forEach(function (option) {
            if (!option.dataset.classId) return;
            const matches = option.dataset.classId === classId;
            option.hidden = !matches;
            option.disabled = !matches;
            if (!matches && option.selected) sectionSelect.value = '';
        });
    }

    classSelect.addEventListener('change', function () {
        sectionSelect.value = '';
        filterSections();
    });

    filterSections();
})();
</script>
@endpush
