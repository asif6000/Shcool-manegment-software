@php
    $active = $active ?? 'students';
    $tabQuery = array_filter(request()->only(['session_id', 'class_id', 'section_id', 'date']), fn ($value) => filled($value));
@endphp

<ul class="nav nav-pills app-tabs mb-3" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $active === 'students' ? 'active' : '' }}" href="{{ route('attendance.students.index', $tabQuery) }}">
            <i class="bi bi-people me-1"></i>{{ __('Daily attendance') }}
        </a>
    </li>
    @can('attendance.mark')
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $active === 'qr' ? 'active' : '' }}" href="{{ route('attendance.qr.index', $tabQuery) }}">
                <i class="bi bi-qr-code-scan me-1"></i>{{ __('QR scan') }}
            </a>
        </li>
    @endcan
    @can('attendance.report')
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $active === 'report' ? 'active' : '' }}" href="{{ route('attendance.students.report', $tabQuery) }}">
                <i class="bi bi-clipboard-data me-1"></i>{{ __('Monthly report') }}
            </a>
        </li>
    @endcan
    @canany(['attendance.submit', 'attendance.approve'])
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $active === 'submissions' ? 'active' : '' }}" href="{{ route('attendance.submissions.index') }}">
                <i class="bi bi-clipboard-check me-1"></i>{{ __('Submissions') }}
            </a>
        </li>
    @endcanany
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $active === 'staff' ? 'active' : '' }}" href="{{ route('attendance.staff.index', $tabQuery) }}">
            <i class="bi bi-person-workspace me-1"></i>{{ __('Staff attendance') }}
        </a>
    </li>
    @canany(['attendance.view', 'attendance.devices.manage'])
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ $active === 'face' ? 'active' : '' }}" href="{{ route('admin.attendance.face.index') }}">
                <i class="bi bi-person-bounding-box me-1"></i>{{ __('Face attendance') }}
            </a>
        </li>
    @endcanany
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $active === 'methods' ? 'active' : '' }}" href="{{ route('attendance.methods.index') }}">
            <i class="bi bi-router me-1"></i>{{ __('Methods & devices') }}
        </a>
    </li>
</ul>
