@extends(auth()->user()?->hasRole('teacher') && ! auth()->user()?->hasAnyRole(['school_admin', 'principal', 'super_admin']) ? 'layouts.teacher' : 'layouts.app')

@section('title', __('My profile'))

@section('content')
    @php
        $teacherInitials = \Illuminate\Support\Str::upper(
            \Illuminate\Support\Str::substr($teacher->first_name, 0, 1).\Illuminate\Support\Str::substr($teacher->last_name ?? '', 0, 1)
        );
    @endphp

    <div class="page-header">
        <div class="d-flex align-items-center gap-3">
            <span class="avatar avatar-lg">
                @if ($teacher->photo_path)
                    <img src="{{ route('teacher.profile.photo', ['v' => $teacher->photoVersion()]) }}" alt="{{ $teacher->full_name }}">
                @else
                    {{ $teacherInitials }}
                @endif
            </span>
            <div>
                <span class="page-eyebrow"><i class="bi bi-person-badge"></i>{{ __('Account') }}</span>
                <h1 class="page-title mb-1">{{ $teacher->full_name }}</h1>
                <p class="page-subtitle mb-0">
                    {{ $teacher->employee_code }} · {{ $teacher->designation }}
                    <span class="badge-soft {{ $teacher->status === 'active' ? 'is-success' : 'is-warning' }} ms-1">{{ __(ucfirst($teacher->status)) }}</span>
                </p>
            </div>
        </div>
        <div class="page-actions">
            <a href="{{ route('profile.password.change') }}" class="btn btn-light-soft"><i class="bi bi-key"></i>{{ __('Change password') }}</a>
            <a href="{{ route('teacher.routine') }}" class="btn btn-light-soft"><i class="bi bi-calendar2-week"></i>{{ __('My routine') }}</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <section class="ui-card p-lg">
                <div class="form-section-head mb-3">
                    <h2 class="card-title mb-1">{{ __('Profile photo') }}</h2>
                    <p class="text-muted mb-0">{{ __('Upload, change or remove your picture. Your header, this page and the staff list all show it straight away.') }}</p>
                </div>

                @include('partials.profile-photo', [
                    'fullName' => $teacher->full_name,
                    'initials' => $teacherInitials,
                    'photoSrc' => $teacher->photo_path !== null ? route('teacher.profile.photo', ['v' => $teacher->photoVersion()]) : null,
                    'uploadUrl' => route('teacher.profile.photo.store'),
                    'removeUrl' => route('teacher.profile.photo.destroy'),
                    'photoMaxKb' => $photoMaxKb,
                    'photoAccept' => $photoAccept,
                ])
            </section>
        </div>

        <div class="col-lg-7">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Teaching details') }}</h2>
                <p class="text-muted small">{{ __('Ask the school office to correct anything on this list.') }}</p>
                <dl class="detail-list">
                    <div><dt>{{ __('Employee code') }}</dt><dd>{{ $teacher->employee_code }}</dd></div>
                    <div><dt>{{ __('Designation') }}</dt><dd>{{ $teacher->designation ?: '—' }}</dd></div>
                    <div><dt>{{ __('Gender') }}</dt><dd>{{ $teacher->gender ? __(ucfirst($teacher->gender)) : '—' }}</dd></div>
                    <div><dt>{{ __('Date of birth') }}</dt><dd>{{ $teacher->date_of_birth?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>{{ __('Phone') }}</dt><dd>{{ $teacher->phone ?: '—' }}</dd></div>
                    <div><dt>{{ __('Email') }}</dt><dd>{{ $teacher->email ?: '—' }}</dd></div>
                    <div><dt>{{ __('Joining date') }}</dt><dd>{{ $teacher->joining_date?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>{{ __('Employment type') }}</dt><dd>{{ __(ucwords(str_replace('_', ' ', $teacher->employment_type))) }}</dd></div>
                    <div><dt>{{ __('Qualification') }}</dt><dd>{{ $teacher->qualification ?? '—' }}</dd></div>
                    <div><dt>{{ __('Specialization') }}</dt><dd>{{ $teacher->specialization ?? '—' }}</dd></div>
                </dl>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="ui-card p-lg">
                <h2 class="card-title">{{ __('Sign-in account') }}</h2>
                <dl class="detail-list">
                    <div><dt>{{ __('Name on account') }}</dt><dd>{{ $shellUser?->name }}</dd></div>
                    <div><dt>{{ __('Sign-in email') }}</dt><dd>{{ $shellUser?->email }}</dd></div>
                    <div><dt>{{ __('Role') }}</dt><dd>{{ $shellUser?->primaryRoleLabel() }}</dd></div>
                    <div><dt>{{ __('Last sign-in') }}</dt><dd>{{ $shellUser?->last_login_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                </dl>
                <div class="d-grid gap-2 mt-3">
                    <a href="{{ route('profile.edit') }}" class="btn btn-light-soft btn-sm">
                        <i class="bi bi-person-gear"></i>{{ __('Account details') }}
                    </a>
                    <a href="{{ route('profile.password.change') }}" class="btn btn-light-soft btn-sm">
                        <i class="bi bi-key"></i>{{ __('Change password') }}
                    </a>
                </div>
            </section>
        </div>
    </div>
@endsection
