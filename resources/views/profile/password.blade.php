@extends('layouts.app')

@section('title', __('Change password'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-key"></i>{{ __('Security') }}</span>
            <h1 class="page-title">{{ __('Change password') }}</h1>
            <p class="page-subtitle">{{ __('Choose a strong password you have not used elsewhere.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('profile.edit') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Back to profile') }}</a>
        </div>
    </div>

    @if ($user->must_change_password)
        <div class="alert alert-warning mb-3">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <strong>{{ __('You are using a temporary password.') }}</strong>
                {{ __('Please choose a new password now to unlock the rest of the workspace.') }}
            </div>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:minmax(0,1.4fr) minmax(280px,1fr);gap:20px;align-items:start;" class="password-grid">
        <form method="POST" action="{{ route('profile.password.update') }}" novalidate>
            @csrf
            @method('PUT')

            <div class="ui-card form-card">
                <div class="form-section">
                    <div class="form-section-head">
                        <h2>{{ __('Verify and update') }}</h2>
                        <p>{{ __('Enter your current password first, then choose a new one.') }}</p>
                    </div>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">{{ __('Current password') }}<span class="required">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-lock"></i>
                            <input type="password" id="current_password" name="current_password" class="form-control has-toggle @error('current_password') is-invalid @enderror" required autocomplete="current-password" autofocus>
                            <button type="button" class="password-toggle" data-toggle-password="current_password" data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}" aria-label="{{ __('Show password') }}"><i class="bi bi-eye"></i></button>
                        </div>
                        @error('current_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-grid">
                        <div>
                            <label for="password" class="form-label">{{ __('New password') }}<span class="required">*</span></label>
                            <div class="input-icon">
                                <i class="bi bi-shield-lock"></i>
                                <input type="password" id="password" name="password" class="form-control has-toggle @error('password') is-invalid @enderror" required autocomplete="new-password">
                                <button type="button" class="password-toggle" data-toggle-password="password" data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}" aria-label="{{ __('Show password') }}"><i class="bi bi-eye"></i></button>
                            </div>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint">{{ __('At least 8 characters with letters and numbers.') }}</div>
                        </div>
                        <div>
                            <label for="password_confirmation" class="form-label">{{ __('Confirm new password') }}<span class="required">*</span></label>
                            <div class="input-icon">
                                <i class="bi bi-check2-circle"></i>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="{{ route('dashboard') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-shield-check"></i>{{ __('Update password') }}</button>
                </div>
            </div>
        </form>

        <aside class="dashboard-stack">
            <section class="ui-card p-lg">
                <h2 class="card-title mb-2">{{ __('A strong password') }}</h2>
                <ul class="text-muted-2 ps-3 mb-0" style="font-size:.8rem;line-height:1.8;">
                    <li>{{ __('Use at least 8 characters — longer is better.') }}</li>
                    <li>{{ __('Mix letters, numbers and symbols.') }}</li>
                    <li>{{ __('Avoid names, birthdays and school names.') }}</li>
                    <li>{{ __('Never share your password with anyone.') }}</li>
                </ul>
            </section>

            <section class="ui-card p-lg">
                <h2 class="card-title mb-2">{{ __('What happens next') }}</h2>
                <p class="text-muted-2 mb-0" style="font-size:.8rem;">
                    {{ __('Other browsers and API tokens are signed out. This browser session remains active so you can continue working.') }}
                </p>
            </section>
        </aside>
    </div>
@endsection
