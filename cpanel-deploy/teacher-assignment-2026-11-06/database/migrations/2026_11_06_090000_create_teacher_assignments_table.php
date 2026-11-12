<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The timetable answers "who teaches this section at 09:00 on Sunday".
     * The new Teacher Assignment screen answers the wider question the office
     * asks when it hands out subjects: "which teacher owns this class, section
     * and subject for the session". That pair has no period, so it cannot be a
     * class_routines row; class_teachers only carries the class. This table is
     * the assignment record, and TeachingScope reads it beside the routine.
     */
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->date('assigned_on');
            $table->string('status', 16)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['school_id', 'academic_session_id', 'teacher_id', 'class_id', 'section_id', 'subject_id'],
                'teacher_assignments_unique',
            );
            $table->index(['school_id', 'academic_session_id', 'teacher_id'], 'teacher_assignments_teacher_index');
            $table->index(
                ['school_id', 'academic_session_id', 'class_id', 'section_id', 'subject_id'],
                'teacher_assignments_period_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};
