@extends('layouts.app')

@section('title', __('Attendance methods'))

@section('content')
    @php
        $tones = [
            'ready' => 'is-success',
            'not_configured' => 'is-info',
            'disconnected' => 'is-warning',
            'error' => 'is-danger',
        ];
        $stateLabels = [
            'ready' => __('Ready'),
            'not_configured' => __('Not configured'),
            'disconnected' => __('Disconnected'),
            'error' => __('Error'),
        ];
        $eventTones = [
            'applied' => 'is-success',
            'duplicate' => 'is-warning',
            'skipped' => 'is-warning',
            'rejected' => 'is-danger',
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-router"></i>{{ __('Daily operations') }}</span>
            <h1 class="page-title">{{ __('Attendance methods') }}</h1>
            <p class="page-subtitle">{{ __('Every method writes through one attendance engine. Manual and QR are built in; a biometric method is only shown as ready once a real terminal has authenticated and its punches are accepted.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('attendance.students.index') }}" class="btn btn-light-soft">
                <i class="bi bi-ui-checks"></i>{{ __('Manual register') }}
            </a>
        </div>
    </div>

    @include('attendance.partials.tabs', ['active' => 'methods'])

    @if (session('device_secret'))
        @php($issued = session('device_secret'))
        <div class="alert alert-warning" role="alert">
            <div class="fw-semibold mb-1"><i class="bi bi-key me-1"></i>{{ __('Copy this secret now — it is shown once') }}</div>
            <p class="mb-2" style="font-size:.85rem;">{{ __('Device: :name. Only a hash is stored, so the value cannot be recovered later.', ['name' => $issued['name']]) }}</p>
            <code class="d-block text-break p-2 bg-white border rounded-3">{{ $issued['secret'] }}</code>
            <div class="form-text mt-2">{{ __('Enter it in the terminal over a secure channel. Rotating creates a new secret and invalidates this one immediately.') }}</div>
        </div>
    @endif

    <div class="row g-3 mb-3">
        @foreach ($methods as $method)
            <div class="col-md-6 col-xl-3">
                <section class="ui-card method-card h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <span class="method-card__icon"><i class="bi {{ $method['icon'] }}"></i></span>
                        <span class="badge-soft {{ $tones[$method['state']] ?? 'is-info' }}">{{ $stateLabels[$method['state']] ?? __('Not configured') }}</span>
                    </div>
                    <h2 class="card-title mb-1">{{ $method['label'] }}</h2>
                    <p class="text-muted-2 mb-2" style="font-size:.8rem;">{{ $method['summary'] }}</p>
                    <p class="method-card__metric mb-2">{{ $method['metric'] }}</p>
                    @if ($method['route'])
                        <a href="{{ $method['route'] }}" class="btn btn-sm btn-light-soft w-100">{{ $method['action'] }}</a>
                    @endif
                </section>
            </div>
        @endforeach
    </div>

    <div class="alert alert-info d-flex gap-2" role="note">
        <i class="bi bi-info-circle mt-1"></i>
        <div style="font-size:.82rem;">
            <strong>{{ __('Status is never typed by hand.') }}</strong>
            {{ __('A biometric method shows Ready only when an adapter is registered in the server configuration and the terminal has authenticated within the heartbeat window. Until then, event ingestion is refused.') }}
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <section class="ui-card mb-3">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Registered terminals') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.78rem;">{{ __('Each terminal authenticates with its own ID and secret. Secrets are hashed; only the hash is stored.') }}</p>
                    </div>
                </div>

                @if (! $canManage)
                    <div class="px-3 pb-3">
                        <p class="text-muted-2 mb-0" style="font-size:.82rem;">{{ __('You can review the state of each method. Adding a terminal, rotating credentials and mapping device users needs the device management permission.') }}</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('attendance.methods.devices.register') }}" class="px-3 pb-3">
                        @csrf
                        <div class="row g-2 align-items-end">
                            <div class="col-sm-4">
                                <label class="form-label" for="device-name">{{ __('Terminal name') }}</label>
                                <input id="device-name" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror" value="{{ old('name') }}" maxlength="120" placeholder="{{ __('Main gate') }}">
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label" for="device-kind">{{ __('Method') }}</label>
                                <select id="device-kind" name="kind" class="form-select form-select-sm @error('kind') is-invalid @enderror">
                                    <option value="fingerprint" @selected(old('kind') === 'fingerprint')>{{ __('Fingerprint') }}</option>
                                    <option value="face" @selected(old('kind') === 'face')>{{ __('Face recognition') }}</option>
                                </select>
                                @error('kind')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label" for="device-serial">{{ __('Serial') }}</label>
                                <input id="device-serial" name="serial" class="form-control form-control-sm" value="{{ old('serial') }}" maxlength="120">
                            </div>
                            <div class="col-sm-2">
                                <button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Register') }}</button>
                            </div>
                        </div>
                    </form>
                @endif

                @if ($devices->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-fingerprint"></i></div>
                        <h3>{{ __('No terminal registered') }}</h3>
                        <p>{{ __('Fingerprint and face recognition stay not configured until real hardware and a provider adapter exist.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Terminal') }}</th>
                                    <th scope="col">{{ __('Status') }}</th>
                                    <th scope="col">{{ __('Last sync') }}</th>
                                    <th scope="col">{{ __('Mapped') }}</th>
                                    <th scope="col" class="text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($devices as $device)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $device->name }}</div>
                                            <div class="text-muted-2" style="font-size:.75rem;">
                                                {{ $device->kindLabel() }}@if ($device->serial) · {{ $device->serial }}@endif
                                                · {{ $device->driver ?: __('No driver configured') }}
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge-soft {{ $tones[$device->effectiveStatus()] ?? 'is-info' }}">{{ $device->statusLabel() }}</span>
                                            @if (! $device->is_active)
                                                <div class="text-muted-2" style="font-size:.72rem;">{{ __('Disabled') }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($device->last_synced_at)
                                                {{ $device->last_synced_at->diffForHumans() }}
                                            @else
                                                <span class="text-muted-2">{{ __('Never') }}</span>
                                            @endif
                                            @if ($device->last_error)
                                                <div class="text-danger" style="font-size:.72rem;">{{ \Illuminate\Support\Str::limit($device->last_error, 60) }}</div>
                                            @endif
                                        </td>
                                        <td>{{ $device->mappings_count }}</td>
                                        <td class="text-end">
                                            @can('attendance.devices.manage')
                                                <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                                    <form method="POST" action="{{ route('attendance.methods.devices.toggle', $device) }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-light-soft">{{ $device->is_active ? __('Disable') : __('Enable') }}</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('attendance.methods.devices.rotate', $device) }}" data-confirm="{{ __('Rotate the secret? The terminal will stop authenticating until it is updated.') }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-light-soft">{{ __('Rotate secret') }}</button>
                                                    </form>
                                                </div>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            @if ($canManage)
                <section class="ui-card p-lg">
                    <div class="card-head mb-3">
                        <div>
                            <h2 class="card-title mb-1">{{ __('Device user mapping') }}</h2>
                            <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('The terminal only sends its own user number. Map it to a student here; an unmapped number is stored as a rejected event, never guessed.') }}</p>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('attendance.methods.index') }}" class="filter-form mb-3">
                        <div>
                            <label class="form-label" for="map-class">{{ __('Class') }}</label>
                            <select id="map-class" name="class_id" class="form-select form-select-sm" data-auto-submit>
                                <option value="">{{ __('Select a class') }}</option>
                                @foreach ($classes as $option)
                                    <option value="{{ $option->id }}" @selected($class?->id === $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="map-section">{{ __('Section') }}</label>
                            <select id="map-section" name="section_id" class="form-select form-select-sm" data-auto-submit @disabled($class === null)>
                                <option value="">{{ __('All sections') }}</option>
                                @foreach ($class?->sections ?? [] as $option)
                                    <option value="{{ $option->id }}" @selected($section?->id === $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>

                    @if ($devices->isEmpty())
                        <p class="text-muted-2 mb-0" style="font-size:.82rem;">{{ __('Register a terminal first.') }}</p>
                    @elseif ($class === null)
                        <p class="text-muted-2 mb-0" style="font-size:.82rem;">{{ __('Choose a class to see its students.') }}</p>
                    @elseif ($roster->isEmpty())
                        <p class="text-muted-2 mb-0" style="font-size:.82rem;">{{ __('This class has no active students in the current session.') }}</p>
                    @else
                        @foreach ($devices as $device)
                            <div class="border rounded-3 p-3 mb-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                    <div>
                                        <div class="fw-semibold">{{ $device->name }}</div>
                                        <div class="text-muted-2" style="font-size:.75rem;">{{ $device->kindLabel() }}</div>
                                    </div>
                                    <span class="badge-soft {{ $tones[$device->effectiveStatus()] ?? 'is-info' }}">{{ $device->statusLabel() }}</span>
                                </div>

                                @php($deviceMappings = $mappings->get($device->id, collect()))
                                @if ($deviceMappings->isNotEmpty())
                                    <div class="d-flex flex-column gap-1 mb-2">
                                        @foreach ($deviceMappings as $mapping)
                                            <div class="d-flex align-items-center gap-2 flex-wrap" style="font-size:.8rem;">
                                                <code>{{ $mapping->device_user_id }}</code>
                                                <span class="text-muted-2">→</span>
                                                <span>{{ $mapping->student?->full_name }}</span>
                                                <span class="text-muted-2">{{ $mapping->student?->student_id }}</span>
                                                <form method="POST" action="{{ route('attendance.methods.devices.unmap', [$device, $mapping]) }}" class="ms-auto" data-confirm="{{ __('Remove this mapping?') }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-link text-danger p-0">{{ __('Remove') }}</button>
                                                </form>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('attendance.methods.devices.map', $device) }}" class="d-flex gap-2 flex-wrap align-items-end">
                                    @csrf
                                    <div style="min-width:9rem;">
                                        <label class="form-label" for="device-user-{{ $device->id }}">{{ __('Device user ID') }}</label>
                                        <input id="device-user-{{ $device->id }}" name="device_user_id" class="form-control form-control-sm" maxlength="191" required placeholder="1001">
                                    </div>
                                    <div class="flex-grow-1" style="min-width:12rem;">
                                        <label class="form-label" for="map-student-{{ $device->id }}">{{ __('Student') }}</label>
                                        <select id="map-student-{{ $device->id }}" name="student_id" class="form-select form-select-sm" required>
                                            <option value="">{{ __('Select a student') }}</option>
                                            @foreach ($roster as $enrollment)
                                                <option value="{{ $enrollment->student_id }}">
                                                    {{ $enrollment->student?->full_name }} · {{ $enrollment->student?->student_id }}@if ($enrollment->section) · {{ $enrollment->section->name }}@endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Map') }}</button>
                                </form>
                            </div>
                        @endforeach
                    @endif
                </section>
            @endif
        </div>

        <div class="col-xl-5">
            <section class="ui-card mb-3">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Recent automated events') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.78rem;">{{ __('Rejected and duplicate punches are kept so a missing mark can be explained.') }}</p>
                    </div>
                </div>

                @if ($recent->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-clock-history"></i></div>
                        <h3>{{ __('No events yet') }}</h3>
                        <p>{{ __('Card scans and terminal punches will appear here with the decision the server made.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Time') }}</th>
                                    <th scope="col">{{ __('Method') }}</th>
                                    <th scope="col">{{ __('Student') }}</th>
                                    <th scope="col">{{ __('Result') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recent as $event)
                                    <tr>
                                        <td style="font-size:.78rem;">{{ $event->received_at?->format('d M H:i') }}</td>
                                        <td style="font-size:.78rem;">
                                            {{ __(':method', ['method' => config('attendance.sources.'.$event->source) ?? $event->source]) }}
                                            @if ($event->device)
                                                <div class="text-muted-2" style="font-size:.72rem;">{{ $event->device->name }}</div>
                                            @endif
                                        </td>
                                        <td style="font-size:.78rem;">{{ $event->student?->full_name ?? ($event->device_user_id ?: '—') }}</td>
                                        <td>
                                            <span class="badge-soft {{ $eventTones[$event->status] ?? 'is-info' }}">{{ $event->statusLabel() }}</span>
                                            <div class="text-muted-2" style="font-size:.72rem;">{{ $event->reasonLabel() }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="ui-card p-lg mb-3">
                <div class="card-head mb-2">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Before a biometric driver is written') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('These details come from the manufacturer. Guessing them would produce an integration that looks connected but is not.') }}</p>
                    </div>
                </div>
                <ul class="checklist">
                    @foreach ($checklist as $item)
                        <li><i class="bi bi-dot"></i>{{ __($item) }}</li>
                    @endforeach
                </ul>
            </section>

            <section class="ui-card p-lg">
                <div class="card-head mb-2">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Face recognition safeguards') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Not enabled. These rules apply before any face method may be turned on.') }}</p>
                    </div>
                </div>
                <ul class="checklist">
                    <li><i class="bi bi-dot"></i>{{ __('Written consent from the guardian, with a lawful basis for processing a minor’s biometric data.') }}</li>
                    <li><i class="bi bi-dot"></i>{{ __('Templates stay with the provider or terminal; this application stores only the outcome and a reference.') }}</li>
                    <li><i class="bi bi-dot"></i>{{ __('A documented retention period and a deletion path when a student leaves or consent is withdrawn.') }}</li>
                    <li><i class="bi bi-dot"></i>{{ __('Liveness checks where the hardware supports them, and a manual or QR fallback for every student.') }}</li>
                    <li><i class="bi bi-dot"></i>{{ __('Access limited to staff with the attendance permission; no biometric payload in logs or API responses.') }}</li>
                </ul>
            </section>
        </div>
    </div>
@endsection

@push('head')
<style>
    .method-card { padding: 1.15rem; }
    .method-card__icon {
        width: 38px; height: 38px; border-radius: 11px; display: grid; place-items: center;
        background: var(--brand-soft, rgba(37,99,235,.12)); color: var(--brand, #2563eb); font-size: 1.05rem;
    }
    .method-card__metric { font-size: .78rem; font-weight: 600; color: var(--bs-body-color); }
    .checklist { list-style: none; margin: 0; padding: 0; }
    .checklist li {
        display: flex; gap: .35rem; align-items: flex-start; font-size: .8rem;
        color: var(--bs-secondary-color); padding: .3rem 0; border-bottom: 1px dashed rgba(15,23,42,.06);
    }
    .checklist li:last-child { border-bottom: 0; }
    .checklist li i { color: var(--brand, #2563eb); font-size: 1.1rem; line-height: 1; }
</style>
@endpush
