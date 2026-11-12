@extends('layouts.guest')

@section('title', __('Forgot password'))

@section('panel')
    <div class="auth-form-head">
        <h2>{{ __('Forgot your password?') }}</h2>
        <p>{{ __('Enter your account email and we will send you a secure reset link.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.email') }}" class="auth-form" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">{{ __('Email address') }}</label>
            <div class="input-icon">
                <i class="bi bi-envelope"></i>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="form-control @error('email') is-invalid @enderror"
                    placeholder="{{ __('you@school.edu.bd') }}"
                    autocomplete="username"
                    inputmode="email"
                    required
                    autofocus
                >
            </div>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary auth-submit mt-2">
            <i class="bi bi-send"></i>
            {{ __('Send reset link') }}
        </button>
    </form>

    <a class="auth-back" href="{{ route('login') }}">
        <i class="bi bi-arrow-left"></i>{{ __('Back to sign in') }}
    </a>

    <div class="muted-box mt-4">
        <i class="bi bi-info-circle me-1"></i>
        {{ __('For security, we show the same confirmation whether or not the email is registered. Reset links expire automatically.') }}
    </div>
@endsection
