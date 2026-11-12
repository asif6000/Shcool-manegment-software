@extends('layouts.public')

@section('title', $notice->title)
@section('page-banner', 'নোটিস বোর্ড')
@section('page-banner-extra')
    <span class="pub-page-banner-current">নোটিস বিস্তারিত</span>
@endsection

@section('content')
    <section class="pub-section">
        <div class="container">
            <article class="pub-article">
                <h1>{{ $notice->title }}</h1>
                <p class="pub-article-meta">
                    <i class="bi bi-calendar3"></i>
                    প্রকাশ: {{ ($notice->published_at ?? $notice->created_at)?->format('d F Y') }}
                    @if ($notice->expires_at)
                        · <i class="bi bi-hourglass-split"></i> মেয়াদ: {{ $notice->expires_at->format('d F Y') }}
                    @endif
                </p>
                <div class="pub-article-body">
                    {!! nl2br(e($notice->body)) !!}
                </div>
                <a href="{{ route('public.notices.index') }}" class="btn pub-btn-soft mt-4"><i class="bi bi-arrow-left"></i> সব নোটিস দেখুন</a>
            </article>
        </div>
    </section>
@endsection
