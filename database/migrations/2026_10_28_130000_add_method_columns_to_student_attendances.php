<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_attendances', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('status');
            $table->string('source_ref')->nullable()->after('source');
            $table->timestamp('locked_at')->nullable()->after('remarks');
            $table->string('correction_reason')->nullable()->after('locked_at');
            $table->foreignId('corrected_by')->nullable()->after('correction_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable()->after('corrected_by');

            $table->index(
                ['school_id', 'source', 'attendance_date'],
                'student_attendance_source_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('student_attendances', function (Blueprint $table) {
            $table->dropIndex('student_attendance_source_date_index');
            $table->dropConstrainedForeignId('corrected_by');
            $table->dropColumn([
                'source',
                'source_ref',
                'locked_at',
                'correction_reason',
                'corrected_at',
            ]);
        });
    }
};
