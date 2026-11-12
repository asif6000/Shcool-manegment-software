<?php

namespace App\Http\Requests\Finance;

use App\Models\FeeType;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A fee type is created and renamed by its name alone. The code is generated
 * and every financial figure lives on the class fee, so nothing else belongs
 * on this form.
 */
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
        ];
    }
}
