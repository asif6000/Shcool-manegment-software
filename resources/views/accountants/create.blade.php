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
                    <p>{{ __('This login always receives the accountant role and signs in with the mobile number.') }}</p>
                </div>

                <div class="form-grid">
                    <div>
                        <label for="name" class="form-label">{{ __('Full name') }}<span class="required">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required autofocus autocomplete="name">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="mobile_number" class="form-label">{{ __('Mobile number') }}<span class="required">*</span></label>
                        <input type="tel" id="mobile_number" name="mobile_number" value="{{ old('mobile_number') }}" class="form-control @error('mobile_number') is-invalid @enderror" autocomplete="tel" inputmode="numeric" placeholder="01XXXXXXXXX" required>
                        @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-hint">{{ __('The accountant signs in with this number. Must be unique, e.g. 01712345678.') }}</div>
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

            <div class="form-actions">
                <a href="{{ route('accountants.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-person-check"></i>{{ __('Create accountant') }}</button>
            </div>
        </div>
    </form>
@endsection
