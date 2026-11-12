@extends('layouts.app')

@section('title', 'Face Recognition Attendance')

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('device_secret'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong><i class="bi bi-key"></i> Device Credentials:</strong><br>
            Name: {{ session('device_secret.name') }}<br>
            Secret: <code>{{ session('device_secret.secret') }}</code><br>
            <small class="text-muted">⚠️ This secret will NOT be shown again. Save it securely!</small>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @include('attendance.partials.tabs', ['active' => 'face'])

    <div class="page-header mb-4">
        <h1><i class="bi bi-person-bounding-box"></i> Face Recognition Attendance</h1>
        <p class="text-muted">Configure and manage your school's face recognition attendance system</p>
    </div>

    {{-- System Status Card --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-4 col-md-6">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <span class="display-4">🎭</span>
                    </div>
                    <h5 class="card-title">System Status</h5>
                    <p class="card-text text-muted">
                        Configure face recognition device integration<br>
                        and manage student/teacher enrollment
                    </p>
                    <form method="POST" action="{{ route('admin.attendance.face.toggle-system') }}">
                        @csrf
                        <div class="form-check form-switch d-inline-flex align-items-center">
                            <input class="form-check-input" type="checkbox" 
                                   name="is_enabled" value="1" id="systemEnable" onchange="this.form.submit()"
                                   {{ \App\Models\AttendanceDevice::query()->where('kind', 'face')->where('is_active', true)->exists() ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="systemEnable">
                                <span class="badge bg-success">Active</span>
                                <span class="badge bg-secondary">Inactive</span>
                            </label>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Quick Actions</h5>
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.attendance.face.enroll', ['type' => 'students']) }}" 
                           class="btn btn-outline-primary">
                            <i class="bi bi-person-plus"></i> Enroll Students
                        </a>
                        <a href="{{ route('admin.attendance.face.enroll', ['type' => 'teachers']) }}" 
                           class="btn btn-outline-success">
                            <i class="bi bi-person-workspace"></i> Enroll Teachers
                        </a>
                        <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#registerModal">
                            <i class="bi bi-pc-display"></i> Register Device
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-12">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Rules Summary</h5>
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td width="60%">School Start:</td>
                            <td><strong>{{ date('g:i A', strtotime($rules->start_time)) }}</strong></td>
                        </tr>
                        <tr>
                            <td>Late Threshold:</td>
                            <td><strong>{{ $rules->late_threshold_minutes }} min</strong></td>
                        </tr>
                        <tr>
                            <td>Working Days:</td>
                            <td><strong>{{ collect(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'])->intersectByKeys($rules->working_days)->join(', ') }}</strong></td>
                        </tr>
                        <tr>
                            <td>SMS Notifications:</td>
                            <td>
                                @if($rules->send_sms_on_present)
                                    <span class="badge bg-success">Present</span>
                                @else
                                    <span class="badge bg-secondary">Off</span>
                                @endif
                                @if($rules->send_sms_on_late)
                                    <span class="badge bg-warning">Late</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                    <div class="mt-3">
                        <a href="{{ route('admin.attendance.face.index') }}#rules-section" 
                           class="btn btn-sm btn-outline-secondary">
                            Edit Rules →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Device Management Section --}}
    <section id="devices-section" class="mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2><i class="bi bi-pc-display-horizontal"></i> Registered Devices</h2>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">
                <i class="bi bi-plus-circle"></i> Add New Device
            </button>
        </div>

        @if($devices->isEmpty())
            <div class="alert alert-info">
                <h5><i class="bi bi-info-circle"></i> No Devices Registered</h5>
                <p>Click "Add New Device" to register a face recognition terminal.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Serial</th>
                            <th>Location</th>
                            <th>Mappings</th>
                            <th>Last Seen</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($devices as $device)
                            <tr>
                                <td>
                                    <strong>{{ $device->name }}</strong>
                                    @if($device->config['model'] ?? null)
                                        <br><small class="text-muted">{{ $device->config['model'] }}</small>
                                    @endif
                                </td>
                                <td>{{ $device->serial ?? '—' }}</td>
                                <td>{{ $device->location ?? '—' }}</td>
                                <td>{{ $device->mappings_count }}</td>
                                <td>
                                    @if($device->last_seen_at)
                                        {{ $device->last_seen_at->diffForHumans() }}
                                    @else
                                        <span class="text-muted">Never</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $device->is_active ? 'success' : 'secondary' }}">
                                        {{ $device->statusLabel() }}
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        @if($device->is_active)
                                            <form method="POST" action="{{ route('attendance.methods.devices.toggle', $device) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-warning" title="Disable">
                                                    <i class="bi bi-slash-circle"></i>
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('attendance.methods.devices.toggle', $device) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-success" title="Enable">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            </form>
                                        @endif

                                        <a href="{{ route('admin.attendance.face.enroll', ['device_id' => $device->id]) }}" 
                                           class="btn btn-outline-primary" title="Enroll Users">
                                            <i class="bi bi-person-plus"></i>
                                        </a>

                                        <form method="POST" action="{{ route('admin.attendance.face.devices.test-connection', $device) }}"
                                              style="display:inline;">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-info" title="Test Connection"
                                                    onclick="return confirm('Test connection to {{ $device->name }}?')">
                                                <i class="bi bi-lightning-charge"></i>
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.attendance.face.devices.rotate', $device) }}"
                                              style="display:inline;">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn btn-outline-dark" title="Rotate Secret"
                                                    onclick="return confirm('Generate new credentials for {{ $device->name }}? The old one will stop working.')">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                        </form>

                                        <button type="button" class="btn btn-outline-danger btn-sm" 
                                                title="Delete"
                                                data-bs-toggle="modal"
                                                data-bs-target="#deleteModal_{{ $device->id }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Enrollment Section --}}
    <section class="mb-5">
        <h2><i class="bi bi-person-check"></i> User Enrollment</h2>
        <p class="text-muted">Map students and teachers to their face recognition device IDs</p>

        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="enrollTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#studentTab" type="button">
                            <i class="bi bi-children"></i> Students
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#teacherTab" type="button">
                            <i class="bi bi-person-workspace"></i> Teachers
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body tab-content">
                {{-- Student Enrollment --}}
                <div class="tab-pane fade show active" id="studentTab">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Select Device</label>
                            <select name="device_id" id="student_device_id" class="form-select">
                                <option value="">-- Select Face Device --</option>
                                @foreach($devices as $device)
                                    <option value="{{ $device->id }}">{{ $device->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Search Query (name, ID, email)</label>
                            <input type="text" class="form-control" placeholder="Enter search term...">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary">
                                <i class="bi bi-search"></i> Search Students
                            </button>
                            <a href="{{ route('admin.attendance.face.enroll', ['type' => 'students']) }}" class="btn btn-outline-secondary">
                                Reset
                            </a>
                        </div>
                    </div>

                    <hr>
                    
                    {{-- Sample mappings table --}}
                    <h5>Current Mappings</h5>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Class/Section</th>
                                    <th>Device ID</th>
                                    <th>Device</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr colspan="5">
                                    <p class="text-muted">Use the search above to find and map students.</p>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Teacher Enrollment --}}
                <div class="tab-pane fade" id="teacherTab">
                    <div class="text-center py-5">
                        <i class="bi bi-person-workspace display-4 text-muted"></i>
                        <h5 class="mt-3">Teacher Enrollment</h5>
                        <p class="text-muted">Search for teachers by name or employee code</p>
                        <button class="btn btn-success">
                            <i class="bi bi-search"></i> Search Teachers
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Rules Configuration Section --}}
    <section id="rules-section" class="mb-5">
        <h2><i class="bi bi-gear"></i> Attendance Rules</h2>
        <p class="text-muted">Configure how face recognition events are processed</p>

        <form method="POST" action="{{ route('admin.attendance.face.rules.update') }}">
            @csrf
            @method('PUT')
            
            <div class="row g-4">
                {{-- Working Hours --}}
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header fw-bold">Working Hours</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">School Start Time</label>
                                <input type="time" name="start_time" class="form-control" 
                                       value="{{ date('H:i', strtotime($rules->start_time)) }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">School End Time</label>
                                <input type="time" name="end_time" class="form-control" 
                                       value="{{ date('H:i', strtotime($rules->end_time)) }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Late Threshold (minutes after start)</label>
                                <input type="number" name="late_threshold_minutes" class="form-control" 
                                       value="{{ $rules->late_threshold_minutes }}" min="1" max="120" required>
                                <small class="text-muted">Students arriving within this time are marked late</small>
                            </div>
                            <div class="mb-0">
                                <label class="form-label">Very Late Threshold (minutes)</label>
                                <input type="number" name="very_late_threshold_minutes" class="form-control" 
                                       value="{{ $rules->very_late_threshold_minutes }}" min="15" max="240">
                                <small class="text-muted">After this time, events may be skipped or excused</small>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Event Processing --}}
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header fw-bold">Event Processing</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Max Checks Per Day</label>
                                <input type="number" name="max_checks_per_day" class="form-control" 
                                       value="{{ $rules->max_checks_per_day }}" min="2" max="50" required>
                                <small class="text-muted">Maximum allowed check-ins per user per day</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Duplicate Scan Interval (seconds)</label>
                                <input type="number" name="duplicate_interval_seconds" class="form-control" 
                                       value="{{ $rules->duplicate_interval_seconds }}" min="30" max="3600" required>
                                <small class="text-muted">Minimum seconds between duplicate scans from same device</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Valid Window (hours before/after school hours)</label>
                                <input type="number" step="0.5" name="window_hours" class="form-control" 
                                       value="{{ $rules->window_hours }}" min="6" max="24" required>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Schedule Settings --}}
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header fw-bold">Schedule</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Working Days</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dayIndex => $dayName)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="working_days[]" value="{{ $dayIndex }}"
                                                   {{ in_array($dayIndex, $rules->working_days) ? 'checked' : '' }}>
                                            <label class="form-check-label">{{ $dayName }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="skip_weekends" class="form-check-input" 
                                       value="1" id="skipWeekends" {{ $rules->skip_weekends ? 'checked' : '' }}>
                                <label class="form-check-label" for="skipWeekends">Skip weekends automatically</label>
                            </div>
                            <div class="mb-0 form-check">
                                <input type="checkbox" name="allow_holiday_attendance" class="form-check-input" 
                                       value="1" id="allowHoliday" {{ $rules->allow_holiday_attendance ? 'checked' : '' }}>
                                <label class="form-check-label" for="allowHoliday">Allow attendance during holidays</label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- SMS Notification --}}
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header fw-bold">SMS Notifications</div>
                        <div class="card-body">
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="send_sms_on_present" class="form-check-input" 
                                       value="1" id="sendOnPresent" {{ $rules->send_sms_on_present ? 'checked' : '' }}>
                                <label class="form-check-label" for="sendOnPresent">
                                    Send SMS on present
                                </label>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="send_sms_on_late" class="form-check-input" 
                                       value="1" id="sendOnLate" {{ $rules->send_sms_on_late ? 'checked' : '' }}>
                                <label class="form-check-label" for="sendOnLate">
                                    Send SMS on late arrival
                                </label>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" name="send_sms_to_guardian" class="form-check-input" 
                                       value="1" id="sendToGuardian" {{ $rules->send_sms_to_guardian ? 'checked' : '' }}>
                                <label class="form-check-label" for="sendToGuardian">Send to guardian (not parent)</label>
                            </div>
                            <small class="text-muted">
                                SMS will use existing BulkSMSBD integration with balance validation
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Save Attendance Rules
                </button>
                <a href="{{ route('admin.attendance.face.index') }}" class="btn btn-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </section>

    {{-- Delete Confirmation Modal (dynamically generated per device) --}}
    @foreach($devices as $device)
    <div class="modal fade" id="deleteModal_{{ $device->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Device Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong>{{ $device->name }}</strong>?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" action="{{ route('admin.attendance.face.devices.destroy', $device) }}">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Device</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<!-- Register Device Modal -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="registerModalLabel"><i class="bi bi-pc-display"></i> Register New Face Device</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.attendance.face.devices.register') }}">
                @csrf
                <input type="hidden" name="kind" value="face">
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Device Name *</label>
                                <input type="text" name="name" class="form-control" required
                                       placeholder="e.g., Main Gate Face Terminal">
                                <small class="text-muted">A descriptive name for identification</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Serial Number</label>
                                <input type="text" name="serial" class="form-control" 
                                       placeholder="e.g., SN-123456789">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Device Model</label>
                                <input type="text" name="model" class="form-control"
                                       placeholder="e.g., Hikvision DS-K5608">
                                <small class="text-muted">Exact make and model number</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Firmware Version</label>
                                <input type="text" name="firmware" class="form-control"
                                       placeholder="e.g., V3.1.0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">LAN IP Address</label>
                                <input type="text" name="lan_ip" class="form-control"
                                       placeholder="e.g., 192.168.1.100">
                                <small class="text-muted">IP address of the device on LAN</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">API Port</label>
                                <input type="number" name="api_port" class="form-control"
                                       placeholder="e.g., 80" min="1" max="65535">
                                <small class="text-muted">Port for ISAPI/Push SDK communication</small>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" name="location" class="form-control"
                                       placeholder="e.g., School Entrance Gate 1">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle"></i> Important Information:</h6>
                                <ul class="mb-0 ps-3">
                                    <li>Ensure device supports HTTPS event pushing to Laravel API</li>
                                    <li>Required protocols: ISAPI, ISUP, or vendor Push SDK</li>
                                    <li>Note device credentials (secret shown only once after registration)</li>
                                    <li>Contact hardware vendor for exact firmware requirements</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Register Device
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const triggerButtons = document.querySelectorAll('[data-bs-target="#registerModal"]');
    triggerButtons.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const modalEl = document.getElementById('registerModal');
            if (window.bootstrap && window.bootstrap.Modal) {
                const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.show();
            } else if (window.$ && typeof $(modalEl).modal === 'function') {
                $(modalEl).modal('show');
            }
        });
    });
});

// Form submission handling for toggle switch
const toggleForms = document.querySelectorAll('form[action*="toggle-system"]');
toggleForms.forEach(form => {
    const checkbox = form.querySelector('input[type="checkbox"]');
    if (checkbox) {
        checkbox.addEventListener('change', () => {
            form.submit();
        });
    }
});

// Device selection for enrollment
const deviceSelect = document.getElementById('student_device_id');
if (deviceSelect) {
    deviceSelect.addEventListener('change', function() {
        // Update URL with selected device ID
        const url = new URL(window.location.href);
        if (this.value) {
            url.searchParams.set('device_id', this.value);
        } else {
            url.searchParams.delete('device_id');
        }
        window.history.pushState({}, '', url);
    });
}
</script>
@endpush
