@php
    $compact = $compact ?? false;
    $initials = \Illuminate\Support\Str::upper(
        \Illuminate\Support\Str::substr($student->first_name ?? '', 0, 1).
        \Illuminate\Support\Str::substr($student->last_name ?? '', 0, 1)
    );
    $school = $student->school;
    $guardian = $student->primaryGuardian();
    $photo = $student->photoUrl();
    $photoVersion = $student->photoVersion();

    if ($photo !== null && $photoVersion !== null) {
        $photo .= '?v='.$photoVersion;
    }
@endphp

<article class="id-card">
    <header class="id-card-head">
        <span class="id-emblem">
            @if ($school?->logoUrl())
                <img src="{{ $school->logoUrl() }}" alt="">
            @else
                {{ $school?->initials() ?: 'DRMS' }}
            @endif
        </span>
        <span class="id-school">
            <strong>{{ $school?->name ?? config('app.name') }}</strong>
            <small>{{ collect([
                filled($school?->eiin) ? 'EIIN '.$school->eiin : null,
                $school?->upazila,
                $school?->district,
            ])->filter()->join(' · ') }}</small>
        </span>
        <span class="id-kind">{{ __('Identity card') }}</span>
    </header>

    <div class="id-card-body">
        <div class="id-photo">
            @if ($photo !== null)
                <img src="{{ $photo }}" alt="">
            @else
                <span>{{ $initials !== '' ? $initials : '·' }}</span>
            @endif
        </div>

        <div class="id-fields">
            <div class="id-field id-field-wide">
                <span class="k">{{ __('Name') }}</span>
                <span class="v">{{ $student->full_name }}</span>
            </div>
            <div class="id-field">
                <span class="k">{{ __('Class') }}</span>
                <span class="v">{{ $enrollment?->schoolClass?->name ?? '—' }}</span>
            </div>
            <div class="id-field">
                <span class="k">{{ __('Section') }}</span>
                <span class="v">{{ $enrollment?->section?->name ?? '—' }}</span>
            </div>
            <div class="id-field">
                <span class="k">{{ __('Roll') }}</span>
                <span class="v">{{ $enrollment?->roll_no ?? '—' }}</span>
            </div>
            <div class="id-field">
                <span class="k">{{ __('Student ID') }}</span>
                <span class="v">{{ $student->student_id }}</span>
            </div>
            <div class="id-field">
                <span class="k">{{ __('Date of birth') }}</span>
                <span class="v">{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</span>
            </div>
            <div class="id-field">
                <span class="k">{{ __('Blood') }}</span>
                <span class="v">{{ $student->blood_group ?: '—' }}</span>
            </div>
            <div class="id-field">
                <span class="k">{{ __('Guardian') }}</span>
                <span class="v">{{ $guardian?->name ?? '—' }}</span>
            </div>
            <div class="id-field">
                <span class="k">{{ __('Phone') }}</span>
                <span class="v">{{ $guardian?->phone ?: $student->phone ?: '—' }}</span>
            </div>
            @if (! $compact)
                <div class="id-field id-field-wide">
                    <span class="k">{{ __('Address') }}</span>
                    <span class="v">{{ $student->present_address ?: '—' }}</span>
                </div>
            @endif
        </div>

        <div class="id-code">
            <div class="id-qr" data-qr="{{ $verifyUrl }}" role="img" aria-label="{{ __('Verification code') }}"></div>
            <span>{{ __('Scan to verify') }}</span>
        </div>
    </div>

    <footer class="id-card-foot">
        <span>{{ __('Session') }}: {{ $enrollment?->academicSession?->name ?? now()->format('Y') }}</span>
        @if ($enrollment?->academicSession?->end_date)
            <span>{{ __('Valid till') }} {{ $enrollment->academicSession->end_date->format('d M Y') }}</span>
        @endif
        <span class="id-sign"><em>{{ __('Headmaster') }}</em></span>
    </footer>
</article>
