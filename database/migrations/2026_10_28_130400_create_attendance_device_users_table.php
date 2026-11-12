<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_device_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_device_id')->constrained('attendance_devices')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // The identifier the terminal prints for this student. It is
            // provider-specific and never exposed to the student portal.
            $table->string('device_user_id', 191);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['attendance_device_id', 'device_user_id'], 'attendance_device_users_user_unique');
            $table->unique(['attendance_device_id', 'student_id'], 'attendance_device_users_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_device_users');
    }
};
