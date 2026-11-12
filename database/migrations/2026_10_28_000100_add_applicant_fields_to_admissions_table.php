<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            // The portal account that submitted the application. Walk-in
            // applications recorded by staff keep this null.
            $table->foreignId('user_id')
                ->nullable()
                ->after('school_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('photo_path', 255)->nullable()->after('previous_school');
            // Shown to the applicant. Internal notes stay private.
            $table->text('rejection_reason')->nullable()->after('notes');

            $table->index(['school_id', 'user_id'], 'admissions_school_user_index');
            $table->index(
                ['school_id', 'user_id', 'academic_session_id'],
                'admissions_applicant_session_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            $table->dropIndex('admissions_applicant_session_index');
            $table->dropIndex('admissions_school_user_index');
            $table->dropColumn(['photo_path', 'rejection_reason']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
