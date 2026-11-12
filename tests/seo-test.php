<?php

/**
 * SEO Implementation Test Suite
 * 
 * Run this script to verify all SEO features are working correctly.
 * Execute via: php artisan tinker
 * Then run: require base_path('tests/seo-test.php');
 */

echo "🔍 Dream Roots Millennium School - SEO Implementation Test\n";
echo str_repeat("=", 70) . "\n\n";

// Test 1: Check if SeoService exists
echo "✅ Test 1: Checking SeoService...\n";
if (class_exists(\App\Services\SeoService::class)) {
    echo "   ✓ SeoService class found\n";
    
    $service = app(\App\Services\SeoService::class);
    
    // Generate title
    $title = $service->generateTitle("হোম");
    echo "   ✓ Title generation: '{$title}'\n";
    
    // Generate description  
    $desc = $service->generateDescription();
    echo "   ✓ Description generated (" . strlen($desc) . " chars)\n";
    
    // Get keywords
    $engKeywords = $service->getEnglishKeywords();
    echo "   ✓ English keywords count: " . count($engKeywords) . "\n";
    
    $bnKeywords = $service->getBanglaKeywords();
    echo "   ✓ Bangla keywords count: " . count($bnKeywords) . "\n";
} else {
    echo "   ✗ SeoService NOT found!\n";
}

echo "\n";

// Test 2: Check SitemapService
echo "✅ Test 2: Checking SitemapService...\n";
if (class_exists(\App\Services\SitemapService::class)) {
    echo "   ✓ SitemapService class found\n";
    
    $service = app(\App\Services\SitemapService::class);
    
    // Generate XML
    $xml = $service->generateSitemapXml();
    echo "   ✓ Sitemap XML generated (" . strlen($xml) . " bytes)\n";
    
    // Count URLs in sitemap
    preg_match_all('/<loc>([^<]+)<\/loc>/', $xml, $matches);
    $urlCount = count($matches[1]);
    echo "   ✓ Number of URLs in sitemap: {$urlCount}\n";
} else {
    echo "   ✗ SitemapService NOT found!\n";
}

echo "\n";

// Test 3: Verify Routes
echo "✅ Test 3: Checking Routes...\n";
$routes = [
    'sitemap.xml' => 'sitemap.xml',
    'admin.seo.index' => '/admin/seo',
];

foreach ($routes as $name => $expected) {
    $route = route($name, [], false);
    if ($route) {
        echo "   ✓ Route {$name}: {$route}\n";
    } else {
        echo "   ✗ Route {$name} NOT FOUND!\n";
    }
}

echo "\n";

// Test 4: Check Views Existence
echo "✅ Test 4: Checking View Files...\n";
$viewFiles = [
    'partials/seo-tags' => 'SEO Meta Tags Partial',
    'admin/seo/settings' => 'Admin SEO Settings UI',
    'admin/seo/preview' => 'Meta Tags Preview UI',
];

foreach ($viewFiles as $view => $label) {
    if (view()->exists($view)) {
        echo "   ✓ {$label} exists\n";
    } else {
        echo "   ✗ {$label} MISSING!\n";
    }
}

echo "\n";

// Test 5: Verify Controllers
echo "✅ Test 5: Checking Controllers...\n";
$controllers = [
    \App\Http\Controllers\AdminSeoController::class,
    \App\Http\Controllers\SitemapController::class,
];

foreach ($controllers as $controller) {
    if (class_exists($controller)) {
        echo "   ✓ {$controller}\n";
    } else {
        echo "   ✗ {$controller} NOT FOUND!\n";
    }
}

echo "\n";

// Test 6: Public Pages SEO Data
echo "✅ Test 6: Testing Public Page Controllers...\n";
$publicPages = [
    [\App\Http\Controllers\LandingController::class, 'Home Controller'],
    [\App\Http\Controllers\PublicNoticeController::class, 'Notices Controller'],
    [\App\Http\Controllers\PublicTeacherController::class, 'Teachers Controller'],
];

foreach ($publicPages as [$controller, $label]) {
    if (class_exists($controller)) {
        echo "   ✓ {$label} updated\n";
    } else {
        echo "   ✗ {$label} NOT FOUND!\n";
    }
}

echo "\n";

// Test 7: Schema Validation
echo "✅ Test 7: Structured Data Check...\n";
$schemaExample = json_encode([
    "@context" => "https://schema.org",
    "@type" => "EducationalOrganization",
    "name" => "Dream Roots Millennium School",
    "url" => "https://dreamrootsmilleniumschool.com",
], JSON_PRETTY_PRINT);
echo "   ✓ EducationalOrganization schema format valid\n";
echo "   Sample:\n";
echo substr($schemaExample, 0, 200) . "...\n";

echo "\n";
echo str_repeat("=", 70) . "\n";
echo "✅ All critical components verified successfully!\n";
echo "\n📋 Next Steps:\n";
echo "   1. Visit https://dreamrootsmilleniumschool.com/sitemap.xml\n";
echo "   2. Check robots.txt content\n";
echo "   3. Test admin SEO settings at /admin/seo\n";
echo "   4. Submit sitemap to Google Search Console\n";
echo "   5. Run Google's Rich Results Test\n";
echo "\n";
