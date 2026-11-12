@php
    $schoolName = $school?->name ?? config('app.name');
    $initials = $school?->initials() ?? 'EN';

    // Applying requires an account, so a visitor who has not signed up yet is
    // sent to registration rather than a sign-in screen they cannot use.
    $applyUrl = auth()->check() ? route('applicant.apply') : route('register');

    $addressParts = array_values(array_unique(array_filter([
        $school?->address,
        $school?->upazila,
        $school?->district,
        $school?->division,
    ])));
    $address = implode(', ', $addressParts);

    $quickCards = [
        ['icon' => 'bi-house-door', 'color' => 'blue', 'label' => 'হোম', 'sub' => 'প্রধান পৃষ্ঠা', 'url' => route('home')],
        ['icon' => 'bi-info-circle', 'color' => 'green', 'label' => 'আমাদের সম্পর্কে', 'sub' => 'স্কুল পরিচিতি', 'url' => route('home').'#about'],
        ['icon' => 'bi-bell', 'color' => 'orange', 'label' => 'নোটিস বোর্ড', 'sub' => 'সর্বশেষ বিজ্ঞপ্তি', 'url' => route('public.notices.index')],
        ['icon' => 'bi-person-plus', 'color' => 'purple', 'label' => 'অনলাইন ভর্তি', 'sub' => 'ভর্তি আবেদন করুন', 'url' => $applyUrl],
        ['icon' => 'bi-mortarboard', 'color' => 'teal', 'label' => 'ফলাফল', 'sub' => 'রেজাল্ট দেখুন', 'url' => route('public.results.index')],
        ['icon' => 'bi-calendar3', 'color' => 'pink', 'label' => 'রুটিন ও ক্যালেন্ডার', 'sub' => 'ক্লাস রুটিন', 'url' => route('public.routines.index')],
        ['icon' => 'bi-people', 'color' => 'indigo', 'label' => 'শিক্ষকবৃন্দ', 'sub' => 'শিক্ষক তালিকা', 'url' => route('public.teachers.index')],
        ['icon' => 'bi-telephone', 'color' => 'sky', 'label' => 'যোগাযোগ', 'sub' => 'ঠিকানা ও ফোন', 'url' => route('home').'#contact'],
    ];

    $quickLinks = [
        ['icon' => 'bi-pencil-square', 'label' => 'অনলাইন ভর্তি আবেদন', 'url' => $applyUrl],
        ['icon' => 'bi-search', 'label' => 'ফলাফল দেখুন', 'url' => route('public.results.index')],
        ['icon' => 'bi-megaphone', 'label' => 'নোটিস বোর্ড', 'url' => route('public.notices.index')],
        ['icon' => 'bi-people', 'label' => 'শিক্ষকবৃন্দ', 'url' => route('public.teachers.index')],
        ['icon' => 'bi-calendar3', 'label' => 'রুটিন ও ক্যালেন্ডার', 'url' => route('public.routines.index')],
        ['icon' => 'bi-book-half', 'label' => 'পঠিত বিষয়', 'url' => route('home').'#subjects'],
        ['icon' => 'bi-telephone', 'label' => 'যোগাযোগ', 'url' => route('home').'#contact'],
    ];

    // The school teaches these two beyond the ordinary academic subjects, so
    // they get their own cards. Everything else is read from the subject master
    // below, and anything already featured here is dropped from that list so a
    // subject never appears twice on the page.
    $featuredSubjects = [
        [
            'icon' => 'bi-book-half',
            'color' => 'green',
            'name' => 'আরবি',
            'en' => 'Arabic',
            'note' => 'কুরআন পাঠ, আরবি ব্যাকরণ ও দ্বিনি শিক্ষা।',
            'keys' => ['আরবি', 'arabic'],
        ],
        [
            'icon' => 'bi-code-slash',
            'color' => 'indigo',
            'name' => 'প্রোগ্রামিং',
            'en' => 'Programming',
            'note' => 'কম্পিউটারের মূল ধারণা থেকে কোড লেখা পর্যন্ত।',
            'keys' => ['প্রোগ্রামিং', 'programming'],
        ],
    ];

    $featuredKeys = [];
    foreach ($featuredSubjects as $featured) {
        foreach ($featured['keys'] as $key) {
            $featuredKeys[] = mb_strtolower(trim($key));
        }
    }

    $isFeatured = function (?string ...$names) use ($featuredKeys): bool {
        foreach ($names as $name) {
            if (filled($name) && in_array(mb_strtolower(trim($name)), $featuredKeys, true)) {
                return true;
            }
        }

        return false;
    };

    $subjectRows = $subjects->reject(fn (array $subject) => $isFeatured($subject['name']));
