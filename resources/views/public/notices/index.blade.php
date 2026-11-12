@php
    $bnMonths = [1 => 'জানুয়ারি', 2 => 'ফেব্রুয়ারি', 3 => 'মার্চ', 4 => 'এপ্রিল', 5 => 'মে', 6 => 'জুন', 7 => 'জুলাই', 8 => 'আগস্ট', 9 => 'সেপ্টেম্বর', 10 => 'অক্টোবর', 11 => 'নভেম্বর', 12 => 'ডিসেম্বর'];
@endphp
@extends('layouts.public')

@section('title', $seoTitle ?? 'নোটিস বোর্ড')
@push('metaDescription', $seoDescription ?? '')
@section('page-banner', 'নোটিস বোর্ড')
@section('page-banner-extra')
    <span class="pub-page-banner-current">নোটিস তালিকা</span>
@endsection

@section('content')
    <section class="pub-section">
        <div class="container">
            <div class="row g-3">
                @forelse ($notices as $notice)
                    @php($publishedAt = $notice->published_at ?? $notice->created_at)
                    <div class="col-12">
                        <a class="pub-notice-row pub-notice-row-lg" href="{{ route('public.notices.show', $notice->id) }}">
                            <span class="pub-notice-date">
                                <strong>{{ $publishedAt?->day }}</strong>
                                <small>{{ $bnMonths[(int) $publishedAt?->month] ?? '' }} {{ $publishedAt?->year }}</small>
                            </span>
                            <span class="pub-notice-text">
                                <strong>
                                    @if ($notice->is_pinned)
                                        <span class="pub-badge-pin"><i class="bi bi-pin-angle-fill"></i> গুরুত্বপূর্ণ</span>
                                    @endif
                                    {{ $notice->title }}
                                </strong>
                                <small>{{ $publishedAt?->diffForHumans() }}</small>
                            </span>
                            <i class="bi bi-chevron-right pub-notice-chevron"></i>
                        </a>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="pub-panel">
                            <div class="pub-panel-body">
                                <p class="pub-empty mb-0">এখনো কোনো নোটিস প্রকাশিত হয়নি।</p>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $notices->links() }}
            </div>
        </div>
    </section>
@endsection
