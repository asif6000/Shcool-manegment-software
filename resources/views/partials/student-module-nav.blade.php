@php
    $navItems = $navItems ?? ($moduleNav ?? []);
@endphp

<div class="portal-nav-bar">
    <span class="portal-menu-current" data-portal-current aria-live="polite">
        <i class="bi bi-grid-1x2-fill"></i>
        <span class="label">{{ __('Dashboard') }}</span>
    </span>
</div>

<nav class="module-grid" aria-label="{{ __('Student modules') }}">
    @foreach ($navItems as $key => $item)
        <button type="button"
                class="module-card tone-{{ $item['tone'] ?? 'blue' }} {{ $loop->first ? 'is-active' : '' }}"
                data-module-target="{{ $key }}"
                aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                @if ($loop->first) aria-current="true" @endif>
            <span class="module-card-icon" aria-hidden="true">
                <i class="bi {{ $item['icon'] }}"></i>
            </span>
            <span class="module-card-label">{{ $item['label'] }}</span>
        </button>
    @endforeach
</nav>
