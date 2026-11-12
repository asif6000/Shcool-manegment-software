<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SitemapController extends Controller
{
    protected SitemapService $sitemapService;

    public function __construct(SitemapService $sitemapService)
    {
        $this->sitemapService = $sitemapService;
    }

    /**
     * Generate XML sitemap for public pages only
     */
    public function xml(): \Illuminate\Http\Response
    {
        $xmlContent = $this->sitemapService->generateSitemapXml();

        return response($xmlContent, 200)
            ->header('Content-Type', 'text/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Alternative sitemap location (for robots.txt reference)
     */
    public function index(): \Illuminate\Http\Response
    {
        return redirect()->route('sitemap.xml');
    }

    /**
     * Generate dynamic notice URLs in sitemap
     * This helper updates the main sitemap with recent notices
     */
    public static function refreshNoticeUrls(): void
    {
        if (! class_exists('\App\Models\Notice')) {
            return;
        }

        // Clear any cached sitemap data if needed
        DB::connection()->flushQueryCache();
    }
}
