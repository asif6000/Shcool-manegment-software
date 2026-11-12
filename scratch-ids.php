<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$admin = DB::table('users')->where('email', 'admin@edunest.test')->first();
echo 'admin id=', $admin->id, ' must_change=', $admin->must_change_password, ' active=', $admin->is_active, "\n";

$session = DB::table('academic_sessions')->where('is_current', true)->first();
echo 'session ', $session?->id, ' ', $session?->name, "\n";

$class = DB::table('classes')->whereNull('deleted_at')->orderBy('id')->first();
echo 'class ', $class?->id, ' ', $class?->name, "\n";

$section = DB::table('sections')->where('class_id', $class?->id)->whereNull('deleted_at')->orderBy('id')->first();
echo 'section ', $section?->id, ' ', $section?->name, "\n";

echo 'school_context ', DB::table('schools')->value('id'), "\n";

$indebted = DB::table('fee_invoices')
    ->join('students', 'students.id', '=', 'fee_invoices.student_id')
    ->select('students.id', 'students.phone', 'students.first_name')
    ->whereNull('students.deleted_at')
    ->first();

echo 'student-with-invoice ', json_encode($indebted), "\n";
