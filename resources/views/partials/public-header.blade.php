@php
    $schoolName = $school?->name ?? config('app.name');
    $initials = $school?->initials() ?? 'EN';
    $motto = $school?->motto ?: 'জ্ঞান | নৈতিকতা | উন্নত ভবিষ্যৎ';
    $location = implode(', ', array_filter([$school?->upazila, $school?->district]));
    $social = $school?->settings?->keyBy('key');
    $facebookUrl = $social?->get('facebook_url')?->value;
    $youtubeUrl = $social?->get('youtube_url')?->value;

    $navItems = [
        ['key' => 'home', 'label' => 'হোম', 'url' => route('home')],
        ['key' => 'about', 'label' => 'আমাদের সম্পর্কে', 'url' => route('home').'#about'],
        ['key' => 'subjects', 'label' => 'বিষয়সমূহ', 'url' => route('home').'#subjects'],
        ['key' => 'classes', 'label' => 'শ্রেণি', 'url' => route('home').'#classes'],
        ['key' => 'notices', 'label' => 'নোটিস বোর্ড', 'url' => route('public.notices.index')],
        ['key' => 'admission', 'label' => 'ভর্তি', 'url' => route('home').'#admission'],
        ['key' => 'results', 'label' => 'ফলাফল', 'url' => route('public.results.index')],
        ['key' => 'routines', 'label' => 'রুটিন ও ক্যালেন্ডার', 'url' => route('public.routines.index')],
        ['key' => 'teachers', 'label' => 'শিক্ষকবৃন্দ', 'url' => route('public.teachers.index')],
        ['key' => 'contact', 'label' => 'যোগাযোগ', 'url' => route('home').'#contact'],
    ];
    $activeNav = $activeNav ?? null;
@endphp
<div class="pub-topbar">
    <div class="container d-flex align-items-center justify-content-between gap-3">
        <div class="pub-topbar-left d-none d-md-flex align-items-center gap-3">
            @if (filled($location))
                <span><i class="bi bi-geo-alt-fill"></i>{{ $location }}</span>
            @endif
            @if (filled($school?->phone))
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $school->phone) }}"><i class="bi bi-telephone-fill"></i>{{ $school->phone }}</a>
            @endif
            @if (filled($school?->email))
                <a href="mailto:{{ $school->email }}"><i class="bi bi-envelope-fill"></i>{{ $school->email }}</a>
            @endif
        </div>
        <div class="d-flex d-md-none align-items-center gap-3">
            @if (filled($school?->phone))
                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $school->phone) }}"><i class="bi bi-telephone-fill"></i></a>
            @endif
        </div>
        <div class="pub-topbar-right d-flex align-items-center gap-3">
            <button type="button" class="pub-topbar-install" data-pwa-install-trigger>
                <i class="bi bi-download"></i><span>{{ __('pwa.install_app') }}</span>
            </button>
            <a href="{{ route('login') }}" class="pub-topbar-login"><i class="bi bi-person-circle"></i>লগইন</a>
            @if (filled($facebookUrl))
                <a href="{{ $facebookUrl }}" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
            @endif
            @if (filled($youtubeUrl))
                <a href="{{ $youtubeUrl }}" target="_blank" rel="noopener noreferrer" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
            @endif
        </div>
    </div>
</div>
<header class="pub-header">
    <div class="container d-flex align-items-center justify-content-between gap-3">
        <a class="pub-brand" href="{{ route('home') }}">
            <span class="pub-brand-mark">
                @if ($school?->logoUrl())
                    <img src="{{ $school->logoUrl() }}" alt="">
                @else
                    {{ $initials }}
                @endif
            </span>
            <span class="pub-brand-copy">
                <strong>{{ $schoolName }}</strong>
                <small>{{ $motto }}</small>
            </span>
        </a>

        <button class="pub-nav-toggle d-lg-none" type="button" data-pub-menu-toggle aria-expanded="false" aria-controls="pub-nav-menu" aria-label="মেনু খুলুন">
            <i class="bi bi-list"></i>
        </button>

        <nav class="pub-nav d-none d-lg-block" aria-label="প্রধান মেনু">
            @foreach ($navItems as $item)
                <a href="{{ $item['url'] }}" @if ($activeNav === $item['key']) class="is-active" aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach
        </nav>
    </div>
    <nav class="pub-nav pub-nav-mobile d-lg-none" id="pub-nav-menu" aria-label="মোবাইল মেনু" hidden>
        @foreach ($navItems as $item)
            <a href="{{ $item['url'] }}" @if ($activeNav === $item['key']) class="is-active" aria-current="page" @endif>{{ $item['label'] }}</a>
        @endforeach
        <button type="button" class="pub-nav-install" data-pwa-install-trigger>
            <i class="bi bi-download"></i>{{ __('pwa.install_app') }}
        </button>
    </nav>
</header>
