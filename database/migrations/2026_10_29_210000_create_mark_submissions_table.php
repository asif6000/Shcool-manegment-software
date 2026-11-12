<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mark_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('exam_subject_id')->constrained('exam_subjects')->cascadeOnDelete();

            // A paper can be taught by two teachers, one per section, so the
            // sheet handed over is the paper plus the section on the screen.
            $table->foreignId('section_id')->nullable()->constrained('sections')->restrictOnDelete();

            $table->string('status', 20)->default('submitted');

            // Frozen at submission time: a reviewer signs off the numbers the
            // teacher saw, and a later edit shows up as drift instead of
            // quietly rewriting the sheet.
            $table->unsignedSmallInteger('enrolled_count')->default(0);
            $table->unsignedSmallInteger('entered_count')->default(0);
            $table->unsignedSmallInteger('absent_count')->default(0);
            $table->unsignedSmallInteger('unmarked_count')->default(0);

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();

            $table->unsignedSmallInteger('revision_count')->default(0);
            $table->string('last_correction_reason')->nullable();
            $table->timestamps();

            $table->index(['exam_subject_id', 'section_id'], 'mark_submissions_sheet_index');
            $table->index(['school_id', 'status', 'submitted_at'], 'mark_submissions_queue_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mark_submissions');
    }
};
