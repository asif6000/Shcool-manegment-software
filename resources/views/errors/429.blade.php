@extends('layouts.guest')

@section('title', __('Too many requests'))

@section('panel')
    @php
        // ThrottleRequestsException carries Retry-After, so the page can say when
        // they are welcome back instead of telling them to try later and leaving
        // them guessing.
        $seconds = null;
        if (isset($exception) && method_exists($exception, 'getHeaders')) {
            $headers = (array) $exception->getHeaders();
            $seconds = isset($headers['Retry-After']) ? max(1, (int) $headers['Retry-After']) : null;
        }
        $wait = $seconds === null
            ? __('a minute or two')
            : ($seconds < 120 ? $seconds.' '.__('seconds') : (int) ceil($seconds / 60).' '.__('minutes'));
    @endphp

    <div class="auth-form-head">
        <span class="eyebrow"><i class="bi bi-hourglass-split"></i>{{ __('Slow down') }}</span>
        <h2>{{ __('That was one attempt too many') }}</h2>
        <p>
            {{ __('To keep sign-ups honest, this site limits how often the form may be used from one internet connection.') }}
            {{ __('Please wait about :wait and try again.', ['wait' => $wait]) }}
        </p>
    </div>

    <div class="alert alert-light-secondary small mt-3" role="note">
        <i class="bi bi-info-circle me-1"></i>
        {{ __('Families sharing one connection — a school office, a mobile network, a Wi-Fi hotspot — share this limit too, so register one student at a time and pause between them.') }}
    </div>

    <div class="d-grid gap-2 mt-4">
        <button type="button" class="btn btn-primary" onclick="window.location.reload()">
            <i class="bi bi-arrow-clockwise me-2"></i>{{ __('Try again') }}
        </button>
        <a href="{{ route('login') }}" class="btn btn-outline-secondary">
            <i class="bi bi-box-arrow-in-right me-2"></i>{{ __('Return to sign in') }}
        </a>
    </div>
@endsection
