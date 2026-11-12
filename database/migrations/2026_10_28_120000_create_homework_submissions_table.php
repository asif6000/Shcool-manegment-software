<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('homework_id')->constrained('homework')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
            $table->string('status', 20)->default('submitted');
            $table->text('text_answer')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name', 160)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resubmitted_at')->nullable();
            $table->decimal('marks', 6, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['homework_id', 'student_id'], 'homework_submissions_student_unique');
            $table->index(['school_id', 'student_id', 'submitted_at'], 'homework_submissions_student_index');
            $table->index(['homework_id', 'status'], 'homework_submissions_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_submissions');
    }
};
