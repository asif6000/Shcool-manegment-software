@extends('layouts.app')

@section('title', 'SEO Settings')

@section('content')
<div class="container-fluid">
    <div class="page-header mb-4">
        <h1><i class="bi bi-search"></i> SEO Settings</h1>
        <p class="text-muted">Manage your school website SEO configuration</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.seo.update') }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-file-text"></i> Website Meta Tags</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="website_title" class="form-label">Website Title</label>
                        <input type="text" 
                               name="website_title" 
                               id="website_title" 
                               class="form-control @error('website_title') is-invalid @enderror"
                               value="{{ old('website_title', $seoSettings['website_title']) }}"
                               placeholder="Dream Roots Millennium School">
                        <small class="text-muted">Default title shown in browser tabs (max 100 chars)</small>
                        @error('website_title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="website_description" class="form-label">Meta Description</label>
                        <textarea name="website_description" 
                                  id="website_description" 
                                  rows="3"
                                  class="form-control @error('website_description') is-invalid @enderror"
                                  placeholder="School description for search engines...">{{ old('website_description', $seoSettings['website_description']) }}</textarea>
                        <small class="text-muted">Recommended length: 120-160 characters</small>
                        @error('website_description')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-image"></i> Social Media Preview Image</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="og_image" class="form-label">Open Graph / Twitter Card Image URL</label>
                    <input type="url" 
                           name="og_image" 
                           id="og_image" 
                           class="form-control @error('og_image') is-invalid @enderror"
                           value="{{ old('og_image', $seoSettings['og_image']) }}"
                           placeholder="https://dreamrootsmilleniumschool.com/path/to/image.jpg">
                    <small class="text-muted">Image will be used when sharing links on Facebook, Twitter, etc.</small>
                    @error('og_image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-google"></i> Google Search Console Verification</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="google_verification" class="form-label">Google Site Verification Code</label>
                    <input type="text" 
                           name="google_verification" 
                           id="google_verification" 
                           class="form-control @error('google_verification') is-invalid @enderror"
                           value="{{ old('google_verification', $seoSettings['google_verification']) }}"
                           placeholder="google-site-verification=xxxxx">
                    <small class="text-muted">Paste the meta tag code provided by Google Search Console here</small>
                    @error('google_verification')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle"></i> Save Changes
            </button>
            <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
// Auto-resize textarea based on content
document.getElementById('website_description').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 200) + 'px';
});
</script>
@endpush
