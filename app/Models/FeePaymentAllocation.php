<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeePaymentAllocation extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'fee_payment_id',
        'fee_invoice_id',
        'amount',
        'waived_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'waived_amount' => 'decimal:2',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(FeePayment::class, 'fee_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }
}
