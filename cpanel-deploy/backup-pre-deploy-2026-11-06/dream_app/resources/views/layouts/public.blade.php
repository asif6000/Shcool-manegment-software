@php
    $schoolName = $school?->name ?? config('app.name');
@endphp
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $schoolName) — {{ $schoolName }}</title>
    <meta name="description" content="@yield('metaDescription', $schoolName.' — আধুনিক শিক্ষা, নৈতিক মূল্যবোধ ও সুষ্ঠু পরিবেশে গড়ে উঠুক আগামী প্রজন্ম।')">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/landing.css') }}?v=8">
    <link rel="stylesheet" href="{{ asset('assets/css/bengali.css') }}?v=1">
    @include('partials.pwa-head')
</head>
<body class="pub">
    @include('partials.public-header')

    @hasSection('page-banner')
        <section class="pub-page-banner">
            <div class="container">
                <h1>@yield('page-banner')</h1>
                <nav class="pub-page-banner-links" aria-label="ব্রেডক্রাম্ব">
                    <a href="{{ route('home') }}"><i class="bi bi-house-door"></i> হোম</a>
                    @yield('page-banner-extra')
                </nav>
            </div>
        </section>
    @endif

    <main class="pub-main">
        @yield('content')
    </main>

    @include('partials.public-footer')

    <script src="{{ asset('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script>
        (function () {
            var toggle = document.querySelector('[data-pub-menu-toggle]');
            var menu = document.getElementById('pub-nav-menu');

            if (!toggle || !menu) {
                return;
            }

            toggle.addEventListener('click', function () {
                var open = menu.hasAttribute('hidden');
                if (open) {
                    menu.removeAttribute('hidden');
                } else {
                    menu.setAttribute('hidden', '');
                }
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        })();
    </script>
    @stack('scripts')
    @include('partials.pwa-ui')
</body>
</html>
