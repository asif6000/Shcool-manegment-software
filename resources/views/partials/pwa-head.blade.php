@php
    $pwaVersion = (string) config('pwa.version', '1.3.0');
    $pwaAppName = (string) config('pwa.short_name', 'DRMS');
@endphp
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ config('pwa.theme_color', '#2563eb') }}">
<meta name="application-name" content="{{ $pwaAppName }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ $pwaAppName }}">
<link rel="apple-touch-icon" href="{{ asset('icons/drms-touch-180.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('icons/drms-192.png') }}">
<link rel="icon" type="image/png" sizes="512x512" href="{{ asset('icons/drms-512.png') }}">
<link rel="stylesheet" href="{{ asset('assets/css/pwa.css') }}?v={{ $pwaVersion }}">
<meta name="pwa-version" content="{{ $pwaVersion }}">
<meta name="pwa-sw-url" content="{{ asset('sw.js') }}">
<meta name="pwa-version-url" content="{{ route('pwa.version') }}">
<meta name="pwa-logout-url" content="{{ route('logout') }}">
<meta name="pwa-push-unsubscribe-url" content="{{ route('notifications.push.unsubscribe') }}">
<meta name="pwa-csrf" content="{{ csrf_token() }}">
@auth
    <meta name="pwa-user" content="{{ auth()->id() }}">
@endauth
