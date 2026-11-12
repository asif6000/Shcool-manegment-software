@extends('layouts.app')

@section('title', __('School profile'))

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-building"></i>{{ __('School profile') }}</span>
            <h1 class="page-title">{{ __('School profile') }}</h1>
            <p class="page-subtitle">{{ __('Institution identity, contact details and defaults used across the system.') }}</p>
        </div>
        <div class="page-actions">
            @can('update', $school)
                <a href="{{ route('schools.edit') }}" class="btn btn-primary"><i class="bi bi-pencil-square"></i>{{ __('Edit profile') }}</a>
            @endcan
        </div>
    </div>

    <section class="profile-hero">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="avatar profile-avatar">
                @if ($school->logoUrl())
                    <img src="{{ $school->logoUrl() }}" alt="{{ $school->name }}">
                @else
                    {{ $school->initials() }}
                @endif
            </span>
            <div class="flex-grow-1">
                <h1>{{ $school->name }}</h1>
                <div class="meta">
                    {{ $school->short_name ?: __('School') }}
                    · {{ $school->code }}
                    @if ($school->eiin) · EIIN {{ $school->eiin }} @endif
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="school-type"><i class="bi bi-geo-alt"></i>{{ collect([$school->upazila, $school->district, $school->division])->filter()->implode(', ') ?: __('Bangladesh') }}</span>
                    @if ($school->established_year)
                        <span class="school-type"><i class="bi bi-calendar3"></i>{{ __('Established :year', ['year' => $school->established_year]) }}</span>
                    @endif
                    @if ($school->is_active)
                        <span class="school-type"><i class="bi bi-check-circle"></i>{{ __('Active') }}</span>
                    @else
                        <span class="school-type"><i class="bi bi-pause-circle"></i>{{ __('Inactive') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <div style="display:grid;grid-template-columns:minmax(0,1.65fr) minmax(280px,1fr);gap:20px;align-items:start;" class="school-show-grid">
        <div class="dashboard-stack">
            <section class="ui-card p-lg">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">{{ __('Contact & location') }}</h2>
                        <p class="card-subtitle">{{ __('Official communication details for the institution.') }}</p>
                    </div>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <i class="bi bi-envelope"></i>
                        <div><span class="label">{{ __('Email') }}</span><span class="value">{{ $school->email ?: __('Not provided') }}</span></div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-telephone"></i>
                        <div><span class="label">{{ __('Phone') }}</span><span class="value">{{ $school->phone ?: __('Not provided') }}</span></div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-telephone-plus"></i>
                        <div><span class="label">{{ __('Alternate phone') }}</span><span class="value">{{ $school->phone_alt ?: __('Not provided') }}</span></div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-globe2"></i>
                        <div>
                            <span class="label">{{ __('Website') }}</span>
                            <span class="value">
                                @if ($school->website)
                                    <a href="{{ $school->website }}" target="_blank" rel="noopener noreferrer">{{ $school->website }}</a>
                                @else
                                    {{ __('Not provided') }}
                                @endif
                            </span>
                        </div>
                    </div>
                    <div class="info-item" style="grid-column:1 / -1;">
                        <i class="bi bi-geo-alt"></i>
                        <div><span class="label">{{ __('Address') }}</span><span class="value">{{ $school->address ?: __('Not provided') }}</span></div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-pin-map"></i>
                        <div><span class="label">{{ __('Postcode') }}</span><span class="value">{{ $school->postcode ?: __('Not provided') }}</span></div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-translate"></i>
                        <div><span class="label">{{ __('Default language') }}</span><span class="value">{{ $school->locale === 'bn' ? __('বাংলা') : __('English') }}</span></div>
                    </div>
                </div>
            </section>

            <section class="ui-card p-lg">
                <div class="card-head">
                    <div>
                        <h2 class="card-title">{{ __('Institution details') }}</h2>
                        <p class="card-subtitle">{{ __('Settings that shape reports, IDs and financial records.') }}</p>
                    </div>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <i class="bi bi-hash"></i>
                        <div><span class="label">{{ __('School code') }}</span><span class="value">{{ $school->code }}</span></div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-card-text"></i>
                        <div><span class="label">{{ __('EIIN') }}</span><span class="value">{{ $school->eiin ?: __('Not provided') }}</span></div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-clock"></i>
                        <div><span class="label">{{ __('Timezone') }}</span><span class="value">{{ $school->timezone }}</span></div>
                    </div>
                    <div class="info-item">
                        <i class="bi bi-currency-exchange"></i>
                        <div><span class="label">{{ __('Currency') }}</span><span class="value">{{ $school->currency }}</span></div>
                    </div>
                    <div class="info-item" style="grid-column:1 / -1;border-bottom:0;">
                        <i class="bi bi-quote"></i>
                        <div><span class="label">{{ __('Motto') }}</span><span class="value">{{ $school->motto ?: __('No motto added yet.') }}</span></div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="dashboard-stack">
            <section class="ui-card p-lg">
                <div class="card-head mb-3">
                    <div>
                        <h2 class="card-title">{{ __('At a glance') }}</h2>
                        <p class="card-subtitle">{{ __('Live counts for this school.') }}</p>
                    </div>
                </div>
                <div class="mini-stats">
                    <div class="mini-stat"><span class="value">{{ number_format($school->students_count) }}</span><span class="label">{{ __('Students') }}</span></div>
                    <div class="mini-stat"><span class="value">{{ number_format($school->teachers_count) }}</span><span class="label">{{ __('Teachers') }}</span></div>
                    <div class="mini-stat"><span class="value">{{ number_format($school->employees_count) }}</span><span class="label">{{ __('Staff') }}</span></div>
                    <div class="mini-stat"><span class="value">{{ number_format($school->academic_sessions_count) }}</span><span class="label">{{ __('Sessions') }}</span></div>
                    <div class="mini-stat"><span class="value">{{ number_format($school->classes_count) }}</span><span class="label">{{ __('Classes') }}</span></div>
                    <div class="mini-stat"><span class="value">{{ number_format($school->users_count) }}</span><span class="label">{{ __('Users') }}</span></div>
                </div>
            </section>

            <section class="ui-card p-lg">
                <h2 class="card-title mb-2">{{ __('Accountability') }}</h2>
                <p class="card-subtitle mb-3">{{ __('Profile changes are recorded with actor, IP address and timestamp.') }}</p>
                @can('audit.view')
                    <a href="{{ route('audit.index') }}" class="btn btn-light-soft w-100">
                        <i class="bi bi-clock-history"></i>{{ __('Open audit trail') }}
                    </a>
                @endcan
                <div class="text-muted-2 mt-3" style="font-size:.75rem;">
                    <i class="bi bi-calendar-plus me-1"></i>{{ __('Created :date', ['date' => $school->created_at?->format('j M Y')]) }}
                </div>
            </section>
        </aside>
    </div>
@endsection
