<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('school_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->unique('user_id', 'students_user_unique');
            $table->index(['school_id', 'user_id'], 'students_school_user_index');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropUnique('students_user_unique');
            $table->dropIndex('students_school_user_index');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
