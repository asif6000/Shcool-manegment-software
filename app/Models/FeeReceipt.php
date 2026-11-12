<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class FeeReceipt extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'fee_payment_id',
        'receipt_no',
        'snapshot',
        'snapshot_hash',
        'issued_at',
        'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Issued fee receipts are immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Issued fee receipts cannot be deleted.');
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
