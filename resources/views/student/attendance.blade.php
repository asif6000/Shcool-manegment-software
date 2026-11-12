@extends('layouts.student')

@section('title', __('Attendance'))

@push('styles')
<style>
    .modern-attendance-card {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 20px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        border: 1px solid #e2e8f0;
        transition: all 0.3s ease;
    }
    
    .modern-attendance-card:hover {
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        transform: translateY(-2px);
    }
    
    .stat-mini-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        padding: 20px;
        color: white;
        min-width: 140px;
        flex: 1;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
        margin-bottom: 12px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        height: 100%;
        min-height: 120px;
    }
    
    .stat-mini-card.present {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    
    .stat-mini-card.absent {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }
    
    .stat-mini-card.late {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }
    
    .stat-mini-card.percentage {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    }
    
    .stat-mini-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        pointer-events: none;
    }
    
    .stat-mini-card:hover {
        transform: scale(1.05);
    }
    
    .stat-mini-icon {
        font-size: 2.5rem;
        opacity: 0.9;
        margin-bottom: 12px;
    }
    
    .stat-mini-label {
        font-size: 0.75rem;
        opacity: 0.9;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        font-weight: 600;
    }
    
    .stat-mini-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.2;
    }
    
    .stat-mini-sublabel {
        font-size: 0.75rem;
        opacity: 0.8;
        margin-top: 4px;
    }
    
    .attendance-stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .trend-chart-container {
        background: white;
        border-radius: 16px;
        padding: 24px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        margin-bottom: 30px;
        min-height: 350px;
        position: relative;
    }
    
    .chart-placeholder {
        height: 280px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(to bottom, #f1f5f9 0%, #ffffff 100%);
        border-radius: 12px;
        border: 2px dashed #cbd5e1;
        position: relative;
        overflow-x: auto;
        overflow-y: hidden;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }
    
    .chart-placeholder::-webkit-scrollbar {
        height: 8px;
    }
    
    .chart-placeholder::-webkit-scrollbar-track {
        background: transparent;
    }
    
    .chart-placeholder::-webkit-scrollbar-thumb {
        background-color: #cbd5e1;
        border-radius: 4px;
    }
    
    .chart-bar-container {
        display: flex;
        align-items: flex-end;
        justify-content: flex-start;
        height: 240px;
        width: 100%;
        padding: 0 40px;
        gap: 6px;
    }
    
    .chart-bar-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        width: 4%;
        flex-shrink: 0;
    }
    
    .chart-bar {
        width: 100%;
        border-radius: 6px 6px 0 0;
        transition: all 0.3s ease;
        position: relative;
        cursor: pointer;
    }
    
    .chart-bar.present {
        background: linear-gradient(to top, #10b981, #34d399);
        min-height: 2px;
    }
    
    .chart-bar.absent {
        background: linear-gradient(to top, #ef4444, #f87171);
        min-height: 2px;
    }
    
    .chart-bar.late {
        background: linear-gradient(to top, #f59e0b, #fbbf24);
        min-height: 2px;
    }
    
    .chart-bar.holiday {
        background: linear-gradient(to top, #94a3b8, #cbd5e1);
        min-height: 2px;
    }
    
    .chart-bar:hover {
        transform: scaleY(1.02);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .chart-bar-tooltip {
        position: absolute;
        top: -40px;
        left: 50%;
        transform: translateX(-50%);
        background: #1e293b;
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.75rem;
        white-space: nowrap;
        opacity: 0;
        transition: opacity 0.2s ease;
        z-index: 10;
    }
    
    .chart-bar-wrapper:hover .chart-bar-tooltip {
        opacity: 1;
    }
    
    .chart-day-label {
        font-size: 0.7rem;
        color: #64748b;
        text-align: center;
        font-weight: 600;
    }
    
    .modern-table-container {
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }
    
    .modern-table-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modern-table-title h2 {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0;
    }
    
    .modern-table-title p {
        font-size: 0.875rem;
        opacity: 0.9;
        margin: 4px 0 0 0;
    }
    
    .modern-badge {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .modern-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .modern-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 18px 20px;
        text-align: left;
        border-bottom: 2px solid #e2e8f0;
    }
    
    .modern-table td {
        padding: 18px 20px;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.9rem;
        vertical-align: middle;
    }
    
    .modern-table tbody tr {
        transition: all 0.2s ease;
    }
    
    .modern-table tbody tr:hover {
        background: #f8fafc;
    }
    
    .modern-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .status-badge-modern {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 10px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .status-present {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-absent {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .status-late {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-leave {
        background: #e0e7ff;
        color: #3730a3;
    }
    
    .status-excused {
        background: #f3e8ff;
        color: #6b21a8;
    }
    
    .empty-state-modern {
        padding: 60px 20px;
        text-align: center;
    }
    
    .empty-state-icon {
        width: 120px;
        height: 120px;
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 24px;
        color: #94a3b8;
        font-size: 3rem;
    }
    
    .empty-state h3 {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
        margin: 0 0 12px;
    }
    
    .empty-state p {
        color: #64748b;
        font-size: 1rem;
        margin: 0;
    }
    
    .legend-modern {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        padding: 20px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.85rem;
        color: #475569;
    }
    
    .legend-dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
    }
    
    /* Responsive Design */
    @media (max-width: 1024px) {
        .attendance-stat-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .stat-mini-card {
            min-height: 100px;
            padding: 16px;
        }
        
        .stat-mini-icon {
            font-size: 2rem;
        }
        
        .stat-mini-value {
            font-size: 1.75rem;
        }
        
        .trend-chart-container {
            padding: 20px;
            min-height: 320px;
        }
        
        .chart-placeholder {
            height: 240px;
        }
        
        .chart-bar-container {
            height: 200px;
            padding: 0 20px;
        }
    }
    
    @media (max-width: 768px) {
        .attendance-stat-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }
        
        .stat-mini-card {
            min-height: 90px;
            padding: 14px;
        }
        
        .stat-mini-icon {
            font-size: 1.75rem;
            margin-bottom: 8px;
        }
        
        .stat-mini-label {
            font-size: 0.7rem;
        }
        
        .stat-mini-value {
            font-size: 1.5rem;
        }
        
        .stat-mini-sublabel {
            font-size: 0.65rem;
        }
        
        .trend-chart-container {
            padding: 16px;
            min-height: 300px;
        }
        
        .chart-placeholder {
            height: 200px;
        }
        
        .chart-bar-container {
            height: 160px;
            padding: 0 15px;
            gap: 4px;
        }
        
        .chart-bar-wrapper {
            width: 5%;
        }
        
        .chart-day-label {
            font-size: 0.6rem;
        }
        
        .modern-table-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
            padding: 18px;
        }
        
        .modern-table-title h2 {
            font-size: 1.1rem;
        }
        
        .modern-table-title p {
            font-size: 0.8rem;
        }
        
        .modern-badge {
            padding: 6px 12px;
            font-size: 0.75rem;
        }
        
        .modern-table th,
        .modern-table td {
            padding: 12px 14px;
            font-size: 0.85rem;
        }
        
        .legend-modern {
            padding: 16px;
            gap: 12px;
        }
    }
    
    @media (max-width: 480px) {
        .page-header {
            flex-direction: column;
            gap: 12px;
        }
        
        .page-actions {
            width: 100%;
            justify-content: center;
        }
        
        .page-actions form {
            width: 100%;
        }
        
        .page-actions input[type="month"] {
            flex: 1;
            min-width: 140px;
        }
        
        .stat-mini-card {
            min-height: 85px;
            padding: 12px;
        }
        
        .stat-mini-icon {
            font-size: 1.5rem;
        }
        
        .stat-mini-value {
            font-size: 1.25rem;
        }
        
        .trend-chart-container {
            padding: 14px;
        }
        
        .chart-bar-container {
            padding: 0 10px;
        }
        
        .chart-bar-wrapper {
            width: 6%;
        }
        
        .empty-state-icon {
            width: 100px;
            height: 100px;
            font-size: 2.5rem;
        }
        
        .empty-state h3 {
            font-size: 1.25rem;
        }
    }
</style>
@endpush

@section('content')
    @php
        $percentage = $summary['percentage'];
        $prevMonth = $month->copy()->subMonthNoOverflow()->format('Y-m');
        $nextMonth = $month->copy()->addMonthNoOverflow()->format('Y-m');
        $isCurrentMonth = $month->isSameMonth(now());
    @endphp

    <!-- Page Header -->
    <div class="page-header mb-4">
        <div>
            <span class="page-eyebrow"><i class="bi bi-calendar-check"></i> {{ __('Attendance') }}</span>
            <h1 class="page-title">{{ __('My Attendance') }}</h1>
            <p class="page-subtitle">{{ __('Overview for :month', ['month' => $month->translatedFormat('F Y')]) }}</p>
        </div>
        <div class="page-actions d-flex gap-2 align-items-center">
            <a href="{{ route('student.attendance', ['month' => $prevMonth]) }}" class="btn btn-light" aria-label="{{ __('Previous month') }}">
                <i class="bi bi-arrow-left"></i>
            </a>
            <form method="GET" action="{{ route('student.attendance') }}" class="d-flex gap-2 align-items-center">
                <label class="visually-hidden" for="month">{{ __('Month') }}</label>
                <input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}" max="{{ now()->format('Y-m') }}" class="form-control" style="width:170px;">
                <button type="submit" class="btn btn-primary">{{ __('View') }}</button>
            </form>
            @unless ($isCurrentMonth)
                <a href="{{ route('student.attendance', ['month' => $nextMonth]) }}" class="btn btn-light" aria-label="{{ __('Next month') }}">
                    <i class="bi bi-arrow-right"></i>
                </a>
            @endunless
        </div>
    </div>

    <!-- Statistics Grid -->
    <section class="mb-4" aria-label="{{ __('Attendance statistics') }}">
        <div class="attendance-stat-grid">
            <!-- Present Stat -->
            <article class="stat-mini-card present">
                <div class="stat-mini-icon">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="stat-mini-label">{{ __('Present') }}</div>
                <div class="stat-mini-value">{{ number_format($summary['counts']['present']) }}</div>
                <div class="stat-mini-sublabel">{{ __('days') }}</div>
            </article>

            <!-- Absent Stat -->
            <article class="stat-mini-card absent">
                <div class="stat-mini-icon">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
                <div class="stat-mini-label">{{ __('Absent') }}</div>
                <div class="stat-mini-value">{{ number_format($summary['counts']['absent']) }}</div>
                <div class="stat-mini-sublabel">{{ __('days') }}</div>
            </article>

            <!-- Late Stat -->
            <article class="stat-mini-card late">
                <div class="stat-mini-icon">
                    <i class="bi bi-clock-fill"></i>
                </div>
                <div class="stat-mini-label">{{ __('Late') }}</div>
                <div class="stat-mini-value">{{ number_format($summary['counts']['late']) }}</div>
                <div class="stat-mini-sublabel">{{ __('arrivals') }}</div>
            </article>

            <!-- Percentage Stat -->
            <article class="stat-mini-card percentage">
                <div class="stat-mini-icon">
                    <i class="bi bi-graph-up"></i>
                </div>
                <div class="stat-mini-label">{{ __('Attendance Rate') }}</div>
                <div class="stat-mini-value">{{ $percentage !== null ? number_format($percentage, 1) . '%' : '—' }}</div>
                <div class="stat-mini-sublabel">{{ __('overall') }}</div>
            </article>
        </div>
    </section>

    <!-- Monthly Trend Chart -->
    <section class="mb-4" aria-label="{{ __('Monthly attendance trend') }}">
        <div class="trend-chart-container">
            <div style="margin-bottom: 20px;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 0;">{{ __('Daily Trend') }}</h3>
                <p style="color: #64748b; font-size: 0.9rem; margin: 4px 0 0 0;">{{ __('Attendance overview for each day in this month') }}</p>
            </div>
            
            @if($calendar['recorded'] > 0)
                <div class="chart-placeholder">
                    <div class="chart-bar-container">
                        @foreach($calendar['days'] as $dayData)
                            @if($dayData['status'] !== null || $dayData['holiday'])
                                <div class="chart-bar-wrapper">
                                    <div class="chart-bar-tooltip">
                                        @if($dayData['holiday'])
                                            {{ $dayData['date']->format('M j') }}: Holiday
                                        @elseif($dayData['status'])
                                            {{ $dayData['date']->format('M j') }}: {{ ucfirst($dayData['status']) }}
                                        @endif
                                    </div>
                                    @if($dayData['holiday'])
                                        <div class="chart-bar" style="height: 8px; background: #94a3b8;"></div>
                                    @elseif($dayData['status'])
                                        <div class="chart-bar {{ $dayData['status'] }}" 
                                             style="height: {{ max(8, ($summary['counts'][$dayData['status']] / max(1, $summary['counts']['present'])) * 40 ) }}px;"></div>
                                    @endif
                                    <div class="chart-day-label">{{ $dayData['date']->format('D') }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @else
                <div class="chart-placeholder">
                    <div style="text-align: center;">
                        <i class="bi bi-graph-up" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 12px;"></i>
                        <p style="color: #64748b;">{{ __('No data available') }}</p>
                    </div>
                </div>
            @endif
        </div>
    </section>

    <!-- Daily Records Table -->
    <section class="mb-4" aria-label="{{ __('Daily attendance records') }}">
        <div class="modern-table-container">
            <div class="modern-table-header">
                <div class="modern-table-title">
                    <h2>{{ __('Daily Register') }}</h2>
                    <p>{{ __('Complete log of all attendance entries for :month', ['month' => $month->translatedFormat('F Y')]) }}</p>
                </div>
                <span class="modern-badge">
                    <i class="bi bi-list-ul"></i> {{ $rows->count() }} {{ __('entries') }}
                </span>
            </div>

            @if ($rows->isEmpty())
                <div class="empty-state-modern">
                    <div class="empty-state-icon">
                        <i class="bi bi-calendar-x"></i>
                    </div>
                    <h3>{{ __('No attendance found') }}</h3>
                    <p>{{ __('Attendance records are not available for :month.', ['month' => $month->translatedFormat('F Y')]) }}</p>
                </div>
            @else
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th><i class="bi bi-calendar"></i> {{ __('Date') }}</th>
                            <th><i class="bi bi-calendar-event"></i> {{ __('Day') }}</th>
                            <th><i class="bi bi-buildings"></i> {{ __('Class') }}</th>
                            <th><i class="bi bi-clipboard-check"></i> {{ __('Status') }}</th>
                            <th><i class="bi bi-journal-text"></i> {{ __('Remarks') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $entry)
                            <tr>
                                <td class="fw-600">
                                    <i class="bi bi-date"></i> {{ $entry->attendance_date?->format('d M Y') }}
                                </td>
                                <td class="text-muted-2">{{ $entry->attendance_date?->translatedFormat('l') }}</td>
                                <td>
                                    <strong>{{ $entry->schoolClass?->name ?? '-' }}</strong>
                                    @if ($entry->section)
                                        <div class="text-muted-2" style="font-size:0.85rem;">{{ __('Section') }}: {{ $entry->section->name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="status-badge-modern status-{{ $entry->status }}">
                                        @if($entry->status === 'present')
                                            <i class="bi bi-check-circle-fill"></i>
                                        @elseif($entry->status === 'absent')
                                            <i class="bi bi-x-circle-fill"></i>
                                        @elseif($entry->status === 'late')
                                            <i class="bi bi-clock-fill"></i>
                                        @elseif($entry->status === 'leave')
                                            <i class="bi bi-calendar-x-fill"></i>
                                        @elseif($entry->status === 'excused')
                                            <i class="bi bi-check-circle-fill"></i>
                                        @endif
                                        {{ __(ucfirst($entry->status)) }}
                                    </span>
                                </td>
                                <td class="text-muted-2">{{ $entry->remarks ?: '<em class="text-muted">—</em>' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="legend-modern">
                    <div class="legend-item">
                        <span class="legend-dot" style="background: #10b981;"></span>
                        <span>{{ __('Present') }}</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot" style="background: #ef4444;"></span>
                        <span>{{ __('Absent') }}</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot" style="background: #f59e0b;"></span>
                        <span>{{ __('Late') }}</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot" style="background: #8b5cf6;"></span>
                        <span>{{ __('Leave') }}</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot" style="background: #ec4899;"></span>
                        <span>{{ __('Excused') }}</span>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
