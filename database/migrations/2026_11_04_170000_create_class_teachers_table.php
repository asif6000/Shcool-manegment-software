<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_teachers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'class_id', 'teacher_id'], 'class_teachers_unique');
            $table->index(['school_id', 'teacher_id'], 'class_teachers_teacher_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_teachers');
    }
};
