<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('Identity cards') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/bengali.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('assets/css/id-card.css') }}?v=1">
</head>
<body>
    <div class="sheet-head screen-only">
        <div>
            <h1>{{ __('Identity cards') }}</h1>
            <p>{{ trans_choice(':count card|:count cards on this sheet', $students->count(), ['count' => $students->count()]) }}</p>
        </div>
        <div class="actions">
            @if ($students->isNotEmpty())
                <button type="button" class="btn btn-primary" onclick="window.print()">{{ __('Print cards') }}</button>
            @endif
            <a class="btn btn-light" href="{{ route('students.index', $filters) }}">{{ __('Back to students') }}</a>
        </div>
    </div>

    @if ($blocked)
        <p class="hint">{{ __('This sheet prints :limit cards at a time. Filter to one class or section to keep the rest printable.', ['limit' => $limit]) }}</p>
    @endif

    @if ($students->isEmpty())
        <p class="hint">{{ __('No student matches this filter, so there is nothing to print. Choose a class or section and try again.') }}</p>
    @else
        <div class="card-grid">
            @foreach ($students as $cardStudent)
                @include('students._idcard', [
                    'student' => $cardStudent,
                    'enrollment' => $enrollments[$cardStudent->id] ?? null,
                    'verifyUrl' => $verifyUrls[$cardStudent->id],
                    'compact' => true,
                ])
            @endforeach
        </div>
    @endif

    <script src="{{ asset('assets/vendor/qrcode/qrcode.js') }}?v=1"></script>
    <script src="{{ asset('assets/js/id-card.js') }}?v=1"></script>
</body>
</html>
