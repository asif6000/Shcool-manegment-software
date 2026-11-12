<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Attendance methods
    |--------------------------------------------------------------------------
    |
    | Every method writes through App\Services\StudentAttendanceService. The
    | source column on a row records which method produced it, so a delayed
    | device event can be recognised and never overwrite a manual correction.
    |
    */

    'sources' => [
        'manual' => 'Manual entry',
        'qr' => 'ID card QR',
        'fingerprint' => 'Fingerprint device',
        'face' => 'Face recognition',
    ],

    'statuses' => ['present', 'absent', 'late', 'leave', 'excused'],

    /*
    |--------------------------------------------------------------------------
    | Percentage policy
    |--------------------------------------------------------------------------
    |
    | One definition, used by the staff report, the CSV export and the student
    | portal. A working day is a calendar day that is neither a weekly holiday
    | (Sat-first indices, so 1..5 is Sunday to Thursday) nor a recorded holiday.
    | Leave and excused are reported separately and removed from the denominator
    | of the adjusted rate.
    |
    */

    'working_days' => [1, 2, 3, 4, 5],

    'attended_statuses' => ['present', 'late'],

    'excluded_from_denominator' => ['leave', 'excused'],

    /*
    |--------------------------------------------------------------------------
    | Teacher scope
    |--------------------------------------------------------------------------
    |
    | Teachers may only open and mark the class/section pairs they have periods
    | for in the timetable. These roles are unrestricted.
    |
    */

    'unrestricted_roles' => ['school_admin', 'principal'],

    /*
    |--------------------------------------------------------------------------
    | QR ID cards
    |--------------------------------------------------------------------------
    |
    | Cards carry a random token; only its SHA-256 hash is stored, so a leaked
    | database cannot be replayed as a card.
    |
    */

    'qr' => [
        'token_bytes' => 32,
        'throttle' => 'attendance-scan',
    ],

    /*
    |--------------------------------------------------------------------------
    | Biometric devices
    |--------------------------------------------------------------------------
    |
    | No fingerprint or face manufacturer has been selected. The drivers below
    | are read from the environment and stay null until a real device or provider
    | is contracted; a null driver means the integration is NOT_CONFIGURED and
    | event ingestion is refused rather than simulated.
    |
    */

    'devices' => [
        'statuses' => ['not_configured', 'ready', 'disconnected', 'error'],

        'event_statuses' => ['applied', 'duplicate', 'skipped', 'rejected'],

        'drivers' => [
            'fingerprint' => env('ATTENDANCE_FINGERPRINT_DRIVER'),
            'face' => env('ATTENDANCE_FACE_DRIVER', 'hikvision'),
        ],

        // Driver name => App\Services\Attendance\DeviceAdapter class.
        'adapters' => [
            'hikvision' => \App\Services\Attendance\Adapters\HikvisionFaceAdapter::class,
        ],

        // A device is only shown as ready when it has authenticated within this
        // window; a stale heartbeat is reported as disconnected.
        'heartbeat_minutes' => 15,

        'ingest_max_batch' => 100,

        // Device clocks drift; an event older than this is refused as delayed
        // rather than silently back-dated.
        'max_event_age_hours' => 72,

        'max_clock_skew_minutes' => 15,
    ],

];
