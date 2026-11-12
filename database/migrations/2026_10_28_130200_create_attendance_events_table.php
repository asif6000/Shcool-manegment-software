<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_device_id')->nullable()->constrained('attendance_devices')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->foreignId('student_attendance_id')->nullable()->constrained('student_attendances')->nullOnDelete();

            $table->string('source', 20);
            // Provider-side identifier for the punch. Unique per school+source so
            // a retried delivery updates the log instead of creating a second
            // attendance decision.
            $table->string('external_ref', 191);
            $table->string('device_user_id')->nullable();
            $table->dateTime('occurred_at');
            $table->dateTime('received_at');
            $table->string('status', 20);
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'source', 'external_ref'], 'attendance_events_external_unique');
            $table->index(['school_id', 'status', 'occurred_at'], 'attendance_events_status_time_index');
            $table->index(['school_id', 'student_id', 'occurred_at'], 'attendance_events_student_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
    }
};
