<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeInvoiceDiscount extends Model
{
    use BelongsToSchool;

    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    public const ACTIVE_STATUSES = ['approved', 'applied'];

    public const LEGACY_APPLIED = 'applied';

    protected $fillable = [
        'fee_invoice_id',
        'fee_discount_id',
        'fee_type_id',
        'code',
        'name',
        'type',
        'frequency',
        'scope',
        'value',
        'reason',
        'amount',
        'base_amount',
        'max_amount',
        'starts_on',
        'ends_on',
        'status',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'amount' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function feeDiscount(): BelongsTo
    {
        return $this->belongsTo(FeeDiscount::class, 'fee_discount_id');
    }

    public function feeType(): BelongsTo
    {
        return $this->belongsTo(FeeType::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved', 'applied' => __('Approved'),
            'pending' => __('Pending'),
            'rejected' => __('Rejected'),
            'cancelled' => __('Cancelled'),
            default => __(ucfirst((string) $this->status)),
        };
    }
}
