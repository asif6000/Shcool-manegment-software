<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Expense extends Model
{
    use BelongsToSchool;

    public const METHODS = ['cash', 'bkash', 'nagad', 'rocket', 'bank', 'card', 'cheque', 'other'];

    public const STATUSES = ['pending', 'paid', 'cancelled', 'rejected'];

    protected $fillable = [
        'expense_category_id',
        'expense_no',
        'title',
        'description',
        'amount',
        'expense_date',
        'payment_method',
        'bank_account_id',
        'reference_no',
        'paid_to',
        'status',
        'approved_by',
        'approved_at',
        'created_by',
        'cancel_reason',
        'cancelled_at',
        'cancelled_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => 'date',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function cashTransactions(): MorphMany
    {
        return $this->morphMany(CashTransaction::class, 'source');
    }
}
