<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('Attendance card') }} · {{ $student->full_name }}</title>
    <link rel="stylesheet" href="{{ asset('assets/vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/bengali.css') }}?v=1">
    <style>
        :root { --ink: #0f172a; --brand: #1d4ed8; --muted: #64748b; }
        * { box-sizing: border-box; }
        body { background: #eef2f7; color: var(--ink); font-family: var(--font-sans), system-ui, -apple-system, "Segoe UI", sans-serif; margin: 0; padding: 24px; }
        .card-sheet { background: #fff; border-radius: 18px; padding: 18px 20px; box-shadow: 0 18px 40px rgba(15, 23, 42, .12); display: flex; gap: 18px; align-items: center; width: 420px; max-width: 100%; }
        .card-photo { width: 84px; height: 84px; border-radius: 16px; background: #e2e8f0; overflow: hidden; flex: 0 0 auto; display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--brand); font-size: 1.4rem; }
        .card-photo img { width: 100%; height: 100%; object-fit: cover; }
        .card-body { flex: 1 1 auto; min-width: 0; }
        .card-school { font-size: .72rem; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); margin: 0 0 4px; }
        .card-name { font-size: 1.12rem; font-weight: 700; margin: 0 0 2px; }
        .card-meta { font-size: .78rem; color: var(--muted); margin: 0; }
        .card-code { flex: 0 0 auto; text-align: center; }
        .card-code svg { display: block; width: 108px; height: 108px; }
        .card-code span { display: block; font-size: .62rem; color: var(--muted); margin-top: 4px; }
        .actions { display: flex; gap: 10px; margin-top: 18px; flex-wrap: wrap; }
        .actions .btn { border: 0; border-radius: 10px; padding: 9px 16px; font-size: .85rem; cursor: pointer; }
        .actions .btn-primary { background: var(--brand); color: #fff; }
        .actions .btn-light { background: #fff; color: var(--ink); box-shadow: inset 0 0 0 1px #cbd5e1; }
        .notice { margin-top: 18px; padding: 14px 16px; border-radius: 12px; background: #fff7ed; border: 1px solid #fed7aa; font-size: .82rem; width: 420px; max-width: 100%; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions, .notice { display: none; }
            .card-sheet { box-shadow: none; border: 1px solid #cbd5e1; border-radius: 12px; }
        }
    </style>
</head>
<body>
    @php
        $initials = \Illuminate\Support\Str::upper(
            \Illuminate\Support\Str::substr($student->first_name ?? '', 0, 1).
            \Illuminate\Support\Str::substr($student->last_name ?? '', 0, 1)
        );
    @endphp

    <div class="card-sheet">
        <div class="card-photo">
            @if ($student->photoUrl())
                <img src="{{ $student->photoUrl() }}" alt="">
            @else
                {{ $initials !== '' ? $initials : '·' }}
            @endif
        </div>

        <div class="card-body">
            <p class="card-school">{{ $student->school?->name ?? __('School') }}</p>
            <p class="card-name">{{ $student->full_name }}</p>
            <p class="card-meta">
                {{ __('Student ID') }}: {{ $student->student_id }}
                @if ($enrollment)
                    <br>{{ $enrollment->schoolClass?->name }}@if ($enrollment->section) · {{ $enrollment->section->name }}@endif
                    @if ($enrollment->roll_no) · {{ __('Roll') }} {{ $enrollment->roll_no }}@endif
                @endif
            </p>
        </div>

        <div class="card-code">
            <div id="card-qr" aria-label="{{ __('Attendance QR code') }}"></div>
            <span>{{ __('Scan for attendance') }}</span>
        </div>
    </div>

    @if ($token === null)
        <div class="notice">
            <strong>{{ __('The card code is shown once, when the card is issued.') }}</strong>
            {{ __('This page was reloaded or revisited, so the code is no longer available. Reissue the card to print a working copy.') }}
        </div>
    @endif

    <div class="actions">
        @if ($token !== null)
            <button type="button" class="btn btn-primary" onclick="window.print()">{{ __('Print card') }}</button>
        @endif
        <a class="btn btn-light" href="{{ route('attendance.qr.index', ['class_id' => $enrollment?->class_id]) }}">{{ __('Back to QR attendance') }}</a>
        @if ($token === null)
            <form method="POST" action="{{ route('attendance.cards.issue', $student) }}">
                @csrf
                <button type="submit" class="btn btn-primary">{{ __('Reissue card') }}</button>
            </form>
        @endif
    </div>

    @if ($token !== null)
        <script src="{{ asset('assets/vendor/qrcode/qrcode.js') }}"></script>
        <script>
            (function () {
                const token = @json($token);
                const target = document.getElementById('card-qr');

                if (! target || typeof qrcode !== 'function') return;

                const code = qrcode(0, 'M');
                code.addData(token);
                code.make();
                target.innerHTML = code.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
            })();
        </script>
    @endif
</body>
</html>
