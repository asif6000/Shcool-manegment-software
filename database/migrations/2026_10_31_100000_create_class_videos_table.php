<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('sections')->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->restrictOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('source', 16);
            $table->string('youtube_url', 300)->nullable();
            $table->string('youtube_id', 16)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name', 160)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->boolean('show_on_website')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['school_id', 'show_on_website', 'created_at'], 'class_videos_website_index');
            $table->index(['school_id', 'class_id', 'section_id'], 'class_videos_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_videos');
    }
};
