<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FeePayment extends Model
{
    use BelongsToSchool;

    public const METHODS = ['cash', 'bkash', 'nagad', 'rocket', 'bank', 'card', 'cheque', 'other'];

    public const VERIFICATION_STATUSES = ['not_required', 'pending', 'verified', 'rejected'];

    protected $fillable = [
        'student_id',
        'academic_session_id',
        'class_id',
        'fee_type_id',
        'billing_month',
        'invoice_no',
        'fee_invoice_id',
        'scheduled_amount',
        'amount',
        'method',
        'transaction_ref',
        'paid_at',
        'received_by',
        'status',
        'verification_status',
        'verified_by',
        'verified_at',
        'rejection_reason',
        'bank_account_id',
        'idempotency_key',
        'notes',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'scheduled_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'reversed_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(FeeReceipt::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FeePaymentAllocation::class);
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    public function scopePendingVerification(Builder $query): Builder
    {
        return $query->where('status', 'pending')
            ->where('verification_status', 'pending');
    }
}
