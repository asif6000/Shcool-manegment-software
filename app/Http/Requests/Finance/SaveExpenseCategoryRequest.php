<?php

namespace App\Http\Requests\Finance;

use App\Models\ExpenseCategory;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseCategoryRequest extends FormRequest
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
        $schoolId = app(SchoolContext::class)->id();
        $editing = $this->route('expenseCategory');
        $ignoreId = $editing instanceof ExpenseCategory ? $editing->id : null;

        return [
            'name' => ['required', 'string', 'max:80'],
            'code' => [
                'required', 'string', 'alpha_dash', 'max:30',
                Rule::unique('expense_categories', 'code')->where('school_id', $schoolId)->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
