<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayment extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'salary_structure_id',
        'teacher_id',
        'employee_id',
        'salary_month',
        'basic_salary',
        'allowances_total',
        'deductions_total',
        'gross_salary',
        'net_salary',
        'payment_method',
        'reference_no',
        'paid_on',
        'status',
        'paid_by',
        'notes',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'salary_month' => 'date',
            'basic_salary' => 'decimal:2',
            'allowances_total' => 'decimal:2',
            'deductions_total' => 'decimal:2',
            'gross_salary' => 'decimal:2',
            'net_salary' => 'decimal:2',
            'paid_on' => 'date',
            'reversed_at' => 'datetime',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function staffName(): string
    {
        return $this->teacher?->full_name ?? $this->employee?->full_name ?? __('Unknown staff');
    }
}
