<?php

namespace App\Http\Requests\Finance;

use App\Models\FeeStructure;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFeeStructureRequest extends FormRequest
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
        $editing = $this->route('feeStructure');
        $ignoreId = $editing instanceof FeeStructure ? $editing->id : null;

        return [
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'class_id' => [
                'required', 'integer',
                Rule::exists('classes', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'fee_type_id' => [
                'required', 'integer',
                Rule::exists('fee_types', 'id')->where('school_id', $schoolId)->whereNull('deleted_at'),
            ],
            'name' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'due_day' => ['nullable', 'integer', 'between:1,31'],
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $schoolId = app(SchoolContext::class)->id();
            $editing = $this->route('feeStructure');

            $exists = FeeStructure::withoutSchoolScope()
                ->where('school_id', $schoolId)
                ->where('academic_session_id', $this->input('academic_session_id'))
                ->where('class_id', $this->input('class_id'))
                ->where('fee_type_id', $this->input('fee_type_id'))
                ->when($editing instanceof FeeStructure, fn ($query) => $query->whereKeyNot($editing->id))
                ->exists();

            if ($exists) {
                $validator->errors()->add('fee_type_id', __('A structure already exists for this session, class and fee type.'));
            }
        });
    }
}
