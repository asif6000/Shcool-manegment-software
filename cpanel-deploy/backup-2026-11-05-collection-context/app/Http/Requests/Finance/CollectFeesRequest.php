<?php

namespace App\Http\Requests\Finance;

use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Services\FeeCollectionAuthority;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A counter collection: one student, a basket of their own fee records and the
 * money actually handed over against each of them.
 */
class CollectFeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) app(FeeCollectionAuthority::class)->mayCollect($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $schoolId = (int) app(SchoolContext::class)->id();
        $studentId = (int) $this->input('student_id');
        $referenceRequired = (array) config('finance.reference_required_methods', []);

        return [
            'student_id' => [
                'required', 'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'academic_session_id' => [
                'nullable', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            // A forged invoice id must never be able to bill somebody else's
            // child, so every picked row is pinned to this student's school.
            'picked' => ['required', 'array', 'min:1', 'max:60'],
            'picked.*' => [
                'integer',
                Rule::exists('fee_invoices', 'id')
                    ->where('school_id', $schoolId)
                    ->where('student_id', $studentId)
                    ->where('status', '!=', 'cancelled'),
            ],
            'amounts' => ['required', 'array'],
            'amounts.*' => ['numeric', 'min:0.01', 'max:99999999'],
            'method' => ['required', Rule::in(FeePayment::METHODS)],
            'transaction_ref' => [
                in_array((string) $this->input('method'), $referenceRequired, true) ? 'required' : 'nullable',
                'string', 'max:100',
            ],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
            'settle_in_full' => ['nullable', 'boolean'],
            'bank_account_id' => [
                'nullable',
                'integer',
                Rule::exists('bank_accounts', 'id')->where('school_id', $schoolId),
            ],
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'picked.*.exists' => __('One of the selected fees does not belong to this student.'),
            'transaction_ref.required' => __('This payment method needs the reference the provider issued.'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $amounts = (array) $this->input('amounts');

            // A browser retry replays the same key, and the service answers it
            // with the receipt it already issued. Rejecting the now-closed
            // invoices here would turn that safe retry into a false failure.
            $replay = FeePayment::query()
                ->where('school_id', app(SchoolContext::class)->id())
                ->where('idempotency_key', (string) $this->input('idempotency_key'))
                ->exists();

            $missing = [];
            $closed = [];

            foreach ((array) $this->input('picked') as $invoiceId) {
                $id = (int) $invoiceId;

                if (! isset($amounts[$id]) || ! is_numeric($amounts[$id])) {
                    $missing[] = $id;

                    continue;
                }

                if ($replay) {
                    continue;
                }

                $due = (float) FeeInvoice::query()->whereKey($id)->value('due_total');

                if ($due <= 0) {
                    $closed[] = $id;
                }
            }

            if ($missing !== []) {
                $validator->errors()->add('amounts', __('Enter the amount received for every fee you selected.'));
            }

            if ($closed !== []) {
                $validator->errors()->add('amounts', __('One of the selected fees is already closed.'));
            }
        });
    }

    /**
     * The basket the service bills: invoice id => amount received.
     *
     * @return array<int, float>
     */
    public function lines(): array
    {
        $amounts = (array) $this->input('amounts');
        $lines = [];

        foreach ((array) $this->input('picked') as $invoiceId) {
            $id = (int) $invoiceId;
            $lines[$id] = round((float) ($amounts[$id] ?? 0), 2);
        }

        return $lines;
    }
}
