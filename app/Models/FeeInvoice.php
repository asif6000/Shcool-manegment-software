<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeInvoice extends Model
{
    use BelongsToSchool;

    /**
     * 'paid' means the configured fee was collected in full. 'settled' means an
     * authorised collection closed the invoice with less than that, and the
     * difference is a write-off, never a balance.
     */
    public const STATUSES = ['issued', 'partial', 'paid', 'settled', 'cancelled'];

    public const OPEN_STATUSES = ['issued', 'partial'];

    protected $fillable = [
        'academic_session_id',
        'student_id',
        'enrollment_id',
        'invoice_no',
        'billing_period',
        'issue_date',
        'due_date',
        'subtotal',
        'discount_total',
        'configured_fee_amount',
        'approved_discount_amount',
        'final_payable_amount',
        'total',
        'paid_total',
        'actual_collected_amount',
        'due_total',
        'waived_total',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'configured_fee_amount' => 'decimal:2',
            'approved_discount_amount' => 'decimal:2',
            'final_payable_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'actual_collected_amount' => 'decimal:2',
            'due_total' => 'decimal:2',
            'waived_total' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FeeInvoiceItem::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(FeeInvoiceDiscount::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(FeePaymentAllocation::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * The only place an invoice status is derived from money, so a settlement
     * that received less than the configured fee can never be labelled 'paid'.
     */
    public static function statusForCollection(float $total, float $paid, float $due): string
    {
        if (round($due, 2) > 0) {
            return $paid > 0 ? 'partial' : 'issued';
        }

        return round($paid, 2) >= round($total, 2) ? 'paid' : 'settled';
    }

    /** Closed and needing no further collection — paid in full or settled short. */
    public function isClosed(): bool
    {
        return in_array($this->status, ['paid', 'settled', 'cancelled'], true);
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES)->where('due_total', '>', 0);
    }
}
