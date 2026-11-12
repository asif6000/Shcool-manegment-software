@extends('layouts.applicant')

@section('title', __('New admission application'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-pencil-square"></i>{{ __('Admission application') }}</span>
            <h1 class="page-title">{{ __('New application') }}</h1>
            <p class="page-subtitle">{{ __('Fill in the student details carefully. After submitting, you get an application number and can follow the decision here.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('applicant.dashboard') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('My applications') }}</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <section class="ui-card p-lg">
                <form method="POST" action="{{ route('applicant.apply.store') }}" enctype="multipart/form-data" novalidate>
                    @csrf

                    <h2 class="card-title mb-3">{{ __('Choose the class') }}</h2>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="academic_session_id">{{ __('Academic session') }}<span class="required">*</span></label>
                            <select class="form-select @error('academic_session_id') is-invalid @enderror" id="academic_session_id" name="academic_session_id" required>
                                <option value="">{{ __('Choose a session') }}</option>
                                @foreach ($sessions as $session)
                                    <option value="{{ $session->id }}" @selected((int) old('academic_session_id') === $session->id)>
                                        {{ $session->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('academic_session_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="applied_class_id">{{ __('Applying for class') }}<span class="required">*</span></label>
                            <select class="form-select @error('applied_class_id') is-invalid @enderror" id="applied_class_id" name="applied_class_id" required>
                                <option value="">{{ __('Choose a class') }}</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}" @selected((int) old('applied_class_id') === $class->id)>
                                        {{ $class->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('applied_class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="first_name">{{ __('Student name (English)') }}<span class="required">*</span></label>
                            <input class="form-control @error('first_name') is-invalid @enderror" type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" maxlength="80" required>
                            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="last_name">{{ __('Surname') }}</label>
                            <input class="form-control @error('last_name') is-invalid @enderror" type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" maxlength="80">
                            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="gender">{{ __('Gender') }}<span class="required">*</span></label>
                            <select class="form-select @error('gender') is-invalid @enderror" id="gender" name="gender" required>
                                <option value="">{{ __('Choose') }}</option>
                                <option value="male" @selected(old('gender') === 'male')>{{ __('Male') }}</option>
                                <option value="female" @selected(old('gender') === 'female')>{{ __('Female') }}</option>
                                <option value="other" @selected(old('gender') === 'other')>{{ __('Other') }}</option>
                            </select>
                            @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="date_of_birth">{{ __('Date of birth') }}<span class="required">*</span></label>
                            <input class="form-control @error('date_of_birth') is-invalid @enderror" type="date" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}" max="{{ now()->subYears(3)->toDateString() }}" required>
                            @error('date_of_birth')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="previous_school">{{ __('Previous school') }}</label>
                            <input class="form-control @error('previous_school') is-invalid @enderror" type="text" id="previous_school" name="previous_school" value="{{ old('previous_school') }}" maxlength="120">
                            @error('previous_school')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <h2 class="card-title mt-4 mb-3">{{ __('Guardian') }}</h2>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="guardian_name">{{ __('Guardian name') }}<span class="required">*</span></label>
                            <input class="form-control @error('guardian_name') is-invalid @enderror" type="text" id="guardian_name" name="guardian_name" value="{{ old('guardian_name') }}" maxlength="120" required>
                            @error('guardian_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="guardian_phone">{{ __('Guardian mobile') }}<span class="required">*</span></label>
                            <input class="form-control @error('guardian_phone') is-invalid @enderror" type="tel" id="guardian_phone" name="guardian_phone" value="{{ old('guardian_phone') }}" placeholder="01XXXXXXXXX" maxlength="30" inputmode="tel" required>
                            @error('guardian_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="guardian_email">{{ __('Guardian email') }}</label>
                            <input class="form-control @error('guardian_email') is-invalid @enderror" type="email" id="guardian_email" name="guardian_email" value="{{ old('guardian_email') }}" maxlength="120">
                            @error('guardian_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="address">{{ __('Address') }}</label>
                            <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2" maxlength="500">{{ old('address') }}</textarea>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="photo">{{ __('Passport-size photo') }}</label>
                            <input class="form-control @error('photo') is-invalid @enderror" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
                            <div class="form-hint">{{ __('Optional. JPEG, PNG or WebP up to 2 MB. The photo moves into the student record when the application is approved.') }}</div>
                            @error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i>{{ __('Submit application') }}</button>
                        <a href="{{ route('applicant.dashboard') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Before you submit') }}</h2>
                <ul class="list-unstyled d-grid gap-2 mt-3 mb-0">
                    <li><i class="bi bi-1-circle me-2"></i>{{ __('One application is reviewed at a time. Submit the next one after the decision.') }}</li>
                    <li><i class="bi bi-2-circle me-2"></i>{{ __('You will get an application number, for example APP-2026-0001.') }}</li>
                    <li><i class="bi bi-3-circle me-2"></i>{{ __('The school office records the decision here and sends you a notification.') }}</li>
                    <li><i class="bi bi-4-circle me-2"></i>{{ __('Keep the guardian mobile reachable — that is how the office contacts you.') }}</li>
                </ul>
            </section>

            <section class="ui-card p-lg mt-3">
                <h2 class="card-title">{{ __('Need help?') }}</h2>
                <p class="text-muted-2 mt-2 mb-0" style="font-size:.82rem;">
                    {{ __('Contact the school office if the form cannot be completed. Never share your password with anyone, including staff.') }}
                </p>
            </section>
        </div>
    </div>
@endsection