@endphp
@extends('layouts.public')

@section('title', $seoTitle ?? 'হোম')

@push('metaDescription', $seoDescription ?? '')

@section('content')
    @include('partials.notice-ticker', ['notices' => $tickerNotices])

    <section class="pub-quickbar" aria-label="দ্রুত লিংক">
        <div class="container">
            <div class="pub-quickbar-inner">
                <span class="pub-quickbar-title"><i class="bi bi-link-45deg"></i> দ্রুত লিংক</span>
                <ul class="pub-quickbar-list">
                    @foreach ($quickLinks as $link)
                        <li>
                            <a class="pub-quickbar-link" href="{{ $link['url'] }}">
                                <i class="bi {{ $link['icon'] }}"></i>
                                <span>{{ $link['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="pub-hero">
        @if (! empty($heroImage))
            <img src="{{ $heroImage }}" class="pub-hero-image" alt="{{ $schoolName }}">
        @else
            <div id="pub-hero-slider" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="5000">
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <img src="{{ asset('assets/img/landing/hero-campus.jpg') }}" class="d-block w-100" alt="{{ $schoolName }} ক্যাম্পাস">
                    </div>
                    <div class="carousel-item">
                        <img src="{{ asset('assets/img/landing/hero-classroom.jpg') }}" class="d-block w-100" alt="{{ $schoolName }} ক্লাসরুম">
                    </div>
                </div>
                <div class="carousel-indicators pub-hero-dots">
                    <button type="button" data-bs-target="#pub-hero-slider" data-bs-slide-to="0" class="active" aria-label="স্লাইড ১"></button>
                    <button type="button" data-bs-target="#pub-hero-slider" data-bs-slide-to="1" aria-label="স্লাইড ২"></button>
                </div>
            </div>
        @endif

        <div class="pub-hero-overlay">
            <div class="container">
                <div class="pub-hero-caption">
                    <h1>শিক্ষাই জাতির উন্নয়ন</h1>
                    <p>{{ $schoolName }} — আধুনিক শিক্ষা, নৈতিক মূল্যবোধ ও সুষ্ঠু পরিবেশে গড়ে উঠুক আগামী প্রজন্ম।</p>
                    <div class="pub-hero-actions">
                        <button type="button" class="btn pub-btn-light btn-lg" data-pwa-install-trigger>
                            <i class="bi bi-download"></i> {{ __('pwa.install_app') }}
                        </button>
                        <a href="{{ route('home').'#admission' }}" class="btn pub-btn-primary btn-lg"><i class="bi bi-mortarboard"></i> ভর্তি তথ্য দেখুন</a>
                        <a href="{{ route('home').'#about' }}" class="btn pub-btn-outline-light btn-lg"><i class="bi bi-info-circle"></i> আমাদের সম্পর্কে</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pub-section pub-quick-cards">
        <div class="container">
            <div class="row g-3">
                @foreach ($quickCards as $card)
                    <div class="col-6 col-md-3 col-xl-3">
                        <a class="pub-quick-card" href="{{ $card['url'] }}">
                            <span class="pub-quick-card-icon pub-icon-{{ $card['color'] }}"><i class="bi {{ $card['icon'] }}"></i></span>
                            <strong>{{ $card['label'] }}</strong>
                            <small>{{ $card['sub'] }}</small>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="pub-stats-band" aria-label="স্কুলের লাইভ পরিসংখ্যান">
        <div class="container">
            <div class="pub-stats">
                <div class="pub-stat">
                    <strong data-live-stat="students">{{ $stats['students'] }}</strong>
                    <span>শিক্ষার্থী</span>
                </div>
                <div class="pub-stat">
                    <strong data-live-stat="teachers">{{ $stats['teachers'] }}</strong>
                    <span>শিক্ষক</span>
                </div>
                <div class="pub-stat">
                    <strong data-live-stat="classes">{{ $stats['classes'] }}</strong>
                    <span>শ্রেণি</span>
                </div>
                <div class="pub-stat">
                    <strong data-live-stat="subjects">{{ $stats['subjects'] }}</strong>
                    <span>বিষয়</span>
                </div>
            </div>
            <p class="pub-stats-note">
                <span class="pub-live-dot" aria-hidden="true"></span>
                <span>তথ্য সরাসরি স্কুল সিস্টেম থেকে আসছে</span>
                <span class="pub-stats-updated invisible" data-live-updated>সর্বশেষ আপডেট <time data-live-updated-time></time></span>
            </p>
        </div>
    </section>

    <section class="pub-section pt-0" id="app">
        <div class="container">
            <div class="pub-app-banner" data-pwa-app>
                <img class="pub-app-banner-icon" src="{{ asset('icons/drms-192.png') }}" alt="DRMS অ্যাপ আইকন" width="88" height="88" loading="lazy">
                <div class="pub-app-banner-copy">
                    <span class="pub-app-banner-eyebrow"><i class="bi bi-phone"></i> মোবাইল অ্যাপ</span>
                    <h2>DRMS অ্যাপ ইনস্টল করুন</h2>
                    <p>হোম স্ক্রিন থেকে এক ট্যাপে নোটিস, ফলাফল ও যোগাযোগ। ইন্টারনেট বন্ধ থাকলেও অ্যাপটি খুলবে।</p>
                </div>
                <div class="pub-app-banner-action">
                    <button type="button" class="btn pub-btn-primary btn-lg" data-pwa-install-open>
                        <i class="bi bi-download"></i> {{ __('pwa.install_app') }}
                    </button>
                    <span class="pub-app-banner-hint">Android, iPhone ও কম্পিউটারে ইনস্টলযোগ্য</span>
                </div>
            </div>
        </div>
    </section>

    <section class="pub-section pt-0">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="pub-panel h-100" id="notices">
                        <div class="pub-panel-head pub-panel-blue">
                            <i class="bi bi-megaphone"></i> সর্বশেষ নোটিস
                            <a href="{{ route('public.notices.index') }}" class="pub-panel-head-link">সব দেখুন <i class="bi bi-arrow-right"></i></a>
                        </div>
                        <div class="pub-panel-body" id="pub-live-notices" data-live-notices>
                            @forelse ($notices as $notice)
                                <a class="pub-notice-row" href="{{ $notice['url'] }}">
                                    <span class="pub-notice-date">
                                        <strong>{{ $notice['day'] }}</strong>
                                        <small>{{ $notice['month'] }}</small>
                                    </span>
                                    <span class="pub-notice-text">
                                        <strong>{{ $notice['title'] }}</strong>
                                        <small>{{ $notice['ago'] }}</small>
                                    </span>
                                    <i class="bi bi-chevron-right pub-notice-chevron"></i>
                                </a>
                            @empty
                                <p class="pub-empty" data-live-notices-empty>এখনো কোনো নোটিস প্রকাশিত হয়নি।</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="pub-panel h-100">
                        <div class="pub-panel-head pub-panel-green">
                            <i class="bi bi-mortarboard"></i> ফলাফল অনুসন্ধান
                        </div>
                        <div class="pub-panel-body">
                            @if ($exams->isNotEmpty() && $sessions->isNotEmpty())
                                <form method="GET" action="{{ route('public.results.index') }}" class="pub-result-form">
                                    <select name="exam_id" class="form-select" required>
                                        <option value="" selected disabled>পরীক্ষার নাম নির্বাচন করুন</option>
                                        @foreach ($exams as $exam)
                                            <option value="{{ $exam->id }}">{{ $exam->name }}</option>
                                        @endforeach
                                    </select>
                                    <select name="session_id" class="form-select" required>
                                        <option value="" selected disabled>শিক্ষাবর্ষ নির্বাচন করুন</option>
                                        @foreach ($sessions as $session)
                                            <option value="{{ $session->id }}">{{ $session->name }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="roll_no" class="form-control" placeholder="রোল নম্বর লিখুন" required>
                                    <button type="submit" class="btn pub-btn-green w-100"><i class="bi bi-search"></i> ফলাফল দেখুন</button>
                                </form>
                            @else
                                <p class="pub-empty">পরীক্ষার ফলাফল প্রকাশিত হলে এখান থেকে খুঁজে দেখতে পারবেন।</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="pub-panel pub-panel-flush h-100">
                        <img src="{{ asset('assets/img/landing/goals-classroom.jpg') }}" class="pub-panel-img" alt="আমাদের লক্ষ্য">
                        <div class="pub-panel-body">
                            <p class="pub-quote">“ভালো শিক্ষা গড়বে সুন্দর ভবিষ্যৎ”</p>
                            <h3 class="pub-h3">আমাদের লক্ষ্য</h3>
                            <p class="pub-muted">শিক্ষার্থীদের মাঝে জ্ঞান, নৈতিকতা, দেশপ্রেম ও মানবিক মূল্যবোধ অঙ্কুর করে একটি আদর্শ নাগরিক হিসেবে গড়ে তোলা।</p>
                            <a href="{{ route('home').'#about' }}" class="btn pub-btn-primary">বিস্তারিত জানুন <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="pub-section pub-section-tint" id="about">
        <div class="container">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <span class="pub-kicker">আমাদের সম্পর্কে</span>
                    <h2 class="pub-h2">{{ $schoolName }}</h2>
                    <p class="pub-muted">
                        {{ $schoolName }}-এ আধুনিক শিক্ষা, নৈতিক মূল্যবোধ ও সুষ্ঠু পরিবেশে শিক্ষার্থীদের গড়ে তোলার লক্ষ্যে
                        অভিজ্ঞ ও দক্ষ শিক্ষকমণ্ডলী নিরলসভাবে কাজ করছেন। ভর্তি, উপস্থিতি, ফি ও ফলাফলের সব তথ্য এখন
                        অনলাইনে — একই নিরাপদ প্ল্যাটফর্মে।
                    </p>
                    <div class="pub-about-actions">
                        <a href="{{ $applyUrl }}" class="btn pub-btn-primary"><i class="bi bi-pencil-square"></i> ভর্তির আবেদন করুন</a>
                        <a href="{{ route('public.teachers.index') }}" class="btn pub-btn-soft">শিক্ষকমণ্ডলী দেখুন</a>
                    </div>
                </div>
                <div class="col-lg-5">
                    <ul class="pub-facts">
                        @if ($school?->established_year)
                            <li><span>প্রতিষ্ঠিত</span><strong>{{ $school->established_year }} খ্রি.</strong></li>
                        @endif
                        @if ($school?->eiin)
                            <li><span>EIIN</span><strong>{{ $school->eiin }}</strong></li>
                        @endif
                        @if (filled($address))
                            <li><span>ঠিকানা</span><strong>{{ $address }}</strong></li>
                        @endif
                        @if (filled($school?->phone))
                            <li><span>ফোন</span><strong>{{ $school->phone }}</strong></li>
                        @endif
                        @if (filled($school?->email))
                            <li><span>ইমেইল</span><strong>{{ $school->email }}</strong></li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <section class="pub-section pub-section-tint" id="teachers">
        <div class="container">
            <div class="row align-items-end g-3 mb-4">
                <div class="col-lg-8">
                    <span class="pub-kicker">শিক্ষকমণ্ডলী</span>
                    <h2 class="pub-h2 mb-2">আমাদের অভিজ্ঞ শিক্ষকবৃন্দ</h2>
                    <p class="pub-muted mb-0">
                        জ্ঞান, নৈতিকতা ও সহানুভূতির মাধ্যমে শিক্ষার্থীদের গড়ে তুলতে আমাদের শিক্ষকরা প্রতিদিন কাজ করেন।
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="{{ route('public.teachers.index') }}" class="btn pub-btn-soft">
                        সব শিক্ষক দেখুন <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>

            <div class="row g-3">
                @forelse ($teachers as $teacher)
                    <div class="col-md-6 col-lg-3">
                        <article class="pub-teacher-card" role="button" data-bs-toggle="modal" data-bs-target="#teacherModal_{{ $teacher->id }}" title="প্রোফাইল দেখতে ক্লিক করুন">
                            <span class="pub-teacher-avatar">
                                @if ($photo = $teacher->publicPhotoUrl())
                                    <img src="{{ $photo }}" alt="{{ $teacher->full_name }}" loading="lazy">
                                @else
                                    {{ mb_substr($teacher->first_name, 0, 1) }}
                                @endif
                            </span>
                            <div class="pub-teacher-info">
                                <strong>{{ $teacher->full_name }}</strong>
                                <span class="pub-teacher-designation">
                                    <i class="bi bi-briefcase"></i> {{ $teacher->designation ?: 'শিক্ষক' }}
                                </span>
                                @if ($teacher->qualification)
                                    <span class="pub-teacher-meta">
                                        <i class="bi bi-mortarboard"></i> {{ $teacher->qualification }}
                                    </span>
                                @endif
                            </div>
                        </article>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="pub-panel">
                            <div class="pub-panel-body">
                                <p class="pub-empty mb-0">শিক্ষক তালিকা শীঘ্রই প্রকাশ করা হবে।</p>
                            </div>
                        </div>
                    </div>
                @endforelse
            </div>

            {{-- Teacher Profile Modals --}}
            @foreach ($teachers as $teacher)
                <div class="modal fade" id="teacherModal_{{ $teacher->id }}" tabindex="-1" aria-labelledby="teacherModalLabel_{{ $teacher->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden;">
                            <div class="modal-header text-white" style="background: linear-gradient(135deg, #0d6efd, #052c65); padding: 1.25rem 1.5rem;">
                                <h5 class="modal-title d-flex align-items-center gap-2" id="teacherModalLabel_{{ $teacher->id }}">
                                    <i class="bi bi-person-badge"></i> শিক্ষক পরিচিতি
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-4 text-center">
                                <div class="mb-3 d-inline-block position-relative">
                                    <span class="pub-teacher-avatar mx-auto shadow" style="width: 100px; height: 100px; font-size: 2.2rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; overflow: hidden; border: 4px solid #fff; background: #e9ecef;">
                                        @if ($photo = $teacher->publicPhotoUrl())
                                            <img src="{{ $photo }}" alt="{{ $teacher->full_name }}" style="width: 100%; height: 100%; object-fit: cover;">
                                        @else
                                            {{ mb_substr($teacher->first_name, 0, 1) }}
                                        @endif
                                    </span>
                                </div>
                                <h4 class="fw-bold mb-1 text-dark">{{ $teacher->full_name }}</h4>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 fs-6 rounded-pill fw-semibold mb-3 d-inline-block">
                                    <i class="bi bi-briefcase me-1"></i> {{ $teacher->designation ?: 'শিক্ষক' }}
                                </span>

                                <div class="text-start bg-light p-3 rounded-3 mb-4">
                                    @if ($teacher->qualification)
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-mortarboard text-primary me-2 fs-5"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">শিক্ষাগত যোগ্যতা</small>
                                                <span class="fw-semibold text-dark">{{ $teacher->qualification }}</span>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($teacher->specialization)
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-journal-bookmark text-primary me-2 fs-5"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">বিশেষজ্ঞতা / বিষয়</small>
                                                <span class="fw-semibold text-dark">{{ $teacher->specialization }}</span>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($teacher->phone)
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-telephone text-primary me-2 fs-5"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">মোবাইল নম্বর</small>
                                                <a href="tel:{{ $teacher->phone }}" class="text-primary fw-semibold text-decoration-none">{{ $teacher->phone }}</a>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($teacher->email)
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="bi bi-envelope text-primary me-2 fs-5"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">ইমেইল</small>
                                                <a href="mailto:{{ $teacher->email }}" class="text-primary fw-semibold text-decoration-none">{{ $teacher->email }}</a>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($teacher->joining_date)
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-calendar-check text-primary me-2 fs-5"></i>
                                            <div>
                                                <small class="text-muted d-block" style="font-size: 0.75rem;">যোগদানের সময়</small>
                                                <span class="fw-semibold text-dark">{{ $teacher->joining_date->format('F Y') }}</span>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="d-flex gap-2 justify-content-end">
                                    <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">বন্ধ করুন</button>
                                    <a href="{{ route('public.teachers.show', $teacher) }}" class="btn btn-primary px-3">
                                        <i class="bi bi-arrow-up-right-circle me-1"></i> বিস্তারিত প্রোফাইল
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="pub-section" id="classes">
        <div class="container">
            <div class="row align-items-end g-3 mb-4">
                <div class="col-lg-8">
                    <span class="pub-kicker">একাডেমিক তথ্য</span>
                    <h2 class="pub-h2 mb-2">শ্রেণি ও শিক্ষা কার্যক্রম</h2>
                    <p class="pub-muted mb-0">
                        সক্রিয় শ্রেণি, শাখা ও একাডেমিক রুটিন একই জায়গায় — অভিভাবক ও শিক্ষার্থীদের জন্য সহজলভ্য।
                    </p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="{{ route('public.routines.index') }}" class="btn pub-btn-soft">
                        রুটিন ও ক্যালেন্ডার <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>

            <div class="pub-class-grid">
                @forelse ($classes as $class)
                    <article class="pub-class-card">
                        <span class="pub-class-icon"><i class="bi bi-mortarboard"></i></span>
                        <div class="pub-class-copy">
                            <span class="pub-class-level">শ্রেণি {{ $class->level }}</span>
                            <h3>{{ $class->name }}</h3>
                            <p>
                                {{ $class->sections->isNotEmpty()
                                    ? $class->sections->count().'টি সক্রিয় শাখা'
                                    : 'শাখা তথ্য শীঘ্রই যোগ করা হবে' }}
                            </p>
                            @if ($class->sections->isNotEmpty())
                                <div class="pub-class-tags">
                                    @foreach ($class->sections as $section)
                                        <span>{{ $section->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <a href="{{ route('public.routines.index') }}" class="pub-class-link">
                            রুটিন <i class="bi bi-arrow-up-right"></i>
                        </a>
                    </article>
                @empty
                    <div class="pub-panel">
                        <div class="pub-panel-body">
                            <p class="pub-empty mb-0">শ্রেণি তালিকা শীঘ্রই প্রকাশ করা হবে।</p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="pub-section" id="subjects">
        <div class="container">
            <div class="text-center mb-4">
                <span class="pub-kicker">পাঠ্যতালিকা</span>
                <h2 class="pub-h2">আরবি ও প্রোগ্রামিংসহ সব বিষয় পড়ানো হয়</h2>
                <p class="pub-muted mb-0">
                    সাধারণ একাডেমিক বিষয়ের পাশাপাশি {{ $schoolName }}-এ আরবি এবং প্রোগ্রামিংও নিয়মিত ক্লাসের অংশ।
                </p>
            </div>

            <div class="pub-subject-cards">
                @foreach ($featuredSubjects as $featured)
                    <article class="pub-subject-card">
                        <span class="pub-subject-card-icon pub-icon-{{ $featured['color'] }}"><i class="bi {{ $featured['icon'] }}"></i></span>
                        <div>
                            <strong>{{ $featured['name'] }}</strong>
                            <span class="pub-subject-card-en">{{ $featured['en'] }}</span>
                            <small>{{ $featured['note'] }}</small>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($subjectRows->isNotEmpty())
                <ul class="pub-chips">
                    @foreach ($subjectRows as $subject)
                        <li class="pub-chip">{{ $subject['name'] }}</li>
                    @endforeach
                </ul>
            @endif

            <p class="pub-subjects-note">
                <i class="bi bi-info-circle"></i>
                বিষয় তালিকা সরাসরি স্কুল সিস্টেম থেকে আসছে — সিস্টেমে নতুন বিষয় যোগ করলে এখানেই দেখা যাবে।
            </p>
        </div>
    </section>

    @if ($videos->isNotEmpty())
        <section class="pub-section pub-section-tint" id="videos">
            <div class="container">
                <div class="text-center mb-4">
                    <span class="pub-kicker">ভিডিও পাঠ</span>
                    <h2 class="pub-h2">ক্লাসের ভিডিও এখান থেকেই দেখুন</h2>
                    <p class="pub-muted mb-0">
                        শিক্ষকরা যেসব ভিডিও পাঠ প্রকাশ করেছেন সেগুলো শ্রেণি অনুযায়ী সাজানো — ইউটিউব লিংক হোক বা স্কুলে আপলোড করা ফাইল, সবই এখানে চলবে।
                    </p>
                </div>

                <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
                    @foreach ($videos as $video)
                        <div class="col">
                            <button type="button" class="pub-video-card"
                                    data-video-type="{{ $video->isYoutube() ? 'youtube' : 'file' }}"
                                    data-video-src="{{ $video->playerUrl() }}"
                                    data-video-title="{{ $video->title }}">
                                <span class="pub-video-thumb">
                                    @if ($video->thumbnailUrl())
                                        <img src="{{ $video->thumbnailUrl() }}" alt="{{ $video->title }}" loading="lazy">
                                    @else
                                        <span class="pub-video-thumb-blank"><i class="bi bi-film"></i></span>
                                    @endif
                                    <span class="pub-video-play" aria-hidden="true"><i class="bi bi-play-fill"></i></span>
                                </span>
                                <span class="pub-video-meta">
                                    <strong>{{ $video->title }}</strong>
                                    <small>
                                        {{ $video->schoolClass?->name ?? '—' }}
                                        @if ($video->section) · {{ $video->section->name }} @endif
                                        @if ($video->subject) · {{ $video->subject->name }} @endif
                                    </small>
                                    <span class="pub-video-kind">
                                        <i class="bi {{ $video->isYoutube() ? 'bi-youtube' : 'bi-camera-reels' }}"></i>
                                        {{ $video->isYoutube() ? 'ইউটিউব' : 'আপলোড করা' }}
                                    </span>
                                </span>
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="modal fade pub-video-modal" id="pubVideoModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="pubVideoTitle"></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="বন্ধ করুন"></button>
                    </div>
                    <div class="modal-body">
                        <div class="pub-video-stage" id="pubVideoStage"></div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <section class="pub-section pt-0" id="admission">
        <div class="container">
            <div class="pub-admission-band">
                <div>
                    <span class="pub-kicker pub-kicker-light">ভর্তি চলছে</span>
                    <h2 class="pub-h2 pub-h2-light">নতুন শিক্ষাবর্ষে ভর্তির আবেদন করুন</h2>
                    <p>অনলাইনে আবেদন ফরম পূরণ করুন — আবেদন নম্বর সংরক্ষণ করুন এবং স্কুল অফিস থেকে নিশ্চিতকরণ পাবেন।</p>
                </div>
                <div class="pub-admission-actions">
                    <a href="{{ $applyUrl }}" class="btn pub-btn-light btn-lg"><i class="bi bi-pencil-square"></i> অনলাইন আবেদন</a>
                    <a href="{{ route('home').'#contact' }}" class="btn pub-btn-outline-light btn-lg">যোগাযোগ করুন</a>
                </div>
            </div>
        </div>
    </section>

    <section class="pub-section pt-0" id="contact">
        <div class="container">
            <div class="text-center mb-4">
                <span class="pub-kicker">যোগাযোগ</span>
                <h2 class="pub-h2">আমাদের সাথে যোগাযোগ করুন</h2>
            </div>
            <div class="row g-3">
                <div class="col-md-6 col-lg-3">
                    <div class="pub-contact-card">
                        <span class="pub-contact-icon"><i class="bi bi-geo-alt"></i></span>
                        <strong>ঠিকানা</strong>
                        <p>{{ filled($address) ? $address : 'ঠিকানা শীঘ্রই আসছে' }}</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="pub-contact-card">
                        <span class="pub-contact-icon"><i class="bi bi-telephone"></i></span>
                        <strong>ফোন</strong>
                        <p>
                            @if (filled($school?->phone))
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $school->phone) }}">{{ $school->phone }}</a>
                            @endif
                            @if (filled($school?->phone_alt))
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $school->phone_alt) }}">{{ $school->phone_alt }}</a>
                            @endif
                            @if (blank($school?->phone) && blank($school?->phone_alt))
                                শীঘ্রই আসছে
                            @endif
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="pub-contact-card">
                        <span class="pub-contact-icon"><i class="bi bi-envelope"></i></span>
                        <strong>ইমেইল</strong>
                        <p>
                            @if (filled($school?->email))
                                <a href="mailto:{{ $school->email }}">{{ $school->email }}</a>
                            @else
                                শীঘ্রই আসছে
                            @endif
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="pub-contact-card">
                        <span class="pub-contact-icon"><i class="bi bi-globe2"></i></span>
                        <strong>ওয়েবসাইট</strong>
                        <p>
                            @if (filled($school?->website))
                                <a href="{{ \Illuminate\Support\Str::startsWith($school->website, ['http://', 'https://']) ? $school->website : 'https://'.$school->website }}" target="_blank" rel="noopener noreferrer">{{ $school->website }}</a>
                            @else
                                শীঘ্রই আসছে
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/notice-ticker.js') }}?v=2" defer></script>
    @if ($videos->isNotEmpty())
        <script src="{{ asset('assets/js/class-videos.js') }}?v=1" defer></script>
    @endif
    <script src="{{ asset('assets/js/live.js') }}?v=2" defer data-live-url="{{ route('public.live') }}"></script>
@endpush
