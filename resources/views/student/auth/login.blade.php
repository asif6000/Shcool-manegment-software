@extends('layouts.guest')

@section('title', __('Student sign in'))

@section('panel')
    <div class="auth-form-head">
        <h2>{{ __('Welcome back') }}</h2>
        <p>{{ __('Students sign in with their mobile number and password.') }}</p>
    </div>

    <form method="POST" action="{{ route('student.login.store') }}" class="auth-form" novalidate>
        @csrf

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
                    autofocus
                >
            </div>
            @error('mobile_number')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-2">
            <label for="password" class="form-label">{{ __('Password') }}</label>
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

    <div class="muted-box mt-4">
        <i class="bi bi-info-circle me-1"></i>
        {{ __('No email is needed for student accounts — your mobile number is your sign-in ID.') }}
    </div>
@endsection
