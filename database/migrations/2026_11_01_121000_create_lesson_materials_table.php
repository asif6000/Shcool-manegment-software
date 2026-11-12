<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->string('kind', 16)->default('document');
            $table->string('title', 160);
            $table->string('file_path');
            $table->string('file_name', 160)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->timestamps();

            $table->index(['lesson_id', 'kind'], 'lesson_materials_lesson_kind_index');
            $table->index(['school_id', 'lesson_id'], 'lesson_materials_school_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_materials');
    }
};
