<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_materials', function (Blueprint $table): void {
            // Rows shared before this column existed were files for study,
            // so they stay 'material'; classwork is a deliberate choice now.
            $table->string('kind', 16)->default('material')->after('subject_id');
            $table->date('work_date')->nullable()->after('kind');
            $table->index(['school_id', 'kind', 'class_id'], 'study_materials_kind_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('study_materials', function (Blueprint $table): void {
            $table->dropIndex('study_materials_kind_period_index');
            $table->dropColumn(['kind', 'work_date']);
        });
    }
};
