<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ __('Identity card') }} · {{ $student->full_name }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/bengali.css') }}?v=1">
    <link rel="stylesheet" href="{{ asset('assets/css/id-card.css') }}?v=1">
</head>
<body>
    <div class="sheet-head screen-only">
        <div>
            <h1>{{ __('Identity card') }} · {{ $student->full_name }}</h1>
            <p>{{ __('Print at actual size on card paper, or cut to 85.6 × 54 mm.') }}</p>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="window.print()">{{ __('Print card') }}</button>
            <a class="btn btn-light" href="{{ route('students.show', $student) }}">{{ __('Back to student') }}</a>
            <a class="btn btn-light" href="{{ route('students.idcards.sheet', array_filter([
                'class_id' => $enrollment?->class_id,
                'section_id' => $enrollment?->section_id,
            ])) }}">{{ __('Print a whole class') }}</a>
        </div>
    </div>

    @include('students._idcard', ['compact' => false])

    <script src="{{ asset('assets/vendor/qrcode/qrcode.js') }}?v=1"></script>
    <script src="{{ asset('assets/js/id-card.js') }}?v=1"></script>
</body>
</html>
