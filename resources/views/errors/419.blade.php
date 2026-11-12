@extends('layouts.guest')

@section('title', __('Session expired'))

@section('panel')
    <div class="auth-form-head">
        <span class="eyebrow"><i class="bi bi-shield-exclamation"></i>{{ __('Security check') }}</span>
        <h2>{{ __('Your session has expired') }}</h2>
        <p>{{ __('For your security, the page was left open too long or the form was submitted twice. Sign in again to continue.') }}</p>
    </div>

    <div class="d-grid gap-2 mt-4">
        <a href="{{ route('login') }}" class="btn btn-primary">
            <i class="bi bi-box-arrow-in-right me-2"></i>{{ __('Return to sign in') }}
        </a>
        <button type="button" class="btn btn-outline-secondary" onclick="window.location.reload()">
            <i class="bi bi-arrow-clockwise me-2"></i>{{ __('Reload the page') }}
        </button>
    </div>
@endsection
