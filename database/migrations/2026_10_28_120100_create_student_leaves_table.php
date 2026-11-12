<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_session_id')->nullable()->constrained('academic_sessions')->nullOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->string('leave_type', 30)->default('casual');
            $table->text('reason');
            $table->string('contact_phone', 30)->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('decision_notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'from_date'], 'student_leaves_student_date_index');
            $table->index(['school_id', 'status', 'created_at'], 'student_leaves_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_leaves');
    }
};
