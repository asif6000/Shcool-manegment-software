<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();

            $table->foreignId('from_academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('to_academic_session_id')->nullable()->constrained('academic_sessions')->restrictOnDelete();

            $table->foreignId('from_enrollment_id')->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('to_enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();

            $table->foreignId('from_class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('from_section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->unsignedInteger('from_roll_no')->nullable();

            $table->foreignId('to_class_id')->nullable()->constrained('classes')->restrictOnDelete();
            $table->foreignId('to_section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->unsignedInteger('to_roll_no')->nullable();

            $table->string('action', 20);
            $table->string('status', 20)->default('completed');
            $table->string('eligibility', 20)->nullable();

            $table->foreignId('result_exam_id')->nullable()->constrained('exams')->nullOnDelete();
            $table->json('result_snapshot')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('promoted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('promoted_at');
            $table->timestamps();

            $table->unique(
                ['school_id', 'student_id', 'from_academic_session_id'],
                'student_promotions_student_from_session_unique'
            );
            $table->index(['school_id', 'action', 'promoted_at'], 'student_promotions_action_date_index');
            $table->index(
                ['school_id', 'to_academic_session_id', 'to_class_id', 'to_section_id'],
                'student_promotions_target_index'
            );
            $table->index(['school_id', 'student_id'], 'student_promotions_student_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_promotions');
    }
};
