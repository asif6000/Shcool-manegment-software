@extends('layouts.guest')

@section('title', __('Sign in'))

@section('panel')
    <div class="auth-form-head">
        <h2>{{ __('Welcome back') }}</h2>
        <p>{{ __('Sign in to your school workspace to continue.') }}</p>
    </div>

    <form method="POST" action="{{ route('login.store') }}" class="auth-form" novalidate>
        @csrf

        <div class="mb-3">
            <label for="role" class="form-label">{{ __('Select Role') }}<span class="required">*</span></label>
            <div class="input-icon">
                <i class="bi bi-person-badge"></i>
                <select
                    id="role"
                    name="role"
                    class="form-select @error('role') is-invalid @enderror"
                    required
                    autofocus
                >
                    <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>{{ __('Admin') }}</option>
                    <option value="principal" {{ old('role') === 'principal' ? 'selected' : '' }}>{{ __('Principal') }}</option>
                    <option value="teacher" {{ old('role') === 'teacher' ? 'selected' : '' }}>{{ __('Teacher') }}</option>
                    <option value="student" {{ old('role') === 'student' ? 'selected' : '' }}>{{ __('Student') }}</option>
                    <option value="accountant" {{ old('role') === 'accountant' ? 'selected' : '' }}>{{ __('Accountant') }}</option>
                </select>
            </div>
            @error('role')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3" id="email-field">
            <label for="email" class="form-label">{{ __('Email Address') }}<span class="required">*</span></label>
            <div class="input-icon">
                <i class="bi bi-envelope"></i>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="form-control @error('email') is-invalid @enderror"
                    placeholder="{{ __('Enter your email address') }}"
                    autocomplete="username"
                    required
                >
            </div>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3 d-none" id="mobile-field">
            <label for="mobile_number" class="form-label">{{ __('Mobile Number') }}<span class="required">*</span></label>
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

        <div class="mb-2">
            <div class="d-flex justify-content-between align-items-center">
                <label for="password" class="form-label">{{ __('Password') }}</label>
                <a href="{{ route('password.request') }}" style="font-size:.78rem;font-weight:600;">{{ __('Forgot password?') }}</a>
            </div>
            <div class="input-icon">
                <i class="bi bi-lock"></i>
                <input
                    id="password"
                    type="password"
                    name="password"
                    class="form-control has-toggle @error('password') is-invalid @enderror"
                    placeholder="{{ __('Enter your password') }}"
                    autocomplete="current-password"
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

        <div class="auth-options">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="remember">{{ __('Keep me signed in') }}</label>
            </div>
            <span class="text-muted-2" style="font-size:.72rem;">
                <i class="bi bi-shield-lock me-1"></i>{{ __('Secure session') }}
            </span>
        </div>

        <button type="submit" class="btn btn-primary auth-submit">
            {{ __('Sign in') }}
            <i class="bi bi-arrow-right"></i>
        </button>
    </form>

    <div class="auth-divider">{{ __('New here') }}</div>

    <a class="auth-back" href="{{ route('register') }}">
        <i class="bi bi-person-plus"></i>{{ __('Create an account to apply for admission') }}
    </a>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const roleSelect = document.getElementById('role');
            const emailField = document.getElementById('email-field');
            const mobileField = document.getElementById('mobile-field');
            const emailInput = document.getElementById('email');
            const mobileInput = document.getElementById('mobile_number');

            function toggleField() {
                const role = roleSelect.value;
                if (role === 'admin') {
                    emailField.classList.remove('d-none');
                    mobileField.classList.add('d-none');
                    emailInput.required = true;
                    mobileInput.required = false;
                    mobileInput.value = '';
                } else {
                    emailField.classList.add('d-none');
                    mobileField.classList.remove('d-none');
                    emailInput.required = false;
                    mobileInput.required = true;
                    emailInput.value = '';
                }
            }

            roleSelect.addEventListener('change', toggleField);
            toggleField(); // Initialize on page load
        });
    </script>
@endsection
