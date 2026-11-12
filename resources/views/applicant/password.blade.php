@extends('layouts.applicant')

@section('title', __('Change password'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-key"></i>{{ __('Account security') }}</span>
            <h1 class="page-title">{{ __('Change password') }}</h1>
            <p class="page-subtitle">{{ __('Choose a new password for :email.', ['email' => auth()->user()->email]) }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('applicant.dashboard') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('My applications') }}</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <section class="ui-card p-lg">
                <form method="POST" action="{{ route('applicant.password.update') }}" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label" for="current_password">{{ __('Current password') }}</label>
                        <input id="current_password" type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required autocomplete="current-password">
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="password">{{ __('New password') }}</label>
                        <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-hint">{{ __('At least 8 characters with letters and numbers.') }}</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password_confirmation">{{ __('Confirm new password') }}</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-shield-check"></i>{{ __('Update password') }}
                    </button>
                </form>
            </section>
        </div>

        <div class="col-lg-6">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Sign-in safety') }}</h2>
                <ul class="list-unstyled d-grid gap-2 mt-3 mb-0">
                    <li><i class="bi bi-check2-circle text-success me-2"></i>{{ __('Changing your password signs out other devices that use this account.') }}</li>
                    <li><i class="bi bi-check2-circle text-success me-2"></i>{{ __('Your application is only visible to you and the school office.') }}</li>
                    <li><i class="bi bi-check2-circle text-success me-2"></i>{{ __('Contact the school office if you cannot sign in.') }}</li>
                </ul>
            </section>
        </div>
    </div>
@endsection
