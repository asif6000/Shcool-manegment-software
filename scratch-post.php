<?php

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

var_export(Illuminate\Support\Facades\DB::table('fee_types')->get()->all());
echo PHP_EOL;
var_export(Illuminate\Support\Facades\DB::table('audit_logs')->select('event')->distinct()->get()->pluck('event')->all());
echo PHP_EOL;
