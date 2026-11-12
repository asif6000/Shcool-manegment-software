<?php

namespace App\Http\Requests\Finance;

use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyFeeDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('fees.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();

        return [
            'fee_discount_id' => [
                'required', 'integer',
                Rule::exists('fee_discounts', 'id')->where('school_id', $schoolId)->where('is_active', true),
            ],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
