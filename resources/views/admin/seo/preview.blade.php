@extends('layouts.app')

@section('title', 'SEO Meta Preview')

@section('content')
<div class="container-fluid">
    <div class="page-header mb-4">
        <h1><i class="bi bi-eye"></i> SEO Meta Tags Preview</h1>
        <p class="text-muted">See how your site will appear in search results and social media</p>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-google"></i> Google Search Result Preview</h5>
                </div>
                <div class="card-body">
                    <div class="search-result-preview">
                        <div class="breadcrumb-url">
                            https://dreamrootsmilleniumschool.com › home
                        </div>
                        <h3 class="search-title" style="color: #1a0dab; font-size: 20px; margin: 8px 0;">
                            @php $seoService = app(\App\Services\SeoService::class); echo $seoService->generateTitle(); @endphp
                        </h3>
                        <p class="search-description" style="color: #545454; line-height: 1.58;">
                            @php echo htmlspecialchars($seoService->generateDescription()); @endphp
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-facebook"></i> Facebook / Open Graph Preview</h5>
                </div>
                <div class="card-body">
                    <div class="social-preview">
                        <div class="social-image" style="background-color: #f0f2f5; border-radius: 8px; height: 300px; display: flex; align-items: center; justify-content: center; color: #999;">
                            <!-- In real implementation, show the OG image here -->
                            <span>{{ $seoSettings['og_image'] ?: 'Image Preview' }}</span>
                        </div>
                        <div class="social-meta" style="margin-top: 12px;">
                            <h4 style="margin: 0 0 4px 0; font-size: 16px;">
                                @php $seoService = app(\App\Services\SeoService::class); echo $seoService->generateTitle(); @endphp
                            </h4>
                            <span style="color: #65676b; font-size: 14px;">dreamrootsmilleniumschool.com</span>
                            <p style="color: #1c1e21; font-size: 14px; margin: 8px 0 0 0;">
                                @php echo $seoService->generateDescription(); @endphp
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-braces"></i> Current Settings</h5>
        </div>
        <div class="card-body">
            <table class="table table-bordered">
                <tbody>
                    <tr>
                        <th style="width: 200px;">Website Title</th>
                        <td>{{ $seoSettings['website_title'] }}</td>
                    </tr>
                    <tr>
                        <th>Meta Description</th>
                        <td style="word-break: break-word;">{{ $seoSettings['website_description'] ?: '(not set)' }}</td>
                    </tr>
                    <tr>
                        <th>OG Image URL</th>
                        <td>{{ $seoSettings['og_image'] ?: '(not set - uses default)' }}</td>
                    </tr>
                    <tr>
                        <th>Google Verification</th>
                        <td>{{ $seoSettings['google_verification'] ? str_repeat('*', strlen($seoSettings['google_verification'])) : '(not set)' }}</td>
                    </tr>
                </tbody>
            </table>
            
            <div class="mt-3 p-3 bg-light rounded">
                <strong>Note:</strong> This is a preview only. To update settings, go to 
                <a href="{{ route('admin.seo.index') }}">Admin → SEO Settings</a>.
            </div>
        </div>
    </div>
</div>

<style>
.search-result-preview {
    padding: 16px 8px;
}
.breadcrumb-url {
    color: #202124;
    font-family: Arial, sans-serif;
    font-size: 14px;
    margin-bottom: 4px;
}
.search-description {
    font-family: Arial, sans-serif;
    font-size: 14px;
}
.social-preview {
    max-width: 500px;
}
</style>
@endsection
