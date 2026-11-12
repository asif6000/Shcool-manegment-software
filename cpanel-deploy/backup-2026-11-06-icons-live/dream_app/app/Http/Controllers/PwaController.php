<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class PwaController extends Controller
{
    public function manifest(): Response
    {
        $base = rtrim(url('/'), '/').'/';
        $icon192 = asset('icons/icon-192.png');
        $icon512 = asset('icons/icon-512.png');

        $manifest = [
            'id' => $base,
            'name' => (string) config('pwa.app_name'),
            'short_name' => (string) config('pwa.short_name'),
            'description' => (string) config('pwa.description'),
            'start_url' => $this->routeUrl((string) config('pwa.start_route', 'login'), $base),
            'scope' => $base,
            'display' => 'standalone',
            'display_override' => ['window-controls-overlay', 'standalone', 'minimal-ui'],
            'orientation' => 'any',
            'background_color' => (string) config('pwa.background_color'),
            'theme_color' => (string) config('pwa.theme_color'),
            'lang' => str_replace('_', '-', (string) app()->getLocale()),
            'dir' => 'ltr',
            'categories' => ['education', 'productivity'],
            'prefer_related_applications' => false,
            'icons' => [
                [
                    'src' => $icon192,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $icon512,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => asset('icons/maskable-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => asset('icons/maskable-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'shortcuts' => [
                [
                    'name' => __('Notices'),
                    'short_name' => __('Notices'),
                    'description' => __('School notices and announcements'),
                    'url' => route('public.notices.index'),
                    'icons' => [['src' => $icon192, 'sizes' => '192x192', 'type' => 'image/png']],
                ],
                [
                    'name' => __('Results'),
                    'short_name' => __('Results'),
                    'description' => __('Published results'),
                    'url' => route('public.results.index'),
                    'icons' => [['src' => $icon192, 'sizes' => '192x192', 'type' => 'image/png']],
                ],
            ],
        ];

        return response(
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            200,
            [
                'Content-Type' => 'application/manifest+json; charset=utf-8',
                'Cache-Control' => 'public, max-age=300',
            ],
        );
    }

    public function version(): JsonResponse
    {
        return response()->json([
            'version' => (string) config('pwa.version'),
            'manifest_url' => route('pwa.manifest'),
            'service_worker_url' => asset('sw.js'),
        ], 200, [
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function routeUrl(string $routeName, string $fallback): string
    {
        return Route::has($routeName) ? route($routeName) : $fallback;
    }
}
