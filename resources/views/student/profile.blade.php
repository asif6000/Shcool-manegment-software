@extends('layouts.student')

@section('title', __('My profile'))

@section('content')
    @php
        $guardian = $student->primaryGuardian();
        $activeEnrollment = $student->enrollments->firstWhere('status', 'active');
    @endphp

    <div class="profile-stack">
        <div class="ui-card profile-summary">
            <span class="avatar profile-avatar">
                @if ($student->photo_path)
                    <img src="{{ route('student.photo', ['v' => $student->photoVersion()]) }}" alt="{{ $student->full_name }}">
                @else
                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($student->first_name, 0, 1).\Illuminate\Support\Str::substr($student->last_name ?? '', 0, 1)) }}
                @endif
            </span>
            <div class="profile-summary-main">
                <h1 class="profile-summary-name">{{ $student->full_name }}</h1>
                <div class="profile-summary-meta">
                    <span class="profile-chip"><i class="bi bi-person-vcard"></i>{{ $student->student_id }}</span>
                    <span class="profile-chip is-navy"><i class="bi bi-mortarboard-fill"></i>{{ __('Class') }} {{ $activeEnrollment?->schoolClass?->name ?? '—' }}</span>
                    <span class="profile-chip is-navy"><i class="bi bi-diagram-3-fill"></i>{{ __('Section') }} {{ $activeEnrollment?->section?->name ?? '—' }}</span>
                    <span class="profile-chip is-navy"><i class="bi bi-list-ol"></i>{{ __('Roll') }} {{ $activeEnrollment?->roll_no ?? '—' }}</span>
                    <span class="badge-soft {{ $student->status === 'active' ? 'is-success' : 'is-warning' }}">{{ __(ucfirst($student->status)) }}</span>
                </div>
            </div>
            <div class="profile-actions">
                <a href="{{ route('student.dashboard') }}" class="btn btn-light-soft btn-sm">
                    <i class="bi bi-arrow-left"></i>{{ __('Overview') }}
                </a>
                <a href="{{ route('student.password.change') }}" class="btn btn-light-soft btn-sm">
                    <i class="bi bi-key"></i>{{ __('Change password') }}
                </a>
            </div>
        </div>

        <div class="ui-card p-0">
            <div class="portal-card-head is-purple">
                <span><i class="bi bi-camera"></i> {{ __('Profile photo') }}</span>
                <span class="portal-card-date">{{ __('Only you and the school office can see it.') }}</span>
            </div>
            <div class="portal-card-body">
                @include('partials.profile-photo', [
                    'fullName' => $student->full_name,
                    'initials' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($student->first_name, 0, 1).\Illuminate\Support\Str::substr($student->last_name ?? '', 0, 1)),
                    'photoSrc' => $student->photo_path !== null ? route('student.photo', ['v' => $student->photoVersion()]) : null,
                    'uploadUrl' => route('student.photo.store'),
                    'removeUrl' => route('student.photo.destroy'),
                    'photoMaxKb' => $photoMaxKb,
                    'photoAccept' => $photoAccept,
                ])
            </div>
        </div>

        <div class="ui-card p-0">
            <div class="portal-card-head is-brand">
                <span><i class="bi bi-person-lines-fill"></i> {{ __('Student information') }}</span>
            </div>
            <div class="portal-card-body">
                <div class="profile-grid">
                    <div class="profile-item"><span>{{ __('Student ID') }}</span><strong>{{ $student->student_id }}</strong></div>
                    <div class="profile-item"><span>{{ __('Admission no') }}</span><strong>{{ $student->admission_no ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Class') }}</span><strong>{{ $activeEnrollment?->schoolClass?->name ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Section') }}</span><strong>{{ $activeEnrollment?->section?->name ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Roll') }}</span><strong>{{ $activeEnrollment?->roll_no ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Date of birth') }}</span><strong>{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Gender') }}</span><strong>{{ $student->gender ? __(ucfirst($student->gender)) : '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Blood group') }}</span><strong>{{ $student->blood_group ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Phone') }}</span><strong>{{ $student->phone ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Email') }}</span><strong>{{ $student->email ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Religion') }}</span><strong>{{ $student->religion ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Nationality') }}</span><strong>{{ $student->nationality ?? '—' }}</strong></div>
                    <div class="profile-item"><span>{{ __('Admission date') }}</span><strong>{{ $student->admission_date?->format('d M Y') ?? '—' }}</strong></div>
                    <div class="profile-item profile-item-wide"><span>{{ __('Present address') }}</span><strong>{{ $student->present_address ?? '—' }}</strong></div>
                    <div class="profile-item profile-item-wide"><span>{{ __('Permanent address') }}</span><strong>{{ $student->permanent_address ?? '—' }}</strong></div>
                </div>
            </div>
        </div>

        <div class="ui-card p-0">
            <div class="portal-card-head is-emerald">
                <span><i class="bi bi-people-fill"></i> {{ __('Guardian information') }}</span>
            </div>
            <div class="portal-card-body">
                @if ($student->guardians->isNotEmpty())
                    <div class="guardian-grid">
                        @foreach ($student->guardians as $item)
                            <div class="guardian-card">
                                <span class="guardian-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($item->name, 0, 1)) }}</span>
                                <div class="guardian-body">
                                    <span class="guardian-name">
                                        {{ $item->name }}
                                        @if ($item->pivot?->is_primary || ($guardian && $guardian->id === $item->id))
                                            <span class="badge-soft is-brand">{{ __('Primary') }}</span>
                                        @endif
                                    </span>
                                    <div class="guardian-meta">
                                        <span>{{ $item->relation ? __(ucfirst($item->relation)) : __('Guardian') }}</span>
                                        @if ($item->phone)<span><i class="bi bi-telephone"></i> {{ $item->phone }}</span>@endif
                                        @if ($item->email)<span><i class="bi bi-envelope"></i> {{ $item->email }}</span>@endif
                                        @if ($item->occupation)<span><i class="bi bi-briefcase"></i> {{ $item->occupation }}</span>@endif
                                        @if ($item->address)<span><i class="bi bi-geo-alt"></i> {{ $item->address }}</span>@endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state">
                        <i class="bi bi-people empty-icon"></i>
                        <p class="mb-0">{{ __('No guardian has been linked yet.') }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="ui-card p-0 table-card">
            <div class="portal-card-head is-navy">
                <span><i class="bi bi-clock-history"></i> {{ __('Enrollment history') }}</span>
                <span class="portal-card-date">{{ __('One enrollment per academic session.') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table responsive-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Session') }}</th>
                            <th>{{ __('Class') }}</th>
                            <th>{{ __('Section') }}</th>
                            <th>{{ __('Roll') }}</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($student->enrollments as $enrollment)
                            <tr>
                                <td data-label="{{ __('Session') }}">{{ $enrollment->academicSession?->name ?? '—' }}</td>
                                <td data-label="{{ __('Class') }}">{{ $enrollment->schoolClass?->name ?? '—' }}</td>
                                <td data-label="{{ __('Section') }}">{{ $enrollment->section?->name ? $enrollment->section->name : '—' }}</td>
                                <td data-label="{{ __('Roll') }}">{{ $enrollment->roll_no ?? '—' }}</td>
                                <td data-label="{{ __('Status') }}">
                                    <span class="badge-soft {{ $enrollment->status === 'active' ? 'is-success' : '' }}">
                                        {{ __(ucfirst($enrollment->status)) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="bi bi-journal-x empty-icon"></i>
                                        <p class="mb-0">{{ __('No enrollment yet') }}</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ui-card p-0">
            <div class="portal-card-head is-plain">
                <span><i class="bi bi-shield-lock"></i> {{ __('Portal account') }}</span>
            </div>
            <div class="portal-card-body">
                <div class="profile-grid">
                    <div class="profile-item"><span>{{ __('Mobile number') }}</span><strong>{{ auth()->user()->mobile_number ?? auth()->user()->phone }}</strong></div>
                    <div class="profile-item"><span>{{ __('Last sign-in') }}</span><strong>{{ auth()->user()->last_login_at?->format('d M Y, h:i A') ?? '—' }}</strong></div>
                    <div class="profile-item profile-item-wide"><span>{{ __('Record corrections') }}</span><strong>{{ __('Contact the school office for corrections to your academic record.') }}</strong></div>
                </div>
            </div>
        </div>
    </div>
@endsection
