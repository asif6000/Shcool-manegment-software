<?php

namespace App\Http\Requests\Finance;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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

        return [
            'expense_category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')->where('school_id', $schoolId),
            ],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_method' => ['required', Rule::in(Expense::METHODS)],
            'bank_account_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn (): bool => $this->input('payment_method') === 'bank'),
                Rule::exists('bank_accounts', 'id')->where('school_id', $schoolId),
            ],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'paid_to' => ['nullable', 'string', 'max:120'],
        ];
    }
}
