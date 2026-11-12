@extends('layouts.teacher')

@section('title', __('Shortcut Dashboard'))

@section('content')
<div class="shortcut-dashboard">
    <!-- Summary Cards -->
    <div class="summary-section">
        <div class="summary-grid">
            <div class="summary-card blue">
                <div class="summary-icon">
                    <i class="bi bi-people"></i>
                </div>
                <div class="summary-content">
                    <div class="summary-value">{{ number_format($summary['total_students']) }}</div>
                    <div class="summary-label">{{ __('Total Students') }}</div>
                </div>
            </div>

            <div class="summary-card green">
                <div class="summary-icon">
                    <i class="bi bi-person-workspace"></i>
                </div>
                <div class="summary-content">
                    <div class="summary-value">{{ number_format($summary['total_teachers']) }}</div>
                    <div class="summary-label">{{ __('Total Teachers') }}</div>
                </div>
            </div>

            <div class="summary-card purple">
                <div class="summary-icon">
                    <i class="bi bi-person-badge"></i>
                </div>
                <div class="summary-content">
                    <div class="summary-value">{{ number_format($summary['total_staff']) }}</div>
                    <div class="summary-label">{{ __('Total Staff') }}</div>
                </div>
            </div>

            <div class="summary-card orange">
                <div class="summary-icon">
                    <i class="bi bi-calendar-check"></i>
                </div>
                <div class="summary-content">
                    <div class="summary-value">{{ number_format($summary['today_attendance']) }}</div>
                    <div class="summary-label">{{ __('Today\'s Attendance') }}</div>
                </div>
            </div>

            <div class="summary-card teal">
                <div class="summary-icon">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div class="summary-content">
                    <div class="summary-value">{{ number_format($summary['today_collection'], 2) }}</div>
                    <div class="summary-label">{{ __('Today\'s Collection') }}</div>
                </div>
            </div>

            <div class="summary-card red">
                <div class="summary-icon">
                    <i class="bi bi-exclamation-circle"></i>
                </div>
                <div class="summary-content">
                    <div class="summary-value">{{ number_format($summary['pending_fees'], 2) }}</div>
                    <div class="summary-label">{{ __('Pending Fees') }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="search-section">
        <div class="search-container">
            <i class="bi bi-search search-icon"></i>
            <input type="text" id="shortcutSearch" class="search-input" placeholder="{{ __('Search shortcuts...') }}">
        </div>
    </div>

    <!-- Shortcuts Grid -->
    <div class="shortcuts-section">
        @php
            $categories = collect($shortcuts)->groupBy('category');
        @endphp

        @foreach ($categories as $category => $categoryShortcuts)
            <div class="category-section" data-category="{{ $category }}">
                <h3 class="category-title">{{ $category }}</h3>
                <div class="shortcuts-grid">
                    @foreach ($categoryShortcuts as $shortcut)
                        <a href="{{ $shortcut['route'] }}" class="shortcut-card {{ $shortcut['color'] }}" data-shortcut="{{ strtolower($shortcut['label']) }}">
                            <div class="shortcut-icon">
                                <i class="bi {{ $shortcut['icon'] }}"></i>
                            </div>
                            <div class="shortcut-label">{{ $shortcut['label'] }}</div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('shortcutSearch');
    const shortcutCards = document.querySelectorAll('.shortcut-card');
    const categorySections = document.querySelectorAll('.category-section');

    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();

        shortcutCards.forEach(card => {
            const shortcutName = card.getAttribute('data-shortcut');
            if (shortcutName.includes(searchTerm)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });

        // Hide empty categories
        categorySections.forEach(section => {
            const visibleCards = section.querySelectorAll('.shortcut-card[style="display: flex;"], .shortcut-card:not([style*="display"])');
            if (visibleCards.length === 0) {
                section.style.display = 'none';
            } else {
                section.style.display = 'block';
            }
        });
    });
});
</script>
@endpush
