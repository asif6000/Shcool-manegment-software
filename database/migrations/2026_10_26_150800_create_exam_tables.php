<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_scales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['school_id', 'name'], 'grade_scales_school_name_unique');
            $table->index(['school_id', 'is_default']);
        });

        Schema::create('grade_scale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_scale_id')->constrained('grade_scales')->cascadeOnDelete();
            $table->string('grade_letter', 5);
            $table->decimal('min_percentage', 5, 2);
            $table->decimal('max_percentage', 5, 2);
            $table->decimal('grade_point', 3, 2);
            $table->string('remarks', 80)->nullable();
            $table->timestamps();

            $table->unique(['grade_scale_id', 'grade_letter'], 'grade_scale_items_letter_unique');
            $table->index(['grade_scale_id', 'min_percentage'], 'grade_scale_items_range_index');
        });

        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('exam_type', 30)->default('term');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('draft');
            $table->text('description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'academic_session_id', 'name'], 'exams_session_name_unique');
            $table->index(['school_id', 'status', 'start_date'], 'exams_status_date_index');
        });

        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->date('exam_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('full_marks', 6, 2)->default(100);
            $table->decimal('pass_marks', 6, 2)->default(33);
            $table->timestamps();

            $table->unique(['exam_id', 'class_id', 'subject_id'], 'exam_subjects_unique');
            $table->index(['school_id', 'exam_id', 'class_id'], 'exam_subjects_exam_class_index');
        });

        Schema::create('exam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->decimal('marks_obtained', 6, 2)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->string('grade_letter', 5)->nullable();
            $table->decimal('grade_point', 3, 2)->nullable();
            $table->string('remarks', 160)->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['exam_subject_id', 'student_id'], 'exam_marks_unique');
            $table->index(['school_id', 'student_id'], 'exam_marks_student_index');
        });

        Schema::create('student_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->decimal('total_full_marks', 8, 2)->default(0);
            $table->decimal('total_obtained', 8, 2)->default(0);
            $table->decimal('gpa', 3, 2)->nullable();
            $table->string('grade_letter', 5)->nullable();
            $table->unsignedInteger('position')->nullable();
            $table->string('status', 20)->default('draft');
            $table->json('snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id'], 'student_results_unique');
            $table->index(['school_id', 'exam_id', 'status'], 'student_results_exam_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_results');
        Schema::dropIfExists('exam_marks');
        Schema::dropIfExists('exam_subjects');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('grade_scale_items');
        Schema::dropIfExists('grade_scales');
    }
};
