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

        return [
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'student_id' => [
                'required', 'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'fee_type_id' => [
                'required', 'integer',
                Rule::exists('fee_types', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'method' => ['required', Rule::in(FeePayment::METHODS)],
            'transaction_ref' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function collectionData(): array
    {
        $data = $this->validated();

        if (isset($data['month'])) {
            $data['month'] = (int) $data['month'];
        }

        return $data;
    }
}
