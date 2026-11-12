<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_types', function (Blueprint $table): void {
            if (! Schema::hasColumn('fee_types', 'category')) {
                $table->string('category', 40)->nullable()->after('description');
            }

            if (! Schema::hasColumn('fee_types', 'starts_on')) {
                $table->date('starts_on')->nullable()->after('frequency');
                $table->date('ends_on')->nullable()->after('starts_on');
            }
        });

        Schema::table('fee_structures', function (Blueprint $table): void {
            if (! Schema::hasColumn('fee_structures', 'starts_on')) {
                $table->date('starts_on')->nullable()->after('due_day');
                $table->date('ends_on')->nullable()->after('starts_on');
            }
        });

        // A class-level structure cannot express "this fee applies to these
        // students, at this amount". Assignments materialise the chosen scope
        // into one row per student so the list can be previewed before it is
        // applied and so a duplicate assignment is caught by the database.
        if (! Schema::hasTable('fee_student_assignments')) {
            Schema::create('fee_student_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('school_id')->constrained()->restrictOnDelete();
                $table->foreignId('fee_type_id')->constrained('fee_types')->restrictOnDelete();
                $table->foreignId('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
                $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
                $table->foreignId('enrollment_id')->nullable()->constrained('enrollments')->nullOnDelete();
                $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
                $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
                // Null means "bill the class structure amount"; a value is the
                // authorised per-student amount.
                $table->decimal('amount', 14, 2)->nullable();
                $table->date('starts_on')->nullable();
                $table->date('ends_on')->nullable();
                $table->string('batch', 36)->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['school_id', 'academic_session_id', 'student_id'], 'fee_assignments_student_index');
                $table->index(['school_id', 'batch'], 'fee_assignments_batch_index');
            });
        }

        $duplicates = DB::table('fee_student_assignments')
            ->selectRaw('CONCAT(school_id, \':\', student_id, \':\', fee_type_id, \':\', academic_session_id) AS assignment_key, COUNT(*) AS rows_count')
            ->where('is_active', true)
            ->groupBy('assignment_key')
            ->havingRaw('COUNT(*) > 1')
            ->limit(10)
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Deactivate the duplicate active assignments before running this migration: '
                .$duplicates->map(fn (object $row): string => $row->assignment_key.' ('.$row->rows_count.' rows)')->implode(', ')
            );
        }

        // MySQL and MariaDB disagree about IF NOT EXISTS in DDL, so every step
        // is guarded from PHP instead.
        if (! Schema::hasColumn('fee_student_assignments', 'active_assignment_key')) {
            DB::statement(<<<'SQL'
ALTER TABLE `fee_student_assignments`
    ADD COLUMN `active_assignment_key` VARCHAR(96) CHARACTER SET ascii
        GENERATED ALWAYS AS (
            CASE
                WHEN `is_active` = 1
                THEN CONCAT(`school_id`, ':', `student_id`, ':', `fee_type_id`, ':', `academic_session_id`)
            END
        ) STORED
SQL);
        }

        $hasUniqueKey = DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['fee_student_assignments', 'fee_assignments_active_unique'],
        ) !== null;

        if (! $hasUniqueKey) {
            DB::statement('ALTER TABLE `fee_student_assignments` ADD UNIQUE KEY `fee_assignments_active_unique` (`active_assignment_key`)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_student_assignments');

        Schema::table('fee_structures', function (Blueprint $table): void {
            $table->dropColumn(['starts_on', 'ends_on']);
        });

        Schema::table('fee_types', function (Blueprint $table): void {
            $table->dropColumn(['category', 'starts_on', 'ends_on']);
        });
    }
};
