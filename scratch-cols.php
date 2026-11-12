<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = [
    'homework', 'class_routines', 'study_materials', 'class_videos', 'lessons',
    'lesson_materials', 'guardians', 'students', 'teachers', 'employees',
    'subjects', 'classes', 'sections', 'academic_sessions', 'holidays',
    'exams', 'exam_subjects', 'exam_marks', 'fee_invoices', 'expenses',
    'vouchers', 'staff_leaves', 'leave_types', 'salary_structures', 'notices',
];

foreach ($tables as $table) {
    $rows = DB::select(
        'SELECT COLUMN_NAME c, IS_NULLABLE n, COLUMN_DEFAULT d, EXTRA e FROM information_schema.COLUMNS'
        .' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION', [$table]);

    if ($rows === []) {
        echo str_pad($table, 22)."MISSING TABLE\n";

        continue;
    }

    $needed = [];

    foreach ($rows as $row) {
        if ($row->n === 'NO' && $row->d === null && ! str_contains((string) $row->e, 'auto_increment')) {
            $needed[] = $row->c;
        }
    }

    echo str_pad($table, 22).($needed ? implode(', ', $needed) : '(nothing required)')."\n";
}
