{{-- SEO Meta Tags --}}
@php
    $seoService = app(\App\Services\SeoService::class);
    $schoolName = $school?->name ?? config('app.name');
    $coverImage = $school?->cover_image ?? asset('assets/img/landing/hero-campus.jpg');
    
    // Collect SEO data from page or use defaults
    $pageTitle = isset($seoTitle) ? $seoTitle : '';
    if (empty($pageTitle)) {
        $pageTitle = view()->yieldContent('title', '');
    }
    
    $description = isset($seoDescription) ? $seoDescription : '';
    if (empty($description)) {
        $description = view()->yieldContent('metaDescription', '');
    }
    
    $canonicalUrl = isset($seoCanonical) ? $seoCanonical : $seoService->generateCanonicalUrl();
    
    $openGraph = $seoService->generateOpenGraph('website');
    $twitterCard = $seoService->generateTwitterCard();
@endphp

<!-- Canonical URL -->
<link rel="canonical" href="{{ $canonicalUrl }}">

<!-- Open Graph Metadata -->
<meta property="og:title" content="{{ $openGraph['og:title'] }}">
<meta property="og:description" content="{{ $openGraph['og:description'] }}">
<meta property="og:image" content="{{ $openGraph['og:image'] }}">
<meta property="og:url" content="{{ $openGraph['og:url'] }}">
<meta property="og:type" content="{{ $openGraph['og:type'] }}">
<meta property="og:site_name" content="{{ $openGraph['og:site_name'] }}">
<meta property="og:locale" content="{{ $openGraph['og:locale'] }}">

@if(str_contains(Request::path(), 'notice'))
    <meta property="og:type" content="article">
@endif

@if(str_contains(Request::path(), 'teacher'))
    <meta property="og:type" content="website">
@endif

@if(str_contains(Request::path(), 'result'))
    <meta property="og:type" content="website">
@endif

<!-- Twitter Card Metadata -->
<meta name="twitter:card" content="{{ $twitterCard['twitter:card'] }}">
<meta name="twitter:title" content="{{ $twitterCard['twitter:title'] }}">
<meta name="twitter:description" content="{{ $twitterCard['twitter:description'] }}">
<meta name="twitter:image" content="{{ $twitterCard['twitter:image'] }}">
<meta name="twitter:site" content="{{ $twitterCard['twitter:site'] }}">

<!-- Additional Keywords (if not already in description) -->
@if(isset($useKeywords) && $useKeywords !== false)
<meta name="keywords" content="{{ collect(array_merge(
    $seoService->getEnglishKeywords(),
    $seoService->getBanglaKeywords()
))->unique()->implode(', ') }}">
@endif

<!-- JSON-LD Structured Data for School -->
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@type": "EducationalOrganization",
    "name": "{{ $schoolName }}",
    "url": "{{ $canonicalUrl }}",
    "logo": "{{ $coverImage }}",
    "address": {
        "@type": "PostalAddress",
        "streetAddress": "{{ $school?->address ?? '' }}",
        "addressLocality": "{{ $school?->upazila ?? '' }}",
        "addressRegion": "{{ $school?->district ?? 'Kushtia' }}",
        "postalCode": "",
        "addressCountry": "BD"
    },
    "telephone": "{{ $school?->phone ?? '' }}",
    "email": "{{ $school?->email ?? '' }}",
    "url": "{{ $canonicalUrl }}"
}
</script>
