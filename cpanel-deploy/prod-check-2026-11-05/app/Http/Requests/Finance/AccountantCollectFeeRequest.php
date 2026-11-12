<?php

namespace App\Http\Requests\Finance;

use App\Models\FeePayment;
use App\Services\FeeCollectionAuthority;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountantCollectFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $authority = app(FeeCollectionAuthority::class);

        return $user->hasRole('accountant')
            && ! $user->hasAnyRole(['school_admin', 'principal'])
            && $authority->mayCollect($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = (int) app(SchoolContext::class)->id();
        $referenceRequired = (array) config('finance.reference_required_methods', []);

        return [
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'class_id' => [
                'required', 'integer',
                Rule::exists('classes', 'id')->where('school_id', $schoolId),
            ],
            'student_id' => [
                'required', 'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'fee_type_id' => [
                'required', 'integer',
                Rule::exists('fee_types', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'method' => ['required', Rule::in(FeePayment::METHODS)],
            'transaction_ref' => [
                in_array((string) $this->input('method'), $referenceRequired, true) ? 'required' : 'nullable',
                'string', 'max:100',
            ],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'bank_account_id' => [
                'nullable', 'integer',
                Rule::exists('bank_accounts', 'id')->where('school_id', $schoolId),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transaction_ref.required' => __('This payment method needs the reference the provider issued.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collectionData(): array
    {
        return [
            ...$this->validated(),
            'year' => (int) $this->input('year'),
            'month' => (int) $this->input('month'),
        ];
    }
}
