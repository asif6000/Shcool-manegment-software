@php
    // A page supplies @section('crumbs'); when it does not, its title is used.
    // Multi-level trails are separated with " / ".
    $crumbSource = trim($__env->yieldContent('crumbs'))
        ?: trim($__env->yieldContent('title'));

    $crumbTrail = $crumbSource === ''
        ? []
        : array_values(array_filter(array_map('trim', explode('/', $crumbSource))));
@endphp

@if (count($crumbTrail) > 0)
    <nav class="portal-crumbs" aria-label="{{ __('Breadcrumb') }}">
        <a class="portal-crumb" href="{{ route('student.dashboard') }}">
            <i class="bi bi-house-door"></i><span>{{ __('Overview') }}</span>
        </a>

        @foreach ($crumbTrail as $index => $crumb)
            <i class="bi bi-chevron-right portal-crumb-sep" aria-hidden="true"></i>

            @if ($index === count($crumbTrail) - 1)
                <span class="portal-crumb is-current" aria-current="page">{{ $crumb }}</span>
            @else
                <span class="portal-crumb">{{ $crumb }}</span>
            @endif
        @endforeach
    </nav>
@endif
