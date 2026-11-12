<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->string('title', 160);
            $table->string('chapter_number', 20)->nullable();
            $table->string('chapter_name', 160)->nullable();
            $table->string('lesson_number', 20)->nullable();
            $table->text('description')->nullable();
            $table->text('objectives')->nullable();
            $table->longText('content')->nullable();
            $table->string('video_url', 300)->nullable();
            $table->string('youtube_id', 16)->nullable();
            $table->text('reference_links')->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'status', 'class_id', 'section_id'], 'lessons_period_status_index');
            $table->index(['school_id', 'subject_id'], 'lessons_subject_index');
            $table->index(['school_id', 'teacher_id'], 'lessons_teacher_index');
            $table->index(['school_id', 'updated_at'], 'lessons_recent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
