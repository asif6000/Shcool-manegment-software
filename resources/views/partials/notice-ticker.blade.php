@php
    $tickerNotices = collect($notices ?? []);
@endphp

<section class="pub-ticker" data-ticker aria-label="জরুরি নোটিশ">
    <div class="pub-ticker-inner">
        <span class="pub-ticker-label">
            <i class="bi bi-bell-fill" aria-hidden="true"></i>
            <span>জরুরি নোটিশ</span>
        </span>

        @if ($tickerNotices->isEmpty())
            <p class="pub-ticker-empty">এই মুহূর্তে কোনো নোটিশ প্রকাশিত হয়নি।</p>
        @else
            <div class="pub-ticker-viewport" data-ticker-viewport role="region" aria-label="চলমান নোটিশ" tabindex="0">
                <div class="pub-ticker-track" data-ticker-track>
                    @foreach ([0, 1] as $pass)
                        <div class="pub-ticker-group" data-ticker-group @if ($pass === 1) aria-hidden="true" @endif>
                            @foreach ($tickerNotices as $notice)
                                <a class="pub-ticker-item {{ $notice['pinned'] ? 'is-urgent' : '' }}" data-ticker-item
                                   href="{{ $notice['url'] }}" @if ($pass === 1) tabindex="-1" @endif>
                                    @if ($notice['pinned'])
                                        <span class="pub-ticker-badge">জরুরি</span>
                                    @endif
                                    <span>{{ $notice['title'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pub-ticker-side">
                <button type="button" class="pub-ticker-btn" data-ticker-toggle hidden
                        aria-label="নোটিশ স্ক্রল থামান" aria-pressed="false">
                    <i class="bi bi-pause-fill" data-ticker-icon aria-hidden="true"></i>
                </button>
                <span class="pub-ticker-dots" data-ticker-dots role="group" aria-label="নোটিশ নির্বাচন"></span>
            </div>
        @endif

        <a class="pub-ticker-all" href="{{ route('public.notices.index') }}" aria-label="সব নোটিশ দেখুন">
            <span class="pub-ticker-all-text">সব নোটিশ দেখুন</span>
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</section>
