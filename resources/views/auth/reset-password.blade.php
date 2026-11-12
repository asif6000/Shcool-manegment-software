@extends('layouts.guest')

@section('title', __('Reset password'))

@section('panel')
    <div class="auth-form-head">
        <h2>{{ __('Choose a new password') }}</h2>
        <p>{{ __('Use at least 8 characters with letters and numbers. Avoid using your name or email.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="auth-form" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label for="email" class="form-label">{{ __('Email address') }}</label>
            <div class="input-icon">
                <i class="bi bi-envelope"></i>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', $email) }}"
                    class="form-control @error('email') is-invalid @enderror"
                    autocomplete="username"
                    inputmode="email"
                    required
                >
            </div>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">{{ __('New password') }}</label>
            <div class="input-icon">
                <i class="bi bi-lock"></i>
                <input
                    id="password"
                    type="password"
                    name="password"
                    class="form-control has-toggle @error('password') is-invalid @enderror"
                    autocomplete="new-password"
                    required
                    autofocus
                >
                <button type="button" class="password-toggle" data-toggle-password="password" data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}" aria-label="{{ __('Show password') }}">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            <div class="form-hint">{{ __('Minimum 8 characters, including letters and numbers.') }}</div>
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">{{ __('Confirm new password') }}</label>
            <div class="input-icon">
                <i class="bi bi-lock-fill"></i>
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

        <button type="submit" class="btn btn-primary auth-submit mt-2">
            <i class="bi bi-shield-check"></i>
            {{ __('Reset password') }}
        </button>
    </form>

    <a class="auth-back" href="{{ route('login') }}">
        <i class="bi bi-arrow-left"></i>{{ __('Back to sign in') }}
    </a>
@endsection
