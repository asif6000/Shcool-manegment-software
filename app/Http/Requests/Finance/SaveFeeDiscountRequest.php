<?php

namespace App\Http\Requests\Finance;

use App\Models\FeeDiscount;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveFeeDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('fees.discounts.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = app(SchoolContext::class)->id();
        $editing = $this->route('feeDiscount');
        $ignoreId = $editing instanceof FeeDiscount ? $editing->id : null;

        return [
            'name' => ['required', 'string', 'max:80'],
            'code' => [
                'required', 'string', 'alpha_dash', 'max:30',
                Rule::unique('fee_discounts', 'code')->where('school_id', $schoolId)->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(FeeDiscount::TYPES)],
            'value' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'frequency' => ['nullable', Rule::in(FeeDiscount::FREQUENCIES)],
            'scope' => ['nullable', Rule::in(FeeDiscount::SCOPES)],
            'class_id' => [
                'nullable', 'integer',
                Rule::exists('classes', 'id')->where('school_id', $schoolId),
            ],
            'student_id' => [
                'nullable', 'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'applies_to_fee_type_id' => [
                'nullable', 'integer',
                Rule::exists('fee_types', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'auto_apply' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('type') === 'percent' && (float) $this->input('value') > 100) {
                $validator->errors()->add('value', __('A percentage discount cannot exceed 100%.'));
            }

            $scope = (string) $this->input('scope', 'all');

            if ($scope === 'class' && ! $this->filled('class_id')) {
                $validator->errors()->add('class_id', __('Choose the class this discount applies to.'));
            }

            if ($scope === 'student' && ! $this->filled('student_id')) {
                $validator->errors()->add('student_id', __('Choose the student this discount applies to.'));
            }
        });
    }
}
