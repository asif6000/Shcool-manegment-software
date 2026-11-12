<?php

namespace App\Http\Requests\Finance;

use App\Models\FeeType;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFeeTypeRequest extends FormRequest
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
        $editing = $this->route('feeType');
        $ignoreId = $editing instanceof FeeType ? $editing->id : null;

        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('fee_types', 'name')->where('school_id', $schoolId)->whereNull('deleted_at')->ignore($ignoreId),
            ],
            // The create form generates the code; the edit modal still owns it.
            'code' => [
                $editing !== null ? 'required' : 'nullable', 'string', 'alpha_dash', 'max:30',
                Rule::unique('fee_types', 'code')->where('school_id', $schoolId)->whereNull('deleted_at')->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', Rule::in(FeeType::CATEGORIES)],
            'frequency' => ['required', Rule::in(FeeType::FREQUENCIES)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_on.after' => __('The end date must be after the start date.'),
        ];
    }
}
