<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Request;

class SeoService
{
    protected ?School $school = null;

    public function __construct()
    {
        // Get current school from context
        if (function_exists('school')) {
            try {
                $this->school = school();
            } catch (\Throwable $e) {
                $this->school = null;
            }
        }

        if ($this->school === null) {
            try {
                $this->school = School::first();
            } catch (\Throwable $e) {
                $this->school = null;
            }
        }
    }

    /**
     * Generate global SEO title
     */
    public function generateTitle(?string $pageTitle = null): string
    {
        $schoolName = $this->school?->name ?? config('app.name');

        if ($pageTitle) {
            return "{$pageTitle} — {$schoolName}";
        }

        return $schoolName;
    }

    /**
     * Generate dynamic meta description based on page type
     */
    public function generateDescription(?string $customDescription = null): string
    {
        $schoolName = $this->school?->name ?? config('app.name');
        
        if ($customDescription) {
            return trim($customDescription);
        }

        // Default descriptions based on common routes
        $addressParts = array_values(array_unique(array_filter([
            $this->school?->address,
            $this->school?->upazila,
            $this->school?->district,
            $this->school?->division,
        ])));
        
        $address = implode(', ', $addressParts);

        return "Dream Roots Millennium School is located at {$address}, Kushtia, Bangladesh. " .
               "Discover our school, teachers, admissions, educational activities and student facilities.";
    }

    /**
     * Generate canonical URL
     */
    public function generateCanonicalUrl(): string
    {
        $url = Request::fullUrl();
        
        // Remove hash fragments
        $url = explode('#', $url)[0];
        
        // Ensure HTTPS if in production
        if (config('app.env') === 'production' && !str_starts_with($url, 'https://')) {
            $url = str_replace('http://', 'https://', $url);
        }
        
        return $url;
    }

    /**
     * Get full URL with domain
     */
    public function getFullUrl(?string $path = null): string
    {
        if ($path && (str_starts_with($path, 'http://') || str_starts_with($path, 'https://'))) {
            return $path;
        }

        $domain = 'https://dreamrootsmilleniumschool.com';
        
        if ($path && !str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        
        return $domain . ($path ?? Request::path());
    }

    /**
     * Generate Open Graph metadata
     */
    public function generateOpenGraph(string $type = 'website'): array
    {
        $schoolName = $this->school?->name ?? config('app.name');
        $coverImage = $this->school?->cover_image ?? asset('assets/img/landing/hero-campus.jpg');
        
        return [
            'og:title' => $this->generateTitle(),
            'og:description' => $this->generateDescription(),
            'og:image' => $this->getFullUrl($coverImage),
            'og:url' => $this->generateCanonicalUrl(),
            'og:type' => $type,
            'og:site_name' => $schoolName,
            'og:locale' => 'bn_BD',
        ];
    }

    /**
     * Generate Twitter Card metadata
     */
    public function generateTwitterCard(): array
    {
        $schoolName = $this->school?->name ?? config('app.name');
        $coverImage = $this->school?->cover_image ?? asset('assets/img/landing/hero-campus.jpg');
        
        return [
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $this->generateTitle(),
            'twitter:description' => $this->generateDescription(),
            'twitter:image' => $this->getFullUrl($coverImage),
            'twitter:site' => '@dreamrootsms',
        ];
    }

    /**
     * Get Bangla keywords for school
     */
    public function getBanglaKeywords(): array
    {
        return [
            'ড্রিম রুটস মিলেনিয়াম স্কুল',
            'কুষ্টিয়া স্কুল',
            'হরিপুর স্কুল',
            'বোয়ালদাহ মোড় স্কুল',
            'কুষ্টিয়া স্কুল ভর্তি',
            'হরিপুরে স্কুল',
        ];
    }

    /**
     * Get English keywords for school
     */
    public function getEnglishKeywords(): array
    {
        return [
            'Dream Roots Millennium School',
            'School in Kushtia',
            'School in Horipur',
            'School near Boaldah More',
            'Kushtia School Admission',
            'Haripur School Admission',
        ];
    }
}
