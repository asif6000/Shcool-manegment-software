<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->date('attendance_date');

            // One row per register (session + class + section + date). The
            // register itself is unique per student and day, so a second teacher
            // of the same section cannot file a parallel submission for it.
            $table->string('status', 20)->default('submitted');

            // The counts are frozen at submission time. A reviewer approves what
            // the teacher saw, and a later edit that changes a status shows up as
            // a mismatch instead of quietly rewriting history.
            $table->unsignedSmallInteger('student_count')->default(0);
            $table->unsignedSmallInteger('present_count')->default(0);
            $table->unsignedSmallInteger('absent_count')->default(0);
            $table->unsignedSmallInteger('late_count')->default(0);
            $table->unsignedSmallInteger('leave_count')->default(0);
            $table->unsignedSmallInteger('excused_count')->default(0);
            $table->unsignedSmallInteger('unmarked_count')->default(0);

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();

            // How many times the register was edited after it left the teacher's
            // hands, so a repeatedly reopened day is visible in the queue.
            $table->unsignedSmallInteger('revision_count')->default(0);
            $table->string('last_correction_reason')->nullable();
            $table->timestamps();

            $table->index(
                ['school_id', 'academic_session_id', 'class_id', 'section_id', 'attendance_date'],
                'attendance_submissions_register_index'
            );
            $table->index(['school_id', 'status', 'attendance_date'], 'attendance_submissions_queue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_submissions');
    }
};
