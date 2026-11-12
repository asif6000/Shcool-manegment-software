<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ExpenseDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->can('expenses.approve') || $this->user()?->can('expenses.manage'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
