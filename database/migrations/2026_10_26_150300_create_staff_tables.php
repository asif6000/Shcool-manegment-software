<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 30);
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('designation', 80)->default('Teacher');
            $table->date('joining_date');
            $table->string('qualification')->nullable();
            $table->string('specialization')->nullable();
            $table->string('employment_type', 20)->default('full_time');
            $table->string('status', 20)->default('active');
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'employee_code'], 'teachers_school_employee_code_unique');
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'phone']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 30);
            $table->string('first_name', 80);
            $table->string('last_name', 80)->nullable();
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('designation', 80);
            $table->string('department', 80)->nullable();
            $table->date('joining_date');
            $table->string('employment_type', 20)->default('full_time');
            $table->string('status', 20)->default('active');
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['school_id', 'employee_code'], 'employees_school_employee_code_unique');
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'phone']);
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->string('name', 60);
            $table->string('code', 20)->nullable();
            $table->unsignedSmallInteger('max_days_per_year')->nullable();
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_id', 'code'], 'leave_types_school_code_unique');
            $table->unique(['school_id', 'name'], 'leave_types_school_name_unique');
        });

        Schema::create('staff_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 1);
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'status', 'start_date'], 'staff_leaves_status_index');
            $table->index(['school_id', 'teacher_id'], 'staff_leaves_teacher_index');
            $table->index(['school_id', 'employee_id'], 'staff_leaves_employee_index');
        });

        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->date('attendance_date');
            $table->string('status', 20)->default('present');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->string('remarks')->nullable();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['school_id', 'teacher_id', 'attendance_date'], 'staff_attendance_teacher_date_unique');
            $table->unique(['school_id', 'employee_id', 'attendance_date'], 'staff_attendance_employee_date_unique');
            $table->index(['school_id', 'attendance_date', 'status'], 'staff_attendance_date_status_index');
        });

        Schema::create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('house_allowance', 12, 2)->default(0);
            $table->decimal('transport_allowance', 12, 2)->default(0);
            $table->decimal('medical_allowance', 12, 2)->default(0);
            $table->decimal('other_allowance', 12, 2)->default(0);
            $table->decimal('gross_salary', 12, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['school_id', 'teacher_id', 'is_active'], 'salary_structures_teacher_index');
            $table->index(['school_id', 'employee_id', 'is_active'], 'salary_structures_employee_index');
        });

        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->restrictOnDelete();
            $table->foreignId('salary_structure_id')->nullable()->constrained('salary_structures')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->restrictOnDelete();
            $table->date('salary_month');
            $table->decimal('basic_salary', 12, 2);
            $table->decimal('allowances_total', 12, 2)->default(0);
            $table->decimal('deductions_total', 12, 2)->default(0);
            $table->decimal('gross_salary', 12, 2);
            $table->decimal('net_salary', 12, 2);
            $table->string('payment_method', 20)->default('cash');
            $table->string('reference_no', 80)->nullable();
            $table->date('paid_on');
            $table->string('status', 20)->default('paid');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'teacher_id', 'salary_month'], 'salary_payments_teacher_month_unique');
            $table->unique(['school_id', 'employee_id', 'salary_month'], 'salary_payments_employee_month_unique');
            $table->index(['school_id', 'status', 'salary_month'], 'salary_payments_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('salary_structures');
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('staff_leaves');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('teachers');
    }
};
