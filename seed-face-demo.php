<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\School;
use App\Models\Student;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceUser;
use App\Models\AttendanceRule;
use App\Models\Setting;

echo "=== Setting up Face Recognition Demo Configuration ===\n";

$school = School::first();
if (!$school) {
    echo "No school found in database! Creating demo school...\n";
    $school = School::create([
        'name' => 'Dream Roots Millennium School',
        'code' => 'DRMS',
        'is_active' => true,
    ]);
}
echo "Using School: {$school->name} (ID: {$school->id})\n";

// 1. Configure Attendance Rules
$rule = AttendanceRule::updateOrCreate(
    ['school_id' => $school->id],
    [
        'name' => 'Demo School Schedule & Face Rules',
        'start_time' => '08:30:00',
        'end_time' => '15:00:00',
        'late_threshold_minutes' => 15,
        'very_late_threshold_minutes' => 60,
        'window_hours' => 12.00,
        'duplicate_interval_seconds' => 300,
        'allow_holiday_attendance' => false,
        'skip_weekends' => true,
        'working_days' => [1, 2, 3, 4, 5], // Sunday to Thursday
        'send_sms_on_present' => true,
        'send_sms_on_late' => true,
        'send_sms_to_guardian' => true,
        'max_checks_per_day' => 10,
        'is_active' => true,
        'priority' => 1,
    ]
);
echo "[OK] Attendance Rules configured (08:30 AM - 03:00 PM, Sun-Thu, 15m Late threshold)\n";

// 2. Set Face Recognition System Enabled & Applicable User Types
Setting::updateOrCreate(
    ['key' => 'face_recognition_enabled'],
    [
        'value' => '1',
        'type' => 'boolean',
        'group_name' => 'attendance',
    ]
);

Setting::updateOrCreate(
    ['key' => 'face_recognition_user_types'],
    [
        'value' => json_encode(['students', 'teachers', 'both']),
        'type' => 'json',
        'group_name' => 'attendance',
    ]
);
echo "[OK] System settings enabled (Applicable to Students & Teachers)\n";

// 3. Register Demo Face Scanner Device
$demoSecret = 'demo_secret_face_' . bin2hex(random_bytes(16));
$secretHash = hash('sha256', $demoSecret);

$device = AttendanceDevice::updateOrCreate(
    [
        'school_id' => $school->id,
        'serial' => 'HK-DSK1T671-DEMO01'
    ],
    [
        'name' => 'Main Gate Face Terminal 01',
        'kind' => 'face',
        'driver' => 'hikvision',
        'location' => 'Main Campus Entrance Gate',
        'secret_hash' => $secretHash,
        'status' => 'configured',
        'is_active' => true,
        'config' => [
            'model' => 'Hikvision DS-K1T671MF',
            'firmware' => 'V3.2.30_build231015',
            'lan_ip' => '192.168.1.120',
            'api_port' => 80,
            'protocol' => 'ISAPI/HTTPS',
        ],
    ]
);
echo "[OK] Registered Demo Device: {$device->name} (Model: Hikvision DS-K1T671MF)\n";
echo "     --> Plain Device Secret: {$demoSecret}\n";

// 4. Enroll Sample Students to this device
$students = Student::where('school_id', $school->id)->take(3)->get();
if ($students->count() > 0) {
    $devIdCounter = 1001;
    foreach ($students as $student) {
        $mapping = AttendanceDeviceUser::updateOrCreate(
            [
                'attendance_device_id' => $device->id,
                'student_id' => $student->id,
            ],
            [
                'school_id' => $school->id,
                'device_user_id' => (string)$devIdCounter,
            ]
        );
        $studentName = $student->user?->name ?? "Student #{$student->id}";
        echo "[OK] Enrolled {$studentName} (ID: {$student->id}) -> Terminal Device ID: {$devIdCounter}\n";
        $devIdCounter++;
    }
} else {
    echo "[INFO] No students found in database to enroll yet.\n";
}

echo "\n=== Demo Configuration Complete! ===\n";
