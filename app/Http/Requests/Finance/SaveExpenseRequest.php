<?php

namespace App\Http\Requests\Finance;

use App\Models\Expense;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseRequest extends FormRequest
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

        return [
            'expense_category_id' => [
                'required', 'integer',
                Rule::exists('expense_categories', 'id')->where('school_id', $schoolId)->where('is_active', true),
            ],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::in(Expense::METHODS)],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'paid_to' => ['nullable', 'string', 'max:120'],
        ];
    }
}
