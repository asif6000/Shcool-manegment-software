@props([
    'label',
    'value' => null,
    'formatted' => null,
    'icon' => 'bi-people',
    'tone' => 'brand',
    'trend' => null,
    'trendLabel' => null,
    'suffix' => '',
])

@php
    $toneClass = in_array($tone, ['teal', 'amber', 'purple', 'sky', 'danger'], true) ? 'is-'.$tone : '';
    $trendClass = $trend === null || (float) $trend === 0.0
        ? 'is-flat'
        : ((float) $trend > 0 ? 'is-up' : 'is-down');
@endphp

<article class="kpi-card">
    <span class="kpi-icon {{ $toneClass }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
    <div class="kpi-copy">
        <p class="kpi-label">{{ $label }}</p>
        <p class="kpi-value">
            @if ($formatted !== null)
                {{ $formatted }}
            @else
                {{ number_format((int) $value) }}@if ($suffix !== '')<span class="stat-suffix">{{ $suffix }}</span>@endif
            @endif
        </p>
    </div>
    <div class="kpi-foot">
        @if ($trend !== null)
            <span class="kpi-trend {{ $trendClass }}">
                <i class="bi {{ (float) $trend > 0 ? 'bi-arrow-up-short' : ((float) $trend < 0 ? 'bi-arrow-down-short' : 'bi-dash') }}"></i>{{ (float) $trend > 0 ? '+' : '' }}{{ number_format((float) $trend, 1) }}%
            </span>
        @endif
        <span>{{ $trendLabel }}</span>
    </div>
</article>
