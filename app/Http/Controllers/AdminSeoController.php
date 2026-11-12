<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class AdminSeoController extends Controller
{
    /**
     * Show SEO settings page (admin only)
     */
    public function index(): View
    {
        $this->authorize('manage-seo') ?? abort(403, 'Unauthorized');

        return view('admin.seo.settings', [
            'seoSettings' => $this->getSeoSettings(),
        ]);
    }

    /**
     * Update SEO settings
     */
    public function update(Request $request)
    {
        $this->authorize('manage-seo') ?? abort(403, 'Unauthorized');

        $validated = $request->validate([
            'website_title' => 'nullable|string|max:100',
            'website_description' => 'nullable|string|max:500',
            'og_image' => 'nullable|url|max:255',
            'google_verification' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated) {
            $this->saveSetting('website_seo_title', $validated['website_title'] ?? config('app.name'));
            $this->saveSetting('website_seo_description', $validated['website_description'] ?? '');
            $this->saveSetting('website_seo_og_image', $validated['og_image'] ?? '');
            $this->saveSetting('google_site_verification', $validated['google_verification'] ?? '');
        });

        return redirect()->route('admin.seo.index')
            ->with('success', 'SEO settings updated successfully!');
    }

    /**
     * Get current SEO settings
     */
    protected function getSeoSettings(): array
    {
        return [
            'website_title' => Setting::value('website_seo_title', config('app.name')),
            'website_description' => Setting::value('website_seo_description', ''),
            'og_image' => Setting::value('website_seo_og_image', ''),
            'google_verification' => Setting::value('google_site_verification', ''),
        ];
    }

    /**
     * Save setting helper
     */
    protected function saveSetting(string $key, string $value): void
    {
        Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => 'string',
                'group_name' => 'seo',
            ]
        );
    }

    /**
     * Preview meta tags
     */
    public function previewMetaTags()
    {
        $this->authorize('manage-seo') ?? abort(403, 'Unauthorized');

        return view('admin.seo.preview', [
            'seoSettings' => $this->getSeoSettings(),
        ]);
    }
}
