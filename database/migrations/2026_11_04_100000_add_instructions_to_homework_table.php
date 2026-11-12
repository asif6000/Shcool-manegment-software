<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework', function (Blueprint $table): void {
            // The task itself stays in description; this is the separate how-to
            // the spec asks for (materials needed, where to hand it in).
            $table->text('instructions')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('homework', function (Blueprint $table): void {
            $table->dropColumn('instructions');
        });
    }
};
