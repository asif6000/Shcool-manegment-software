<?php

namespace App\Http\Requests\Finance;

use App\Models\FeePayment;
use App\Services\FeeCollectionAuthority;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) app(FeeCollectionAuthority::class)->mayCollect($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = (int) app(SchoolContext::class)->id();

        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'method' => ['required', Rule::in(FeePayment::METHODS)],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
            'hold_for_verification' => ['nullable', 'boolean'],
            'settle_in_full' => ['nullable', 'boolean'],
            'bank_account_id' => [
                'nullable',
                'integer',
                Rule::exists('bank_accounts', 'id')->where('school_id', $schoolId),
            ],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
        ];
    }
}
