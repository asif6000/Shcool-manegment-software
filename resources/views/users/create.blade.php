@extends('layouts.app')

@section('title', __('Add user'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-plus"></i>{{ __('Access control') }}</span>
            <h1 class="page-title">{{ __('Add user') }}</h1>
            <p class="page-subtitle">{{ __('Create an account and assign the least role they need. The user will be asked to change the temporary password at first sign-in.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('users.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Back to users') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('users.store') }}" novalidate>
        @csrf

        <div class="ui-card form-card">
            <div class="form-section">
                <div class="form-section-head">
                    <h2>{{ __('Account details') }}</h2>
                    <p>{{ __('A School Admin signs in with the email address. Teachers, accountants and the principal sign in with the mobile number, so fill the identifier the selected role needs.') }}</p>
                </div>

                <div class="form-grid">
                    <div>
                        <label for="name" class="form-label">{{ __('Full name') }}<span class="required">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required autofocus autocomplete="name">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="email" class="form-label">{{ __('Email address') }}<span class="required d-none" data-identity-email>*</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-hint">{{ __('Sign-in identifier for a School Admin account. Must be unique.') }}</div>
                    </div>

                    <div>
                        <label for="mobile_number" class="form-label">{{ __('Mobile number') }}<span class="required d-none" data-identity-mobile>*</span></label>
                        <input type="tel" id="mobile_number" name="mobile_number" value="{{ old('mobile_number') }}" class="form-control @error('mobile_number') is-invalid @enderror" autocomplete="tel" placeholder="01XXXXXXXXX">
                        @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-hint">{{ __('Sign-in identifier for teacher, accountant and principal accounts. Must be unique, e.g. 01712345678.') }}</div>
                    </div>

                    <div>
                        <label for="phone" class="form-label">{{ __('Phone number') }}</label>
                        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" class="form-control @error('phone') is-invalid @enderror" autocomplete="tel" placeholder="+880 1XXX XXXXXX">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="role" class="form-label">{{ __('Role') }}<span class="required">*</span></label>
                        <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="">{{ __('Select a role') }}</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role['name'] }}" @selected(old('role') === $role['name'])>{{ __($role['label']) }}</option>
                            @endforeach
                        </select>
                        @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-hint">{{ __('You can only assign staff roles below your own level. Student portal accounts are created from a student record.') }}</div>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-head">
                    <h2>{{ __('Temporary password') }}</h2>
                    <p>{{ __('Share this password with the user through a secure channel. They must change it at first sign-in.') }}</p>
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
                <a href="{{ route('users.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-person-check"></i>{{ __('Create user') }}</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    (function () {
        const role = document.getElementById('role');
        const emailMark = document.querySelector('[data-identity-email]');
        const mobileMark = document.querySelector('[data-identity-mobile]');

        if (!role || !emailMark || !mobileMark) {
            return;
        }

        const emailRoles = ['school_admin', 'super_admin'];
        const sync = () => {
            const usesMobile = role.value !== '' && !emailRoles.includes(role.value);
            emailMark.classList.toggle('d-none', usesMobile);
            mobileMark.classList.toggle('d-none', !usesMobile);
        };

        role.addEventListener('change', sync);
        sync();
    })();
</script>
@endpush
