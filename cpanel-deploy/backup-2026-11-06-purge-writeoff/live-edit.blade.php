@extends('layouts.app')

@section('title', __('Edit accountant').' · '.$accountant->name)

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-badge"></i>{{ __('Accountant Management') }}</span>
            <h1 class="page-title">{{ __('Edit accountant') }}</h1>
            <p class="page-subtitle">{{ __('The mobile number is the sign-in identifier. Changing it changes the login.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('accountants.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Back to accountants') }}</a>
            <a href="{{ route('accountants.show', $accountant) }}" class="btn btn-light-soft"><i class="bi bi-eye"></i>{{ __('View details') }}</a>
        </div>
    </div>

    <form method="POST" action="{{ route('accountants.update', $accountant) }}" novalidate>
        @csrf
        @method('PUT')

        <div class="ui-card form-card">
            <div class="form-section">
                <div class="form-section-head">
                    <h2>{{ __('Account details') }}</h2>
                    <p>{{ __('The accountant keeps the accountant role; only the name and the sign-in number can change here.') }}</p>
                </div>

                <div class="form-grid">
                    <div>
                        <label for="name" class="form-label">{{ __('Full name') }}<span class="required">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', $accountant->name) }}" class="form-control @error('name') is-invalid @enderror" required autofocus autocomplete="name">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div>
                        <label for="mobile_number" class="form-label">{{ __('Mobile number') }}<span class="required">*</span></label>
                        <input type="tel" id="mobile_number" name="mobile_number" value="{{ old('mobile_number', $accountant->mobile_number) }}" class="form-control @error('mobile_number') is-invalid @enderror" autocomplete="tel" inputmode="numeric" placeholder="01XXXXXXXXX" required>
                        @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-hint">{{ __('Unique across the school. The accountant signs in with this number.') }}</div>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('accountants.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i>{{ __('Save changes') }}</button>
            </div>
        </div>
    </form>

    <div class="ui-card form-card mt-3" id="password">
        <div class="form-section">
            <div class="form-section-head">
                <h2>{{ __('Reset password') }}</h2>
                <p>{{ __('Set a temporary password. Every other device signed in as :name is signed out, and a new password is required at the next sign-in.', ['name' => $accountant->name]) }}</p>
            </div>

            <form method="POST" action="{{ route('accountants.password', $accountant) }}" novalidate>
                @csrf

                <div class="form-grid">
                    <div>
                        <label for="new_password" class="form-label">{{ __('New password') }}<span class="required">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-lock"></i>
                            <input type="password" id="new_password" name="password" class="form-control has-toggle @error('password') is-invalid @enderror" required autocomplete="new-password">
                            <button type="button" class="password-toggle" data-toggle-password="new_password" data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}" aria-label="{{ __('Show password') }}"><i class="bi bi-eye"></i></button>
                        </div>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-hint">{{ __('At least 8 characters including letters and numbers.') }}</div>
                    </div>

                    <div>
                        <label for="new_password_confirmation" class="form-label">{{ __('Confirm password') }}<span class="required">*</span></label>
                        <div class="input-icon">
                            <i class="bi bi-lock-fill"></i>
                            <input type="password" id="new_password_confirmation" name="password_confirmation" class="form-control" required autocomplete="new-password">
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-light-soft"><i class="bi bi-key"></i>{{ __('Reset password') }}</button>
                </div>
            </form>
        </div>

        <div class="form-section">
            <div class="form-section-head">
                <h2>{{ __('Account status') }}</h2>
                <p>{{ $accountant->is_active ? __('Active. This login can sign in and collect fees.') : __('Inactive. This login cannot sign in; the receipts already posted are kept.') }}</p>
            </div>

            <form method="POST" action="{{ route('accountants.status', $accountant) }}" data-confirm="{{ $accountant->is_active ? __('Deactivate :name? They will be signed out immediately.', ['name' => $accountant->name]) : __('Activate :name?', ['name' => $accountant->name]) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="btn {{ $accountant->is_active ? 'btn-light-soft' : 'btn-primary' }}">
                    <i class="bi {{ $accountant->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                    {{ $accountant->is_active ? __('Deactivate accountant') : __('Activate accountant') }}
                </button>
            </form>

            @can('delete', $accountant)
                <hr class="my-4">

                <div class="danger-zone">
                    <div class="form-section-head">
                        <h3 class="h6 fw-bold text-danger">{{ __('Permanently delete this accountant') }}</h3>
                        <p>{{ __('Deletes the login and every record posted in this accountant’s name. The deletion cannot be undone through the app; a backup is written before anything is removed.') }}</p>
                    </div>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#purgeAccountantModal">
                        <i class="bi bi-exclamation-octagon"></i>{{ __('Permanently delete accountant') }}
                    </button>
                </div>
            @endcan
        </div>
    </div>

    @can('delete', $accountant)
        @include('accountants.partials.purge-modal', [
            'accountant' => $accountant,
            'purge' => $purge,
        ])
    @endcan
@endsection
