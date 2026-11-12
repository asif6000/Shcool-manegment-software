<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$tables = ['fee_invoice_items', 'fee_payments', 'fee_payment_allocations', 'fee_receipts', 'student_results', 'exam_marks', 'student_attendances'];

foreach ($tables as $table) {
    $lines = [];

    foreach (DB::select("describe {$table}") as $column) {
        if ($column->Field === 'id' || in_array($column->Field, ['created_at', 'updated_at', 'deleted_at'], true)) {
            continue;
        }

        $lines[] = sprintf(
            '%s %s %s%s',
            $column->Field,
            $column->Type,
            $column->Null === 'NO' ? 'NOT NULL' : 'null',
            $column->Default === null ? '' : ' default='.$column->Default
        );
    }

    echo "=== {$table}\n  ".implode("\n  ", $lines)."\n";
}
