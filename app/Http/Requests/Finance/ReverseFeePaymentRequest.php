<?php

namespace App\Http\Requests\Finance;

use App\Services\FeeCollectionAuthority;
use Illuminate\Foundation\Http\FormRequest;

class ReverseFeePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();

        return $actor !== null && app(FeeCollectionAuthority::class)->mayReverse($actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
