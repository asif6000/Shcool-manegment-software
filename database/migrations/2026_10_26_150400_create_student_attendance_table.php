<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->date('attendance_date');
            $table->string('status', 20)->default('present');
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->string('remarks')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'student_id', 'attendance_date'], 'student_attendance_student_date_unique');
            $table->index(
                ['school_id', 'class_id', 'section_id', 'attendance_date'],
                'student_attendance_class_date_index'
            );
            $table->index(['school_id', 'status', 'attendance_date'], 'student_attendance_status_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
    }
};
