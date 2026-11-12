@extends('layouts.app')

@section('title', __('My profile'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-gear"></i>{{ __('Account') }}</span>
            <h1 class="page-title">{{ __('My profile') }}</h1>
            <p class="page-subtitle">{{ __('Your personal account details. Changes are recorded in the audit trail.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('profile.password.change') }}" class="btn btn-light-soft"><i class="bi bi-key"></i>{{ __('Change password') }}</a>
        </div>
    </div>

    <section class="profile-hero">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="avatar profile-avatar">
                @if ($user->avatarUrl())
                    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}">
                @else
                    {{ $user->initials() }}
                @endif
            </span>
            <div class="flex-grow-1">
                <h1>{{ $user->name }}</h1>
                <div class="meta">{{ $user->email }}</div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="school-type"><i class="bi bi-shield-check"></i>{{ $user->primaryRoleLabel() }}</span>
                    @if ($user->school)
                        <span class="school-type"><i class="bi bi-building"></i>{{ $user->school->short_name ?: $user->school->name }}</span>
                    @endif
                    @if ($user->last_login_at)
                        <span class="school-type"><i class="bi bi-clock"></i>{{ __('Last sign-in :time', ['time' => $user->last_login_at->diffForHumans()]) }}</span>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div style="display:grid;grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr);gap:20px;align-items:start;" class="profile-edit-grid">
        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" novalidate>
            @csrf
            @method('PATCH')

            <div class="ui-card form-card">
                <div class="form-section">
                    <div class="form-section-head">
                        <h2>{{ __('Personal details') }}</h2>
                        <p>{{ __('Use a name and contact details your school can recognise.') }}</p>
                    </div>
                    <div class="form-grid">
                        <div class="span-2">
                            <label for="name" class="form-label">{{ __('Full name') }}<span class="required">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required autocomplete="name">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label for="email" class="form-label">{{ __('Email address') }}<span class="required">*</span></label>
                            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required autocomplete="email">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint">{{ __('This is your sign-in identifier.') }}</div>
                        </div>
                        <div>
                            <label for="phone" class="form-label">{{ __('Phone number') }}</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control @error('phone') is-invalid @enderror" autocomplete="tel">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <h2>{{ __('Profile photo') }}</h2>
                        <p>{{ __('Optional. JPEG, PNG or WebP up to 2 MB.') }}</p>
                    </div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <span class="avatar avatar-lg">
                            @if ($user->avatarUrl())
                                <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}">
                            @else
                                {{ $user->initials() }}
                            @endif
                        </span>
                        <div class="flex-grow-1" style="min-width:220px;">
                            <label for="avatar" class="form-label">{{ __('Upload new photo') }}</label>
                            <input type="file" id="avatar" name="avatar" class="form-control @error('avatar') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                            @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="{{ route('dashboard') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i>{{ __('Save profile') }}</button>
                </div>
            </div>
        </form>

        <aside class="dashboard-stack">
            <section class="ui-card p-lg">
                <h2 class="card-title mb-2">{{ __('Security') }}</h2>
                <p class="card-subtitle mb-3">{{ __('Keep your account safe with a strong, unique password.') }}</p>
                <a href="{{ route('profile.password.change') }}" class="btn btn-light-soft w-100 mb-2">
                    <i class="bi bi-key"></i>{{ __('Change password') }}
                </a>
                <div class="muted-box">
                    <i class="bi bi-shield-check me-1"></i>
                    {{ __('Password changes sign you out of API clients and other devices while keeping this session active.') }}
                </div>
            </section>

            <section class="ui-card p-lg">
                <h2 class="card-title mb-2">{{ __('Account details') }}</h2>
                <div class="info-grid" style="grid-template-columns:1fr;">
                    <div class="info-item">
                        <i class="bi bi-person-badge"></i>
                        <div><span class="label">{{ __('Role') }}</span><span class="value">{{ $user->primaryRoleLabel() }}</span></div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-building"></i>
                        <div><span class="label">{{ __('School') }}</span><span class="value">{{ $user->school?->name ?? __('Not linked') }}</span></div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-calendar-plus"></i>
                        <div><span class="label">{{ __('Member since') }}</span><span class="value">{{ $user->created_at?->format('j F Y') }}</span></div>
                    </div>
                    <div class="info-item" style="border-bottom:0;">
                        <i class="bi bi-translate"></i>
                        <div><span class="label">{{ __('Interface language') }}</span><span class="value">{{ app()->getLocale() === 'bn' ? __('বাংলা') : __('English') }}</span></div>
                    </div>
                </div>
            </section>
        </aside>
    </div>
@endsection
