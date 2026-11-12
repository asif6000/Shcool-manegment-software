@extends('layouts.app')

@section('title', __('Add accountant'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-badge"></i>{{ __('Accountant Management') }}</span>
            <h1 class="page-title">{{ __('Add accountant') }}</h1>
            <p class="page-subtitle">{{ __('Create a finance desk login. The accountant will be asked to change the temporary password at first sign-in.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('accountants.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Back to accountants') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('accountants.store') }}" novalidate>
        @csrf

        <div class="ui-card form-card">
            <div class="form-section">
                <div class="form-section-head">
                    <h2>{{ __('Account details') }}</h2>
                    <p>{{ __('This login always receives the accountant role and can collect fees.') }}</p>
                </div>

                <div class="form-grid">
                    <div>
                        <label for="name" class="form-label">{{ __('Full name') }}<span class="required">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required autofocus autocomplete="name">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="email" class="form-label">{{ __('Email address') }}<span class="required">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" required autocomplete="email">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="phone" class="form-label">{{ __('Phone number') }}</label>
                        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" class="form-control @error('phone') is-invalid @enderror" autocomplete="tel" placeholder="+880 1XXX XXXXXX">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label class="form-label">{{ __('Role') }}</label>
                        <input type="text" class="form-control" value="{{ __('Accountant') }}" disabled>
                        <div class="form-hint">{{ __('Accountant logins can collect fees and see the finance workspace.') }}</div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-head">
                    <h2>{{ __('Temporary password') }}</h2>
                    <p>{{ __('Share this password with the accountant through a secure channel.') }}</p>
                </div>

                <div class="form-grid">
                    <div>
                        <label for="password" class="form-label">{{ __('Password') }}<span class="required">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-lock"></i>
                            <input type="password" id="password" name="password" class="form-control has-toggle @error('password') is-invalid @enderror" required autocomplete="new-password">
                            <button type="button" class="password-toggle" data-toggle-password="password" data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}" aria-label="{{ __('Show password') }}"><i class="bi bi-eye"></i></button>
                        </div>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-hint">{{ __('At least 8 characters including letters and numbers.') }}</div>
                    </div>

                    <div>
                        <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}<span class="required">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-lock-fill"></i>
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', true))>
                    <label class="form-check-label" for="is_active">{{ __('Account is active') }}</label>
                </div>
                <div class="form-hint">{{ __('Inactive accounts cannot sign in but remain in the audit trail.') }}</div>
            </div>

            <div class="form-actions">
                <a href="{{ route('accountants.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-person-check"></i>{{ __('Create accountant') }}</button>
            </div>
        </div>
    </form>
@endsection
