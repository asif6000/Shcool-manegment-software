<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, int>
     */
    private const COLUMNS = [
        'students' => 120,
        'guardians' => 120,
        'admissions' => 120,
        'classes' => 60,
        'subjects' => 80,
        'teachers' => 120,
        'employees' => 120,
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $length) {
            if (! Schema::hasColumn($table, 'name_bn')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('name_bn');
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $length) {
            if (Schema::hasColumn($table, 'name_bn')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($length): void {
                $blueprint->string('name_bn', $length)->nullable();
            });
        }
    }
};
