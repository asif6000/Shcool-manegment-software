@props(['status'])

@php
    $map = [
        'present' => ['is-present', 'bi-check2-circle', 'Present'],
        'absent' => ['is-absent', 'bi-x-circle', 'Absent'],
        'late' => ['is-late', 'bi-clock-history', 'Late'],
        'leave' => ['is-leave', 'bi-airplane', 'Leave'],
        'excused' => ['is-excused', 'bi-shield-check', 'Excused'],
    ];

    [$class, $icon, $label] = $map[$status] ?? ['is-neutral', 'bi-dash-circle', ucfirst((string) $status)];
@endphp

<span class="status-chip {{ $class }}">
    <i class="bi {{ $icon }}"></i>{{ __($label) }}
</span>
