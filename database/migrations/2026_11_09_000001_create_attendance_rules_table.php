<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for attendance rules configuration.
     *
     * This table stores global attendance configuration:
     * - School start time
     * - Late threshold minutes
     * - Attendance window hours
     * - Duplicate scan interval
     * - Holiday handling settings
     */
    public function up(): void
    {
        Schema::create('attendance_rules', function (Blueprint $table) {
            $table->id();

            // Foreign key to school for multi-school support
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();

            // Rule name for admin reference
            $table->string('name')->default('Default Attendance Rules');

            // School working hours
            $table->time('start_time')->default('08:30:00');
            $table->time('end_time')->default('15:00:00');

            // Late arrival threshold in minutes after start time
            $table->integer('late_threshold_minutes')->default(15);

            // How late is "very late" (> this value = excused or skipped)
            $table->integer('very_late_threshold_minutes')->default(60);

            // Attendance capture window (hours around school day)
            $table->float('window_hours', 4, 2)->default(12.00);

            // Minimum seconds between duplicate scans from same device
            $table->integer('duplicate_interval_seconds')->default(300); // 5 minutes

            // Handle events during holidays
            $table->boolean('allow_holiday_attendance')->default(false);

            // Skip weekends entirely (if true, ignore Saturday/Sunday events)
            $table->boolean('skip_weekends')->default(true);

            // Working days of the week (array like [1,2,3,4,5] for Sun-Thu)
            $table->json('working_days')->nullable();

            // SMS notification settings
            $table->boolean('send_sms_on_present')->default(true);
            $table->boolean('send_sms_on_late')->default(false);
            $table->boolean('send_sms_to_guardian')->default(true);

            // Maximum daily attendance checks per user
            $table->integer('max_checks_per_day')->default(10);

            // Status flags
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0); // Lower number = higher priority

            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['school_id', 'is_active']);
            $table->unique(['school_id'], 'attendance_rules_school_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_rules');
    }
};
