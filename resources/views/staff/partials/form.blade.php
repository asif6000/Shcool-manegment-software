@php
    $person = $person ?? null;
    $isTeacher = $type === 'teachers';
    $selectedClassIds = array_map('strval', (array) old(
        'class_ids',
        $isTeacher ? ($person?->classes?->pluck('id')->all() ?? []) : [],
    ));
@endphp

<div class="ui-card form-card">
    <div class="form-section">
        <div class="form-section-head">
            <h2>{{ $isTeacher ? __('Teacher details') : __('Staff details') }}</h2>
            <p>{{ __('The employee code is generated automatically for this school when left blank.') }}</p>
        </div>

        <div class="form-grid">
            <div>
                <label for="employee_code" class="form-label">{{ __('Employee code') }}</label>
                <input type="text" id="employee_code" name="employee_code" value="{{ old('employee_code', $person?->employee_code) }}" class="form-control @error('employee_code') is-invalid @enderror" placeholder="{{ __('Automatic') }}" autocomplete="off">
                @error('employee_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="first_name" class="form-label">{{ __('First name') }}<span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $person?->first_name) }}" class="form-control @error('first_name') is-invalid @enderror" required autofocus autocomplete="off">
                @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="last_name" class="form-label">{{ __('Last name') }}</label>
                <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $person?->last_name) }}" class="form-control @error('last_name') is-invalid @enderror" autocomplete="off">
                @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="gender" class="form-label">{{ __('Gender') }}<span class="required">*</span></label>
                <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                    <option value="">{{ __('Select gender') }}</option>
                    @foreach (['male' => __('Male'), 'female' => __('Female'), 'other' => __('Other')] as $value => $label)
                        <option value="{{ $value }}" @selected(old('gender', $person?->gender) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="date_of_birth" class="form-label">{{ __('Date of birth') }}</label>
                <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $person?->date_of_birth?->toDateString()) }}" class="form-control @error('date_of_birth') is-invalid @enderror">
                @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="phone" class="form-label">{{ __('Phone') }}<span class="required">*</span></label>
                <input type="tel" id="phone" name="phone" value="{{ old('phone', $person?->phone) }}" class="form-control @error('phone') is-invalid @enderror" placeholder="01XXXXXXXXX" required>
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @if ($isTeacher && $person === null)
                <div>
                    <label for="password" class="form-label">{{ __('Portal password') }}</label>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="new-password">
                    <div class="form-hint">{{ __('Leave blank to add the teacher without a login.') }}</div>
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password">
                </div>
            @endif
        </div>
    </div>

    <div class="form-section">
        <div class="form-section-head">
            <h2>{{ __('Employment') }}</h2>
            <p>{{ __('Designation, employment type and joining details.') }}</p>
        </div>

        <div class="form-grid">
            <div>
                <label for="designation" class="form-label">{{ __('Designation') }}<span class="required">*</span></label>
                <input type="text" id="designation" name="designation" value="{{ old('designation', $person?->designation ?? ($isTeacher ? __('Teacher') : '')) }}" class="form-control @error('designation') is-invalid @enderror" required>
                @error('designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            @if ($isTeacher)
                <div>
                    <label for="specialization" class="form-label">{{ __('Specialization') }}</label>
                    <input type="text" id="specialization" name="specialization" value="{{ old('specialization', $person?->specialization) }}" class="form-control @error('specialization') is-invalid @enderror" placeholder="{{ __('e.g. Mathematics') }}">
                    @error('specialization')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div>
                    <label for="qualification" class="form-label">{{ __('Qualification') }}</label>
                    <input type="text" id="qualification" name="qualification" value="{{ old('qualification', $person?->qualification) }}" class="form-control @error('qualification') is-invalid @enderror" placeholder="{{ __('e.g. B.Ed, M.Sc') }}">
                    @error('qualification')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @else
                <div>
                    <label for="department" class="form-label">{{ __('Department') }}</label>
                    <input type="text" id="department" name="department" value="{{ old('department', $person?->department) }}" class="form-control @error('department') is-invalid @enderror" placeholder="{{ __('e.g. Accounts, Transport') }}">
                    @error('department')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endif

            @if ($isTeacher)
                <div class="span-2">
                    <label for="class_ids" class="form-label">{{ __('Classes') }}</label>
                    <select id="class_ids" name="class_ids[]" class="form-select @error('class_ids') is-invalid @enderror" multiple size="6">
                        @forelse ($classes ?? [] as $class)
                            <option value="{{ $class->id }}" @selected(in_array((string) $class->id, $selectedClassIds, true))>{{ $class->name }}</option>
                        @empty
                            <option value="" disabled>{{ __('No class exists yet.') }}</option>
                        @endforelse
                    </select>
                    <div class="form-hint">{{ __('Hold Ctrl (Cmd on a Mac) to choose more than one class. Leave none selected for no class.') }}</div>
                    @error('class_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @error('class_ids.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
            @endif

            <div>
                <label for="joining_date" class="form-label">{{ __('Joining date') }}<span class="required">*</span></label>
                <input type="date" id="joining_date" name="joining_date" value="{{ old('joining_date', $person?->joining_date?->toDateString() ?? now()->toDateString()) }}" class="form-control @error('joining_date') is-invalid @enderror" required>
                @error('joining_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="employment_type" class="form-label">{{ __('Employment type') }}<span class="required">*</span></label>
                <select id="employment_type" name="employment_type" class="form-select @error('employment_type') is-invalid @enderror" required>
                    @foreach (['full_time' => __('Full-time'), 'part_time' => __('Part-time'), 'contract' => __('Contract'), 'visiting' => __('Visiting')] as $value => $label)
                        <option value="{{ $value }}" @selected(old('employment_type', $person?->employment_type ?? 'full_time') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('employment_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div>
                <label for="status" class="form-label">{{ __('Status') }}<span class="required">*</span></label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    @foreach (['active' => __('Active'), 'inactive' => __('Inactive'), 'resigned' => __('Resigned')] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $person?->status ?? 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="span-2">
                <label for="photo" class="form-label">{{ __('Photo') }}</label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" class="form-control @error('photo') is-invalid @enderror">
                <div class="form-hint">{{ __('JPEG, PNG or WebP up to 2 MB. Photos are served only to signed-in users.') }}</div>
                @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a href="{{ $person ? route('staff.'.$type.'.show', $person) : route('staff.index', ['type' => $type]) }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2-circle"></i>{{ $person ? __('Save changes') : ($isTeacher ? __('Add teacher') : __('Add staff member')) }}
        </button>
    </div>
</div>
