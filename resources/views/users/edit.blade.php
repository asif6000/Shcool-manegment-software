@extends('layouts.app')

@section('title', __('Edit user'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-person-gear"></i>{{ __('Access control') }}</span>
            <h1 class="page-title">{{ __('Edit :name', ['name' => $user->name]) }}</h1>
            <p class="page-subtitle">{{ __('Update account details, role and status. Sensitive changes are written to the audit trail.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('users.index') }}" class="btn btn-light-soft"><i class="bi bi-arrow-left"></i>{{ __('Back to users') }}</a>
        </div>
    </div>

    @if ($orphanStudentAccount ?? false)
        <div class="alert alert-warning mb-3">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>{{ __('This account has the student role but is not linked to a student record, so it can only use the admission application desk. The student portal opens once an application is approved, or create portal access from the student page.') }}</div>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:minmax(0,1.6fr) minmax(280px,1fr);gap:20px;align-items:start;" class="user-edit-grid">
        <form method="POST" action="{{ route('users.update', $user) }}" novalidate>
            @csrf
            @method('PUT')

            <div class="ui-card form-card">
                <div class="form-section">
                    <div class="form-section-head">
                        <h2>{{ __('Account details') }}</h2>
                        <p>{{ __('A School Admin signs in with the email address. Teachers, accountants and the principal sign in with the mobile number, so keep the identifier the selected role needs.') }}</p>
                    </div>

                    <div class="form-grid">
                        <div>
                            <label for="name" class="form-label">{{ __('Full name') }}<span class="required">*</span></label>
                            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control @error('name') is-invalid @enderror" required autocomplete="name">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label for="email" class="form-label">{{ __('Email address') }}<span class="required d-none" data-identity-email>*</span></label>
                            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" autocomplete="email">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint">{{ __('Sign-in identifier for a School Admin account.') }}</div>
                        </div>

                        <div>
                            <label for="mobile_number" class="form-label">{{ __('Mobile number') }}<span class="required d-none" data-identity-mobile>*</span></label>
                            <input type="tel" id="mobile_number" name="mobile_number" value="{{ old('mobile_number', $user->mobile_number) }}" class="form-control @error('mobile_number') is-invalid @enderror" autocomplete="tel" placeholder="01XXXXXXXXX">
                            @error('mobile_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint">{{ __('Sign-in identifier for teacher, accountant and principal accounts. Must be unique.') }}</div>
                        </div>

                        <div>
                            <label for="phone" class="form-label">{{ __('Phone number') }}</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" class="form-control @error('phone') is-invalid @enderror" autocomplete="tel">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label for="role" class="form-label">{{ __('Role') }}<span class="required">*</span></label>
                            <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                                @foreach ($roles as $role)
                                    <option value="{{ $role['name'] }}" @selected(old('role', $currentRole) === $role['name'])>{{ __($role['label']) }}</option>
                                @endforeach
                            </select>
                            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-hint">{{ __('The last active school administrator cannot be demoted or deactivated.') }}</div>
                        </div>

                        <div class="span-2">
                            <label class="form-label">{{ __('School') }}</label>
                            <div class="muted-box d-flex align-items-center justify-content-between gap-2">
                                <span>
                                    <i class="bi bi-building me-1"></i>
                                    {{ $user->school?->name ?? __('No school linked') }}
                                </span>
                                @if ($user->school)
                                    <span class="badge-soft">{{ $user->school->code }}</span>
                                @endif
                            </div>
                            <div class="form-hint">{{ __('Accounts belong to the school workspace and cannot be moved.') }}</div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-head">
                        <h2>{{ __('Reset password') }}</h2>
                        <p>{{ __('Optional. Setting a password here signs the user out of all devices and requires a change at next sign-in.') }}</p>
                    </div>

                    <div class="form-grid">
                        <div>
                            <label for="password" class="form-label">{{ __('New password') }}</label>
                            <div class="input-icon">
                                <i class="bi bi-lock"></i>
                                <input type="password" id="password" name="password" class="form-control has-toggle @error('password') is-invalid @enderror" autocomplete="new-password">
                                <button type="button" class="password-toggle" data-toggle-password="password" data-show-label="{{ __('Show password') }}" data-hide-label="{{ __('Hide password') }}" aria-label="{{ __('Show password') }}"><i class="bi bi-eye"></i></button>
                            </div>
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}</label>
                            <div class="input-icon">
                                <i class="bi bi-lock-fill"></i>
                                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                        <label class="form-check-label" for="is_active">{{ __('Account is active') }}</label>
                    </div>
                    <div class="form-hint">{{ __('Deactivating an account immediately revokes API tokens and database sessions.') }}</div>
                </div>

                <div class="form-actions">
                    <a href="{{ route('users.index') }}" class="btn btn-light-soft">{{ __('Cancel') }}</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i>{{ __('Save changes') }}</button>
                </div>
            </div>
        </form>

        <aside class="dashboard-stack">
            <section class="ui-card p-lg">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar avatar-lg">
                        @if ($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="">
                        @else
                            {{ $user->initials() }}
                        @endif
                    </span>
                    <div>
                        <div class="fw-700">{{ $user->name }}</div>
                        <div class="text-muted-2" style="font-size:.78rem;">{{ $user->primaryRoleLabel() }}</div>
                        <div class="mt-2">
                            @if ($user->is_active)
                                <span class="badge-soft is-success"><span class="dot"></span>{{ __('Active') }}</span>
                            @else
                                <span class="badge-soft is-danger"><span class="dot"></span>{{ __('Inactive') }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <hr class="divider">

                <div class="info-grid" style="grid-template-columns:1fr;">
                    <div class="info-item">
                        <i class="bi bi-envelope"></i>
                        <div><span class="label">{{ __('Email') }}</span><span class="value">{{ $user->email ?: __('Not provided') }}</span></div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-phone"></i>
                        <div><span class="label">{{ __('Mobile number') }}</span><span class="value">{{ $user->mobile_number ?: __('Not provided') }}</span></div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-telephone"></i>
                        <div><span class="label">{{ __('Phone') }}</span><span class="value">{{ $user->phone ?: __('Not provided') }}</span></div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-clock"></i>
                        <div><span class="label">{{ __('Last sign-in') }}</span><span class="value">{{ $user->last_login_at?->format('j M Y, h:i A') ?? __('Never') }}</span></div>
                    </div>
                    <div class="info-item" style="border-bottom:0;">
                        <i class="bi bi-calendar-plus"></i>
                        <div><span class="label">{{ __('Created') }}</span><span class="value">{{ $user->created_at?->format('j M Y') }}</span></div>
                    </div>
                </div>
            </section>

            <section class="ui-card p-lg">
                <h2 class="card-title mb-2">{{ __('Account actions') }}</h2>
                <p class="card-subtitle mb-3">{{ __('Use these shortcuts for account recovery and lifecycle changes.') }}</p>

                @can('sendResetLink', $user)
                    <form method="POST" action="{{ route('users.reset-link', $user) }}" data-confirm="{{ __('Send a password reset link to :email?', ['email' => $user->email]) }}" class="mb-2">
                        @csrf
                        <button type="submit" class="btn btn-light-soft w-100">
                            <i class="bi bi-envelope-arrow-up"></i>{{ __('Send password reset link') }}
                        </button>
                    </form>
                @endcan

                @can('changeStatus', $user)
                    <form method="POST" action="{{ route('users.status', $user) }}" data-confirm="{{ $user->is_active ? __('Deactivate :name? They will be signed out immediately.', ['name' => $user->name]) : __('Activate :name?', ['name' => $user->name]) }}" class="mb-2">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-light-soft w-100">
                            <i class="bi {{ $user->is_active ? 'bi-pause-circle' : 'bi-play-circle' }}"></i>
                            {{ $user->is_active ? __('Deactivate account') : __('Activate account') }}
                        </button>
                    </form>
                @endcan

                @can('delete', $user)
                    @if ($user->hasRole('accountant'))
                        <a href="{{ route('accountants.edit', $user) }}" class="btn btn-danger-soft w-100">
                            <i class="bi bi-trash3"></i>{{ __('Delete from the accountant screen') }}
                        </a>
                    @else
                        <form method="POST" action="{{ route('users.destroy', $user) }}"
                              data-confirm="{{ __('Are you sure you want to permanently delete this record? This action cannot be undone.') }}"
                              data-confirm-delete="1" data-confirm-type="1"
                              data-confirm-label="{{ __('Permanently Delete') }}">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="permanent_delete" value="DELETE">
                            <button type="submit" class="btn btn-danger-soft w-100">
                                <i class="bi bi-trash3"></i>{{ __('Permanently delete user') }}
                            </button>
                        </form>
                    @endif
                @endcan
            </section>
        </aside>
    </div>
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
