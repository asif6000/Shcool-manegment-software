@extends('layouts.app')

@section('title', 'Face Recognition Enrollment')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-person-bounding-box text-primary"></i> Face Enrollment</h2>
            <p class="text-muted mb-0">Map student and staff members to face recognition hardware terminals.</p>
        </div>
        <div>
            <a href="{{ route('admin.attendance.face.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.attendance.face.enroll') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Select Terminal Device *</label>
                    <select name="device_id" class="form-select" required>
                        <option value="">-- Choose Face Terminal --</option>
                        @foreach($availableDevices as $dev)
                            <option value="{{ $dev->id }}" {{ (string)$deviceId === (string)$dev->id ? 'selected' : '' }}>
                                {{ $dev->name }} ({{ $dev->location ?? 'No location' }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">User Type</label>
                    <select name="type" class="form-select">
                        <option value="student" {{ $searchType === 'student' ? 'selected' : '' }}>Students</option>
                        <option value="teacher" {{ $searchType === 'teacher' ? 'selected' : '' }}>Teachers / Staff</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Search Member</label>
                    <input type="text" name="q" class="form-control" value="{{ $searchQuery }}" placeholder="Name, ID or Roll...">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if($searchResults->isNotEmpty())
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0">Search Results ({{ $searchResults->count() }} found)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Identifier</th>
                            <th>Status on Device</th>
                            <th>Terminal User ID</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($searchResults as $index => $result)
                            @php
                                $isMapped = in_array($result->id, (array)$existingMappings);
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td class="fw-bold">{{ $result->full_name ?? $result->name }}</td>
                                <td>{{ $result->student_id ?? $result->employee_code ?? ('ID: ' . $result->id) }}</td>
                                <td>
                                    @if($isMapped)
                                        <span class="badge bg-success"><i class="bi bi-check-circle"></i> Enrolled</span>
                                    @else
                                        <span class="badge bg-secondary">Not Enrolled</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$isMapped && $deviceId)
                                        <form method="POST" action="{{ route('admin.attendance.face.enroll.map') }}" class="d-flex gap-2">
                                            @csrf
                                            <input type="hidden" name="device_id" value="{{ $deviceId }}">
                                            <input type="hidden" name="user_type" value="{{ $searchType === 'student' ? 'students' : 'teachers' }}">
                                            <input type="hidden" name="user_id" value="{{ $result->id }}">
                                            <input type="text" name="device_user_id" class="form-control form-control-sm" style="max-width: 150px;" placeholder="e.g. 1001" required>
                                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                        </form>
                                    @elseif($isMapped)
                                        <span class="text-muted">Registered</span>
                                    @else
                                        <small class="text-muted">Select device above</small>
                                    @endif
                                </td>
                                <td>
                                    @if($isMapped)
                                        <small class="text-success"><i class="bi bi-check2"></i> Ready</small>
                                    @else
                                        <small class="text-muted">Assign ID to link</small>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif(!empty($searchQuery))
        <div class="card shadow-sm text-center py-5">
            <div class="card-body">
                <i class="bi bi-search text-muted" style="font-size: 2.5rem;"></i>
                <h5 class="mt-3">No members found</h5>
                <p class="text-muted">Try searching with a different name or ID.</p>
            </div>
        </div>
    @endif
</div>
@endsection
