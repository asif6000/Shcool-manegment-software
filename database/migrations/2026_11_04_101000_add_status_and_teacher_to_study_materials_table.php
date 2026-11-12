<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_materials', function (Blueprint $table): void {
            // The default keeps rows shared before this column existed visible;
            // the service writes draft or published explicitly on every save.
            $table->string('status', 16)->default('published')->after('subject_id');
            $table->timestamp('published_at')->nullable()->after('work_date');
            $table->foreignId('teacher_id')->nullable()->after('status')->constrained('teachers')->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->after('teacher_id')->constrained('lessons')->nullOnDelete();

            $table->index(['school_id', 'status', 'class_id'], 'study_materials_status_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('study_materials', function (Blueprint $table): void {
            $table->dropIndex('study_materials_status_period_index');
            $table->dropForeign('study_materials_teacher_id_foreign');
            $table->dropForeign('study_materials_lesson_id_foreign');
            $table->dropColumn(['status', 'published_at', 'teacher_id', 'lesson_id']);
        });
    }
};
