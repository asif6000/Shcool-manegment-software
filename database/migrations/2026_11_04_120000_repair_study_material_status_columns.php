<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Some environments reported the study_materials table without the publishing
 * columns even though the original migration was marked as run. Adding them
 * defensively keeps the student portal and the teacher screens working on a
 * database whose schema drifted from the code.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('study_materials', 'status')) {
            Schema::table('study_materials', function (Blueprint $table): void {
                $table->string('status', 16)->default('published')->after('subject_id');
            });
        }

        if (! Schema::hasColumn('study_materials', 'published_at')) {
            Schema::table('study_materials', function (Blueprint $table): void {
                $table->timestamp('published_at')->nullable()->after('work_date');
            });
        }

        if (! Schema::hasColumn('study_materials', 'teacher_id')) {
            Schema::table('study_materials', function (Blueprint $table): void {
                $table->foreignId('teacher_id')->nullable()->after('status')->constrained('teachers')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('study_materials', 'lesson_id')) {
            Schema::table('study_materials', function (Blueprint $table): void {
                $table->foreignId('lesson_id')->nullable()->after('teacher_id')->constrained('lessons')->nullOnDelete();
            });
        }

        if (! Schema::hasIndex('study_materials', 'study_materials_status_period_index')) {
            Schema::table('study_materials', function (Blueprint $table): void {
                $table->index(['school_id', 'status', 'class_id'], 'study_materials_status_period_index');
            });
        }

        // Same deployment window as the study-material columns: a database that
        // missed one of the 2026_11_04 migrations usually missed both.
        if (! Schema::hasColumn('homework', 'instructions')) {
            Schema::table('homework', function (Blueprint $table): void {
                $table->text('instructions')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        // The columns are owned by the original migration; this repair only
        // fills gaps and must not remove data when it is rolled back.
    }
};
