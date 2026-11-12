@php
    $social = $school?->settings?->keyBy('key');
    $socialUrl = fn (string $key): ?string => filled($social?->get($key)?->value) ? (string) $social->get($key)->value : null;
    $whatsappUrl = $socialUrl('whatsapp_url')
        ?? (filled($school?->phone) ? 'https://wa.me/'.preg_replace('/[^0-9]/', '', (string) $school->phone) : null);

    $socialLinks = array_values(array_filter([
        ['label' => 'Facebook', 'icon' => 'bi-facebook', 'class' => 'is-facebook', 'url' => $socialUrl('facebook_url')],
        ['label' => 'YouTube', 'icon' => 'bi-youtube', 'class' => 'is-youtube', 'url' => $socialUrl('youtube_url')],
        ['label' => 'Instagram', 'icon' => 'bi-instagram', 'class' => 'is-instagram', 'url' => $socialUrl('instagram_url')],
        ['label' => 'WhatsApp', 'icon' => 'bi-whatsapp', 'class' => 'is-whatsapp', 'url' => $whatsappUrl],
        ['label' => 'LinkedIn', 'icon' => 'bi-linkedin', 'class' => 'is-linkedin', 'url' => $socialUrl('linkedin_url')],
    ], fn (array $link): bool => filled($link['url'])));

    $quickLinks = [
        ['label' => 'Home', 'url' => route('home')],
        ['label' => 'About Us', 'url' => route('home').'#about'],
        ['label' => 'Subjects', 'url' => route('home').'#subjects'],
        ['label' => 'Admissions', 'url' => route('home').'#admission'],
        ['label' => 'Notice Board', 'url' => route('public.notices.index')],
        ['label' => 'Contact Us', 'url' => route('home').'#contact'],
    ];

    $accessLinks = [
        ['label' => 'Login', 'icon' => 'bi-person-fill', 'url' => route('login')],
        ['label' => 'Student Portal', 'icon' => 'bi-mortarboard-fill', 'url' => route('student.dashboard')],
        ['label' => 'Teacher Portal', 'icon' => 'bi-person-badge-fill', 'url' => route('dashboard')],
        ['label' => 'Guardian Portal', 'icon' => 'bi-people-fill', 'url' => route('login')],
    ];
@endphp
<footer class="pub-footer">
    <div class="container">
        <div class="pub-footer-grid">
            <div class="pub-footer-col pub-footer-brand-col">
                <div class="pub-footer-brand">
                    <span class="pub-footer-logo">
                        @if ($school?->logoUrl())
                            <img src="{{ $school->logoUrl() }}" alt="">
                        @else
                            {{ $school?->initials() ?? 'EN' }}
                        @endif
                    </span>
                    <span class="pub-footer-rule" aria-hidden="true"></span>
                    <div class="pub-footer-identity">
                        <strong>{{ $school?->name ?? config('app.name') }}</strong>
                        <span class="pub-footer-tagline">{{ $school?->motto ?: 'Learn, Grow & Shine' }}</span>
                    </div>
                </div>

                <p class="pub-footer-about">আধুনিক শিক্ষা, নৈতিক মূল্যবোধ এবং সৃজনশীল মানুষ গড়ার লক্ষ্যে আমাদের প্রতিশ্রুতি।</p>

                @if (filled($socialLinks))
                    <div class="pub-footer-social">
                        @foreach ($socialLinks as $link)
                            <a href="{{ $link['url'] }}" class="pub-social {{ $link['class'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $link['label'] }}">
                                <i class="bi {{ $link['icon'] }}"></i>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <nav class="pub-footer-col pub-footer-links" aria-labelledby="pub-footer-quick-links">
                <h2 class="pub-footer-title" id="pub-footer-quick-links"><i class="bi bi-link-45deg"></i>Quick Links</h2>
                <ul>
                    @foreach ($quickLinks as $link)
                        <li><a href="{{ $link['url'] }}"><i class="bi bi-chevron-right"></i>{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <nav class="pub-footer-col pub-footer-access" aria-labelledby="pub-footer-access">
                <h2 class="pub-footer-title" id="pub-footer-access"><i class="bi bi-people-fill"></i>Student &amp; User Access</h2>
                <ul>
                    @foreach ($accessLinks as $link)
                        <li><a href="{{ $link['url'] }}"><i class="bi {{ $link['icon'] }}"></i>{{ $link['label'] }}</a></li>
                    @endforeach
                </ul>
            </nav>
        </div>

        <div class="pub-footer-bottom">
            <span>&copy; {{ now()->year }} {{ $school?->name ?? config('app.name') }}, সর্বস্বত্ব সংরক্ষিত।</span>
            <span aria-hidden="true">|</span>
            <span>ডিজাইন ও ডেভেলপমেন্ট: আপনার প্রতিষ্ঠান</span>
        </div>
    </div>
</footer>
