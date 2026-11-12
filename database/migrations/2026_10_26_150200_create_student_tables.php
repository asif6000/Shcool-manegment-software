<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('student_id', 30);
            $table->string('admission_no', 30);
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth');
            $table->string('blood_group', 5)->nullable();
            $table->string('religion', 20)->nullable();
            $table->string('nationality', 40)->default('Bangladeshi');
            $table->string('photo_path')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('present_address')->nullable();
            $table->text('permanent_address')->nullable();
            $table->string('status', 20)->default('active');
            $table->date('admission_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'student_id'], 'students_school_student_id_unique');
            $table->unique(['school_id', 'admission_no'], 'students_school_admission_no_unique');
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'last_name', 'first_name'], 'students_school_name_index');
        });

        Schema::create('guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 120);
            $table->string('relation', 20)->default('guardian');
            $table->string('phone', 30);
            $table->string('alt_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('occupation', 80)->nullable();
            $table->string('nid', 30)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'phone']);
            $table->index(['school_id', 'email']);
        });

        Schema::create('student_guardians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('guardian_id')->constrained('guardians')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->timestamps();

            $table->unique(['student_id', 'guardian_id'], 'student_guardians_unique');
            $table->index(['school_id', 'guardian_id']);
        });

        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('applied_class_id')->constrained('classes')->restrictOnDelete();
            $table->string('application_no', 30);
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth');
            $table->string('guardian_name', 120);
            $table->string('guardian_phone', 30);
            $table->string('guardian_email')->nullable();
            $table->text('address')->nullable();
            $table->string('previous_school')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'application_no'], 'admissions_school_application_unique');
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'academic_session_id', 'status'], 'admissions_session_status_index');
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->unsignedInteger('roll_no')->nullable();
            $table->date('enrolled_on');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(
                ['school_id', 'student_id', 'academic_session_id'],
                'enrollments_student_session_unique'
            );
            $table->unique(
                ['school_id', 'academic_session_id', 'class_id', 'section_id', 'roll_no'],
                'enrollments_roll_unique'
            );
            $table->index(['school_id', 'class_id', 'section_id', 'status'], 'enrollments_class_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('admissions');
        Schema::dropIfExists('student_guardians');
        Schema::dropIfExists('guardians');
        Schema::dropIfExists('students');
    }
};
