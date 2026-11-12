<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\PermanentDeleteService;

$models = [
    'SchoolClass', 'Section', 'Subject', 'AcademicSession', 'Holiday', 'Student', 'Teacher',
    'Employee', 'User', 'Guardian', 'Notice', 'Homework', 'Lesson', 'StudyMaterial', 'ClassVideo',
    'Exam', 'ExamSubject', 'FeeInvoice', 'FeePayment', 'Expense', 'LeaveType', 'SalaryStructure',
];

foreach ($models as $name) {
    $class = 'App\\Models\\'.$name;

    if (! class_exists($class)) {
        echo str_pad($name, 20)."MISSING MODEL\n";

        continue;
    }

    $row = $class::query()->withoutGlobalScopes()->first();

    if (! $row) {
        echo str_pad($name, 20)."no rows\n";

        continue;
    }

    try {
        $p = app(PermanentDeleteService::class)->plan($row);
        $steps = array_map(fn (array $s): string => $s['table'].'('.count($s['values']).')', $p['steps']);
        echo str_pad($name, 20)
            .'id='.$row->getKey()
            .' counts='.json_encode($p['counts'])
            .' detached='.json_encode($p['detached'])
            .' files='.count($p['files'])
            .' cleanup='.count($p['cleanup'])
            ."\n  steps: ".($steps === [] ? '(none)' : implode(' -> ', $steps))
            ."\n  denials: ".($p['denials'] === [] ? '(none)' : implode(' | ', $p['denials']))."\n";
    } catch (Throwable $e) {
        echo str_pad($name, 20).'ERROR '.get_class($e).': '.$e->getMessage()."\n";
    }
}
