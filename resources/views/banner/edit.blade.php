@extends('layouts.app')

@section('title', __('Homepage banner'))

@push('head')
    <style>
        .banner-preview {
            border-radius: var(--radius-lg, 14px);
            overflow: hidden;
            background: #0f223f;
            aspect-ratio: 16 / 6;
        }

        .banner-preview img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>
@endpush

@section('content')
    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-image"></i>{{ __('Website') }}</span>
            <h1 class="page-title">{{ __('Homepage banner') }}</h1>
            <p class="page-subtitle">{{ __('The picture shown behind the headline on the public home page.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('schools.show') }}" class="btn btn-light-soft"><i class="bi bi-building"></i>{{ __('School profile') }}</a>
        </div>
    </div>

    <section class="ui-card p-lg">
        <div class="card-head">
            <div>
                <h2 class="card-title">{{ __('Banner image') }}</h2>
                <p class="card-subtitle">{{ __('JPEG, PNG or WebP · up to 4 MB. A wide photo (1600 × 600 or larger) looks best.') }}</p>
            </div>
            @if ($bannerUrl)
                <span class="badge-soft is-success"><span class="dot"></span>{{ __('Custom banner') }}</span>
            @else
                <span class="badge-soft is-info"><span class="dot"></span>{{ __('Default banner') }}</span>
            @endif
        </div>

        <div class="banner-preview mb-4">
            <img src="{{ $bannerUrl ?? asset('assets/img/landing/hero-campus.jpg') }}" alt="{{ __('Homepage banner preview') }}">
        </div>

        <form method="POST" action="{{ route('banner.update') }}" enctype="multipart/form-data" class="row g-3">
            @csrf
            <div class="col-md-8">
                <label class="form-label" for="banner-file">{{ __('Banner image') }}<span class="required">*</span></label>
                <input id="banner-file" type="file" name="banner" accept="image/jpeg,image/png,image/webp"
                       class="form-control @error('banner') is-invalid @enderror" required>
                @error('banner')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-hint">{{ __('Uploading a new picture replaces the current banner immediately.') }}</div>
            </div>
            <div class="col-md-4 d-flex align-items-start">
                <button type="submit" class="btn btn-primary w-100 mt-md-4"><i class="bi bi-upload"></i>{{ __('Upload banner') }}</button>
            </div>
        </form>

        @if ($bannerUrl)
            <hr class="my-4">
            <form method="POST" action="{{ route('banner.destroy') }}" class="d-flex align-items-center justify-content-between flex-wrap gap-2"
                  data-confirm="{{ __('Remove the custom banner and go back to the default hero images?') }}">
                @csrf
                @method('DELETE')
                <p class="text-muted-2 mb-0" style="font-size:.82rem;">{{ __('Removing the banner restores the two default campus photos.') }}</p>
                <button type="submit" class="btn btn-light-soft text-danger"><i class="bi bi-trash3"></i>{{ __('Remove banner') }}</button>
            </form>
        @endif
    </section>
@endsection
