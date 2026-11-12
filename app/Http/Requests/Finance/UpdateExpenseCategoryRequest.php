<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('expenses.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = (int) $this->user()->school_id;
        $categoryId = (int) $this->route('category')->getKey();

        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('expense_categories', 'name')->where('school_id', $schoolId)->ignore($categoryId),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                'alpha_dash',
                Rule::unique('expense_categories', 'code')->where('school_id', $schoolId)->ignore($categoryId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
