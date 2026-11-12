<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per payment this school asked a gateway to take. The row is created
 * before the provider is contacted and is only allowed to reach a terminal
 * status through a verified callback, never through a browser redirect.
 */
class PaymentGatewayTransaction extends Model
{
    use BelongsToSchool;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_SUCCESS, self::STATUS_FAILED, self::STATUS_CANCELLED];

    protected $table = 'payment_gateway_transactions';

    protected $fillable = [
        'fee_invoice_id',
        'fee_payment_id',
        'payment_key',
        'student_id',
        'gateway',
        'gateway_transaction_id',
        'amount',
        'currency',
        'status',
        'verified_at',
        'failure_reason',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function feeInvoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class);
    }

    public function feePayment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isSettled(): bool
    {
        return $this->status !== self::STATUS_PENDING;
    }
}
