<?php

namespace App\Services;

use Illuminate\Support\Facades\Request;

class SitemapService
{
    /**
     * Get all public URLs to include in sitemap
     */
    public function getPublicUrls(): array
    {
        $baseUrl = 'https://dreamrootsmilleniumschool.com';
        
        // Define public routes (no authentication required)
        $publicRoutes = [
            ['/' => 1],                           // Home
            ['/notices' => 0.8],                 // Notices board
            ['/notices/{id}' => 0.7],           // Single notice
            ['/teachers' => 0.7],               // Teachers list
            ['/results' => 0.6],                // Results
            ['/routines' => 0.5],               // Routines
            ['/website/live' => 0.3],           // Live feed
        ];

        $urls = [];

        foreach ($publicRoutes as $route => $priority) {
            if (strpos($route, '{') === false) {
                $urls[] = [
                    'loc' => $baseUrl . $route,
                    'priority' => $priority ?: 0.5,
                    'changefreq' => $this->getChangeFrequency($route),
                ];
            }
        }

        return $urls;
    }

    /**
     * Get change frequency based on route type
     */
    protected function getChangeFrequency(string $route): string
    {
        $frequencyMap = [
            '/' => 'daily',
            '/notices' => 'hourly',
            '/notices/' => 'weekly',
            '/teachers' => 'monthly',
            '/results' => 'weekly',
            '/routines' => 'monthly',
            '/website/live' => 'hourly',
        ];

        return $frequencyMap[$route] ?? 'monthly';
    }

    /**
     * Generate sitemap XML content
     */
    public function generateSitemapXml(): string
    {
        $urls = $this->getPublicUrls();
        
        // Add notices dynamically if we can access them
        if (class_exists('\App\Models\Notice')) {
            $notices = \App\Models\Notice::query()
                ->published()
                ->public()
                ->orderByDesc('published_at')
                ->limit(100)
                ->get();

            foreach ($notices as $notice) {
                $urls[] = [
                    'loc' => url("/notices/{$notice->id}"),
                    'lastmod' => $notice->updated_at->format('c'),
                    'changefreq' => 'weekly',
                    'priority' => 0.7,
                ];
            }
        }

        // Generate XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($urls as $urlData) {
            $xml .= '  <url>' . PHP_EOL;
            
            if (isset($urlData['loc'])) {
                $xml .= "    <loc>{$urlData['loc']}</loc>" . PHP_EOL;
            }
            
            if (isset($urlData['lastmod'])) {
                $xml .= "    <lastmod>{$urlData['lastmod']}</lastmod>" . PHP_EOL;
            } elseif (isset($urlData['updated_at'])) {
                $xml .= "    <lastmod>{$urlData['updated_at']->format('c')}</lastmod>" . PHP_EOL;
            }
            
            if (isset($urlData['changefreq'])) {
                $xml .= "    <changefreq>{$urlData['changefreq']}</changefreq>" . PHP_EOL;
            }
            
            if (isset($urlData['priority'])) {
                $xml .= "    <priority>{$urlData['priority']}</priority>" . PHP_EOL;
            } else {
                $xml .= "    <priority>0.5</priority>" . PHP_EOL;
            }
            
            $xml .= '  </url>' . PHP_EOL;
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Get last modified date from model
     */
    public function getLastModified(\Illuminate\Database\Eloquent\Model $model): string
    {
        if ($model->exists && method_exists($model, 'updated_at')) {
            return $model->updated_at->format('c');
        }

        return (new \DateTime())->format('c');
    }

    /**
     * Check if URL should be indexed
     */
    public function shouldIndexUrl(string $url): bool
    {
        $excludedPatterns = [
            '/^\/admin/',
            '/^\/student/',
            '/^\/teacher/',
            '/^\/guardian/',
            '/^\/accountant/',
            '/^\/login/',
            '/^\/register/',
            '/^\/password/',
            '/\/api\/',
            '/\.json$/',
        ];

        foreach ($excludedPatterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return false;
            }
        }

        return true;
    }
}
