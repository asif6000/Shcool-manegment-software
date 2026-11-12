<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Approval limits
    |--------------------------------------------------------------------------
    |
    | The accountant may approve expenses below this limit and request
    | discounts below the threshold. Anything larger needs a holder of the
    | matching high-value permission (school admin / principal).
    |
    */

    'expense_approval_limit' => (float) env('FINANCE_EXPENSE_APPROVAL_LIMIT', 10000),
    'discount_approval_threshold' => (float) env('FINANCE_DISCOUNT_APPROVAL_THRESHOLD', 2000),

    'channels' => [
        'cash' => 'Cash',
        'bank' => 'Bank',
        'mobile' => 'Mobile wallet',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment method to ledger channel
    |--------------------------------------------------------------------------
    */

    'method_channels' => [
        'cash' => 'cash',
        'bank' => 'bank',
        'card' => 'bank',
        'cheque' => 'bank',
        'bkash' => 'mobile',
        'nagad' => 'mobile',
        'rocket' => 'mobile',
        'other' => 'cash',
    ],

    /*
    |--------------------------------------------------------------------------
    | Methods that need a provider reference
    |--------------------------------------------------------------------------
    |
    | Money that did not come over the counter cannot be booked without the
    | transaction id the provider issued, so the collector must type it.
    |
    */

    'reference_required_methods' => ['bank', 'card', 'cheque', 'bkash', 'nagad', 'rocket'],

];

