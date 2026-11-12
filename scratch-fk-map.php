<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::select(
    "SELECT k.TABLE_NAME AS child, k.COLUMN_NAME AS col, k.REFERENCED_TABLE_NAME AS parent, r.DELETE_RULE AS rule "
    .'FROM information_schema.KEY_COLUMN_USAGE k '
    .'JOIN information_schema.REFERENTIAL_CONSTRAINTS r '
    .'ON k.CONSTRAINT_NAME = r.CONSTRAINT_NAME AND k.CONSTRAINT_SCHEMA = r.CONSTRAINT_SCHEMA '
    .'WHERE k.REFERENCED_TABLE_NAME IS NOT NULL AND k.TABLE_SCHEMA = DATABASE()'
);

$by = [];
foreach ($rows as $r) {
    $by[$r->parent][] = $r->rule.' '.$r->child.'('.$r->col.')';
}

$soft = [];
foreach (glob(__DIR__.'/app/Models/*.php') as $file) {
    if (str_contains((string) file_get_contents($file), 'SoftDeletes;')) {
        $name = basename($file, '.php');
        $instance = ('App\\Models\\'.$name);
        if (method_exists($instance, 'getTable')) {
            $soft[] = $name.':'.(new $instance)->getTable();
        }
    }
}

foreach (['classes', 'sections', 'subjects', 'academic_sessions', 'holidays', 'exams', 'exam_subjects', 'exam_marks', 'notices', 'homework', 'homework_submissions', 'lessons', 'lesson_materials', 'study_materials', 'teachers', 'employees', 'guardians', 'users', 'students', 'class_routines', 'class_subjects', 'leave_types', 'salary_structures', 'salary_payments', 'fee_invoices', 'fee_payments', 'fee_receipts', 'expenses', 'expense_categories', 'vouchers', 'cash_transactions', 'bank_accounts', 'class_videos', 'fee_types', 'fee_structures', 'fee_student_assignments', 'fee_discounts', 'schools', 'staff_attendances', 'staff_leaves', 'enrollments', 'student_attendances', 'student_results', 'attendance_devices', 'settings', 'admissions'] as $t) {
    echo str_pad($t, 26).' <= '.implode('  |  ', array_values(array_unique($by[$t] ?? ['NONE']))).PHP_EOL;
}

echo PHP_EOL.'SOFTDELETES => '.implode(', ', $soft).PHP_EOL;
