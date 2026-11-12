@php($admission = $admission ?? null)

<div class="ui-card form-card">
    <div class="form-section">
        <div class="form-section-head">
            <h2>{{ __('Applicant') }}</h2>
            <p>{{ __('The application number is generated automatically. Approval creates the student record.') }}</p>
        </div>

        <div class="form-grid">
            <div>
                <label for="first_name" class="form-label">{{ __('First name') }}<span class="required">*</span></label>
                <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $admission?->first_name) }}" class="form-control @error('first_name') is-invalid @enderror" required autofocus>
                @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="last_name" class="form-label">{{ __('Last name') }}</label>
                <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $admission?->last_name) }}" class="form-control @error('last_name') is-invalid @enderror">
                @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="gender" class="form-label">{{ __('Gender') }}<span class="required">*</span></label>
                <select id="gender" name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                    <option value="">{{ __('Select gender') }}</option>
                    @foreach (['male' => __('Male'), 'female' => __('Female'), 'other' => __('Other')] as $value => $label)
                        <option value="{{ $value }}" @selected(old('gender', $admission?->gender) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="date_of_birth" class="form-label">{{ __('Date of birth') }}<span class="required">*</span></label>
                <input type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth', $admission?->date_of_birth?->toDateString()) }}" class="form-control @error('date_of_birth') is-invalid @enderror" required>
                @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="academic_session_id" class="form-label">{{ __('Session') }}<span class="required">*</span></label>
                <select id="academic_session_id" name="academic_session_id" class="form-select @error('academic_session_id') is-invalid @enderror" required>
                    <option value="">{{ __('Choose a session') }}</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected((string) old('academic_session_id', $admission?->academic_session_id ?? ($session->is_current ? $session->id : '')) === (string) $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
                @error('academic_session_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="applied_class_id" class="form-label">{{ __('Class applied for') }}<span class="required">*</span></label>
                <select id="applied_class_id" name="applied_class_id" class="form-select @error('applied_class_id') is-invalid @enderror" required>
                    <option value="">{{ __('Choose a class') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected((string) old('applied_class_id', $admission?->applied_class_id) === (string) $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
                @error('applied_class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="span-2">
                <label for="previous_school" class="form-label">{{ __('Previous school') }}</label>
                <input type="text" id="previous_school" name="previous_school" value="{{ old('previous_school', $admission?->previous_school) }}" class="form-control @error('previous_school') is-invalid @enderror">
                @error('previous_school')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="form-section">
        <div class="form-section-head">
            <h2>{{ __('Guardian contact') }}</h2>
            <p>{{ __('The guardian is created and linked when the application is approved.') }}</p>
        </div>
        <div class="form-grid">
            <div>
                <label for="guardian_name" class="form-label">{{ __('Guardian name') }}<span class="required">*</span></label>
                <input type="text" id="guardian_name" name="guardian_name" value="{{ old('guardian_name', $admission?->guardian_name) }}" class="form-control @error('guardian_name') is-invalid @enderror" required>
                @error('guardian_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="guardian_phone" class="form-label">{{ __('Guardian phone') }}<span class="required">*</span></label>
                <input type="tel" id="guardian_phone" name="guardian_phone" value="{{ old('guardian_phone', $admission?->guardian_phone) }}" class="form-control @error('guardian_phone') is-invalid @enderror" placeholder="01XXXXXXXXX" required>
                @error('guardian_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="guardian_email" class="form-label">{{ __('Guardian email') }}</label>
                <input type="email" id="guardian_email" name="guardian_email" value="{{ old('guardian_email', $admission?->guardian_email) }}" class="form-control @error('guardian_email') is-invalid @enderror">
                @error('guardian_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="status" class="form-label">{{ __('Application status') }}</label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror">
                    <option value="pending" @selected(old('status', $admission?->status ?? 'pending') === 'pending')>{{ __('Pending') }}</option>
                    <option value="waitlisted" @selected(old('status', $admission?->status) === 'waitlisted')>{{ __('Waitlisted') }}</option>
                </select>
                @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="span-2">
                <label for="address" class="form-label">{{ __('Address') }}</label>
                <textarea id="address" name="address" rows="2" class="form-control @error('address') is-invalid @enderror">{{ old('address', $admission?->address) }}</textarea>
                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="span-2">
                <label for="notes" class="form-label">{{ __('Notes') }}</label>
                <textarea id="notes" name="notes" rows="2" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $admission?->notes) }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="form-actions">
        <a href="{{ $admission ? route('admissions.show', $admission) : route('admissions.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i>{{ $admission ? __('Save application') : __('Record application') }}</button>
    </div>
</div>
