@extends('layouts.app')

@section('title', __('QR attendance'))

@section('content')
    @php
        $statusTones = [
            'applied' => 'is-success',
            'duplicate' => 'is-warning',
            'skipped' => 'is-warning',
            'rejected' => 'is-danger',
        ];
    @endphp

    <div class="page-header">
        <div>
            <span class="page-eyebrow"><i class="bi bi-qr-code-scan"></i>{{ __('Daily operations') }}</span>
            <h1 class="page-title">{{ __('QR attendance') }}</h1>
            <p class="page-subtitle">{{ __('Scan a student ID card with the camera, or paste the card code when no camera is available. Scans only mark today and never overwrite a corrected record.') }}</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('attendance.students.index') }}" class="btn btn-light-soft">
                <i class="bi bi-ui-checks"></i>{{ __('Manual register') }}
            </a>
        </div>
    </div>

    @include('attendance.partials.tabs', ['active' => 'qr'])

    <div class="row g-3">
        <div class="col-lg-7">
            <section class="ui-card p-lg mb-3">
                <div class="card-head mb-3">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Scan a card') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('The camera stays on this device; card codes are verified on the server.') }}</p>
                    </div>
                    <span class="badge-soft is-brand">{{ $today->translatedFormat('l, d F Y') }}</span>
                </div>

                <div class="qr-scanner" data-qr-scanner data-scan-url="{{ route('attendance.qr.scan') }}">
                    <div class="qr-viewport">
                        <video id="qr-video" playsinline muted aria-label="{{ __('Card camera preview') }}"></video>
                        <div class="qr-overlay" aria-hidden="true"></div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="button" class="btn btn-primary" data-camera-start>
                            <i class="bi bi-camera-video"></i>{{ __('Start camera') }}
                        </button>
                        <button type="button" class="btn btn-light-soft" data-camera-stop hidden>
                            <i class="bi bi-stop-circle"></i>{{ __('Stop camera') }}
                        </button>
                    </div>

                    <div class="alert alert-danger mt-3 mb-0" data-camera-error hidden role="alert"></div>
                    <div class="alert alert-info mt-3 mb-0" data-camera-insecure hidden role="status">
                        {{ __('Camera scanning needs HTTPS. Use the manual code entry below on an insecure connection.') }}
                    </div>

                    <hr class="my-3">

                    <form method="POST" action="{{ route('attendance.qr.scan') }}" class="d-flex gap-2 flex-wrap" data-manual-form>
                        @csrf
                        <div class="flex-grow-1">
                            <label class="form-label" for="qr-token">{{ __('Card code') }}</label>
                            <input id="qr-token" name="token" class="form-control" autocomplete="off" spellcheck="false" maxlength="256" placeholder="atn1_…">
                        </div>
                        <div class="align-self-end">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i>{{ __('Mark present') }}</button>
                        </div>
                    </form>

                    <div class="qr-result mt-3" data-scan-result role="status" aria-live="polite" hidden></div>
                </div>
            </section>

            <section class="ui-card">
                <div class="table-toolbar">
                    <div>
                        <h2 class="card-title mb-1">{{ __('Today’s scans') }}</h2>
                        <p class="text-muted-2 mb-0" style="font-size:.78rem;">{{ __('Only this school, only today, newest first.') }}</p>
                    </div>
                </div>

                @if ($recent->isEmpty())
                    <div class="empty-state">
                        <div class="empty-icon"><i class="bi bi-qr-code"></i></div>
                        <h3>{{ __('No scans yet today') }}</h3>
                        <p>{{ __('Scanned cards will appear here with their result.') }}</p>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Student') }}</th>
                                    <th scope="col">{{ __('Time') }}</th>
                                    <th scope="col">{{ __('Result') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recent as $event)
                                    <tr>
                                        <td>
                                            <div class="cell-person">
                                                <span class="avatar avatar-sm">
                                                    {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($event->student?->first_name ?? '', 0, 1).\Illuminate\Support\Str::substr($event->student?->last_name ?? '', 0, 1)) }}
                                                </span>
                                                <span>
                                                    <span class="name">{{ $event->student?->full_name ?? '—' }}</span>
                                                    <span class="email">{{ $event->student?->student_id }}</span>
                                                </span>
                                            </div>
                                        </td>
                                        <td>{{ $event->occurred_at?->format('H:i') }}</td>
                                        <td>
                                            <span class="badge-soft {{ $statusTones[$event->status] ?? 'is-info' }}">{{ $event->statusLabel() }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <div class="col-lg-5">
            @can('attendance.qr.manage')
                <section class="ui-card p-lg">
                    <div class="card-head mb-3">
                        <div>
                            <h2 class="card-title mb-1">{{ __('ID cards') }}</h2>
                            <p class="text-muted-2 mb-0" style="font-size:.8rem;">{{ __('Issue a card to print, or revoke a lost one. Reissuing invalidates the old code immediately.') }}</p>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('attendance.qr.index') }}" class="filter-form mb-3">
                        <div>
                            <label class="form-label" for="qr-class">{{ __('Class') }}</label>
                            <select id="qr-class" name="class_id" class="form-select form-select-sm" data-auto-submit>
                                <option value="">{{ __('Select a class') }}</option>
                                @foreach ($classes as $option)
                                    <option value="{{ $option->id }}" @selected($class?->id === $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="qr-section">{{ __('Section') }}</label>
                            <select id="qr-section" name="section_id" class="form-select form-select-sm" data-auto-submit @disabled($class === null)>
                                <option value="">{{ __('All sections') }}</option>
                                @foreach ($class?->sections ?? [] as $option)
                                    <option value="{{ $option->id }}" @selected($section?->id === $option->id)>{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </form>

                    @if ($class === null)
                        <p class="text-muted-2 mb-0" style="font-size:.82rem;">{{ __('Choose a class to see its students.') }}</p>
                    @elseif ($roster->isEmpty())
                        <p class="text-muted-2 mb-0" style="font-size:.82rem;">{{ __('This class has no active students in the current session.') }}</p>
                    @else
                        <div class="d-flex flex-column gap-2">
                            @foreach ($roster as $entry)
                                @php($student = $entry['enrollment']->student)
                                <div class="d-flex align-items-center gap-2 flex-wrap border rounded-3 p-2">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold">{{ $student?->full_name }}</div>
                                        <div class="text-muted-2" style="font-size:.75rem;">
                                            {{ $student?->student_id }} · {{ $entry['enrollment']->section?->name ?? '—' }}
                                            @if ($entry['card'])
                                                · <span class="badge-soft is-success">{{ __('Active card') }}</span>
                                            @else
                                                · <span class="badge-soft is-info">{{ __('No card') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('attendance.cards.issue', $student) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm {{ $entry['card'] ? 'btn-light-soft' : 'btn-primary' }}">
                                            <i class="bi bi-person-vcard"></i>{{ $entry['card'] ? __('Reissue') : __('Issue') }}
                                        </button>
                                    </form>
                                    @if ($entry['card'])
                                        <form method="POST" action="{{ route('attendance.cards.revoke', $entry['card']) }}" data-confirm="{{ __('Revoke this card? The printed code will stop working immediately.') }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-link text-danger">{{ __('Revoke') }}</button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endcan
        </div>
    </div>
@endsection

@push('head')
<style>
    .qr-viewport { position: relative; width: 100%; aspect-ratio: 4 / 3; background: #0f172a; border-radius: 16px; overflow: hidden; }
    .qr-viewport video { width: 100%; height: 100%; object-fit: cover; display: block; }
    .qr-overlay { position: absolute; inset: 12%; border: 2px solid rgba(255,255,255,.85); border-radius: 16px; box-shadow: 0 0 0 9999px rgba(15,23,42,.35); }
    .qr-result { border-radius: 12px; padding: 1rem 1.1rem; font-size: .9rem; }
    .qr-result.is-success { background: rgba(22,163,74,.12); border: 1px solid rgba(22,163,74,.35); }
    .qr-result.is-warning { background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.35); }
    .qr-result.is-danger { background: rgba(220,38,38,.12); border: 1px solid rgba(220,38,38,.35); }
</style>
@endpush

@push('scripts')
<script src="{{ asset('assets/vendor/jsqr/jsQR.js') }}"></script>
<script>
(function () {
    const scanner = document.querySelector('[data-qr-scanner]');
    if (!scanner) return;

    const video = document.getElementById('qr-video');
    const startButton = scanner.querySelector('[data-camera-start]');
    const stopButton = scanner.querySelector('[data-camera-stop]');
    const errorBox = scanner.querySelector('[data-camera-error]');
    const insecureBox = scanner.querySelector('[data-camera-insecure]');
    const resultBox = scanner.querySelector('[data-scan-result]');
    const manualForm = scanner.querySelector('[data-manual-form]');
    const tokenInput = document.getElementById('qr-token');
    const scanUrl = scanner.getAttribute('data-scan-url');
    const csrf = (manualForm && manualForm.querySelector('input[name="_token"]'))
        ? manualForm.querySelector('input[name="_token"]').value
        : '';

    let stream = null;
    let frame = null;
    let lastToken = '';
    let lastTokenAt = 0;
    let busy = false;

    function showError(message) {
        errorBox.textContent = message;
        errorBox.hidden = false;
    }

    function hideError() {
        errorBox.hidden = true;
    }

    function showResult(status, message) {
        const tones = { applied: 'is-success', duplicate: 'is-warning', skipped: 'is-warning', rejected: 'is-danger' };
        resultBox.className = 'qr-result mt-3 ' + (tones[status] || 'is-info');
        resultBox.textContent = message || '';
        resultBox.hidden = false;
    }

    function stopCamera() {
        if (frame) {
            cancelAnimationFrame(frame);
            frame = null;
        }

        if (stream) {
            stream.getTracks().forEach(function (track) { track.stop(); });
            stream = null;
        }

        startButton.hidden = false;
        stopButton.hidden = true;
    }

    function detectFrame() {
        if (!stream) return;

        if (video.readyState === video.HAVE_ENOUGH_DATA && typeof window.jsQR === 'function') {
            const canvas = detectFrame.canvas || (detectFrame.canvas = document.createElement('canvas'));
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;

            const context = canvas.getContext('2d', { willReadFrequently: true });
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            const image = context.getImageData(0, 0, canvas.width, canvas.height);
            const code = window.jsQR(image.data, image.width, image.height, { inversionAttempts: 'dontInvert' });

            if (code && code.data) {
                submitToken(code.data);
            }
        }

        frame = requestAnimationFrame(detectFrame);
    }

    function startCamera() {
        hideError();

        if (! window.isSecureContext) {
            insecureBox.hidden = false;
            return;
        }

        if (! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia) {
            showError(@json(__('This browser does not expose a camera to the page.')));
            return;
        }

        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
            .then(function (mediaStream) {
                stream = mediaStream;
                video.srcObject = mediaStream;
                startButton.hidden = true;
                stopButton.hidden = false;

                return video.play();
            })
            .then(function () {
                detectFrame();
            })
            .catch(function (error) {
                stopCamera();

                if (error && (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError')) {
                    showError(@json(__('Camera permission was denied. Allow the camera in the browser settings, or use the manual code entry.')));
                } else if (error && error.name === 'NotFoundError') {
                    showError(@json(__('No camera was found on this device. Use the manual code entry.')));
                } else {
                    showError(@json(__('The camera could not be started. Use the manual code entry.')));
                }
            });
    }

    async function submitToken(token) {
        const text = String(token || '').trim();

        if (text === '' || busy) return;

        const now = Date.now();

        if (text === lastToken && now - lastTokenAt < 4000) {
            return;
        }

        lastToken = text;
        lastTokenAt = now;
        busy = true;

        try {
            const response = await fetch(scanUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ token: text }),
            });

            const data = await response.json();

            showResult(data.status || 'rejected', data.message || @json(__('The scan could not be applied.')));

            if (data.status === 'applied' && tokenInput) {
                tokenInput.value = '';
            }
        } catch (error) {
            showResult('rejected', @json(__('The scan could not be sent. Check the connection and try again.')));
        } finally {
            busy = false;
        }
    }

    startButton.addEventListener('click', startCamera);
    stopButton.addEventListener('click', stopCamera);

    if (manualForm) {
        manualForm.addEventListener('submit', function (event) {
            event.preventDefault();
            submitToken(tokenInput.value);
        });
    }

    if (! window.isSecureContext) {
        insecureBox.hidden = false;
    }

    window.addEventListener('pagehide', stopCamera);
})();
</script>
@endpush
