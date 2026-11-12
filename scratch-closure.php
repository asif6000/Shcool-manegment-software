<?php

// Read-only: walks the live foreign key graph from one student and prints the
// tables a full delete would have to reach, with real row counts.

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\ForeignKeyMap;
use Illuminate\Support\Facades\DB;

$map = app(ForeignKeyMap::class);
$studentId = (int) ($argv[1] ?? 1);
$protected = (array) config('deletions.protected');

$seen = [];
$queue = [['table' => 'students', 'ids' => [$studentId]]];
$rows = [];

while ($queue !== []) {
    $node = array_shift($queue);
    $table = $node['table'];

    foreach ($map->childrenOf($table) as $child) {
        $childTable = $child['table'];
        $ids = DB::table($childTable)->whereIn($child['column'], $node['ids'])->pluck('id')->filter()->values()->all();

        $rows[] = sprintf(
            '%-34s via %-22s %-8s %s rows=%d%s',
            $childTable,
            $table.'.'.$child['referenced'],
            $child['rule'],
            $child['column'],
            count($ids),
            in_array($childTable, $protected, true) ? '  [PROTECTED]' : ''
        );

        $key = $childTable.':'.$child['column'];

        if (isset($seen[$key]) || $ids === [] || $childTable === 'users') {
            $seen[$key] = true;

            continue;
        }

        $seen[$key] = true;
        $queue[] = ['table' => $childTable, 'ids' => $ids];
    }
}

echo "student #{$studentId}\n";
echo implode("\n", array_values(array_unique($rows))), "\n";
