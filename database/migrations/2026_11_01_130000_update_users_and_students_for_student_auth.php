<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Update users table: make email nullable, add mobile_number if not present
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();

            if (! Schema::hasColumn('users', 'mobile_number')) {
                $table->string('mobile_number', 30)->nullable()->after('phone');
            }
        });

        // Clean up soft-deleted duplicate test data if any
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->update([
                'phone' => null,
                'mobile_number' => null,
            ]);

        // Sync existing phone to mobile_number for active users
        DB::table('users')
            ->whereNull('deleted_at')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->update(['mobile_number' => DB::raw('phone')]);

        // Add unique constraints if not already added
        Schema::table('users', function (Blueprint $table) {
            // Check if index already exists
            $indexes = collect(DB::select("SHOW INDEXES FROM users"))->pluck('Key_name')->unique();

            if (! $indexes->contains('users_phone_unique')) {
                $table->unique('phone', 'users_phone_unique');
            }

            if (! $indexes->contains('users_mobile_number_unique')) {
                $table->unique('mobile_number', 'users_mobile_number_unique');
            }
        });

        // 2. Update students table: add mobile_number column if not exists
        if (! Schema::hasColumn('students', 'mobile_number')) {
            Schema::table('students', function (Blueprint $table) {
                $table->string('mobile_number', 30)->nullable()->after('phone');
            });
        }

        if (Schema::hasColumn('students', 'phone')) {
            DB::table('students')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->whereNull('mobile_number')
                ->update(['mobile_number' => DB::raw('phone')]);
        }

        $studentIndexes = collect(DB::select("SHOW INDEXES FROM students"))->pluck('Key_name')->unique();
        if (! $studentIndexes->contains('students_mobile_number_index')) {
            Schema::table('students', function (Blueprint $table) {
                $table->index('mobile_number', 'students_mobile_number_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            if (Schema::hasColumn('students', 'mobile_number')) {
                $table->dropIndex('students_mobile_number_index');
                $table->dropColumn('mobile_number');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_phone_unique');
            $table->dropUnique('users_mobile_number_unique');

            if (Schema::hasColumn('users', 'mobile_number')) {
                $table->dropColumn('mobile_number');
            }

            $table->string('email')->nullable(false)->change();
        });
    }
};
