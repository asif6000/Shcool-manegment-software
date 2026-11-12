@extends('layouts.guest')

@section('title', __('Create applicant account'))

@section('panel')
    <div class="auth-form-head">
        <h2>{{ __('Create your account') }}</h2>
        <p>{{ __('An account is required to apply for admission. Sign up, then fill in the application form.') }}</p>
    </div>

    <form method="POST" action="{{ route('register.store') }}" class="auth-form" novalidate>
        @csrf

        <div class="mb-3">
            <label for="name" class="form-label">{{ __('Full name') }}<span class="required">*</span></label>
            <div class="input-icon">
                <i class="bi bi-person"></i>
                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    class="form-control @error('name') is-invalid @enderror"
                    placeholder="{{ __('Guardian or student name') }}"
                    autocomplete="name"
                    required
                    autofocus
                >
            </div>
            @error('name')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="mobile_number" class="form-label">{{ __('Mobile number') }}<span class="required">*</span></label>
            <div class="input-icon">
                <i class="bi bi-phone"></i>
                <input
                    id="mobile_number"
                    type="tel"
                    name="mobile_number"
                    value="{{ old('mobile_number') }}"
                    class="form-control @error('mobile_number') is-invalid @enderror"
                    placeholder="01XXXXXXXXX"
                    autocomplete="tel"
                    inputmode="tel"
                    required
                >
            </div>
            @error('mobile_number')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">{{ __('Password') }}<span class="required">*</span></label>
            <div class="input-icon">
                <i class="bi bi-lock"></i>
                <input
                    id="password"
                    type="password"
                    name="password"
                    class="form-control has-toggle @error('password') is-invalid @enderror"
                    placeholder="{{ __('At least 8 characters with letters and numbers') }}"
                    autocomplete="new-password"
                    required
                >
                <button type="button" class="password-toggle" data-toggle-password="password" data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}" aria-label="{{ __('Show password') }}">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}<span class="required">*</span></label>
            <div class="input-icon">
                <i class="bi bi-shield-lock"></i>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    class="form-control"
                    autocomplete="new-password"
                    required
                >
            </div>
        </div>

        <button type="submit" class="btn btn-primary auth-submit">
            {{ __('Create account') }}
            <i class="bi bi-arrow-right"></i>
        </button>
    </form>

    <div class="auth-divider">{{ __('Already registered') }}</div>

    <a class="auth-back" href="{{ route('student.login') }}">
        <i class="bi bi-box-arrow-in-right"></i>{{ __('Sign in instead') }}
    </a>

    <div class="muted-box mt-4">
        <i class="bi bi-info-circle me-1"></i>
        {{ __('Your password is stored hashed and only school staff can review your application.') }}
    </div>
@endsection
