<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendance_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();

            // The QR payload is never stored: only its SHA-256 hash, so a leaked
            // database dump cannot be replayed at the scanner.
            $table->string('token_hash', 64)->unique();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'student_id', 'revoked_at'], 'attendance_cards_student_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendance_cards');
    }
};
