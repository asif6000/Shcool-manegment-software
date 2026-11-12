<?php

namespace App\Http\Requests\Finance;

use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Services\FeeCollectionAuthority;
use App\Support\SchoolContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A counter collection: the invoice the money is being handed over against and
 * how much was actually received. The student is never taken from the form — it
 * is read from the invoice, so a forged pair cannot bill somebody else's child.
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
            'academic_session_id' => [
                'nullable', 'integer',
                Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId),
            ],
            'picked' => ['required', 'array', 'min:1', 'max:60'],
            'picked.*' => [
                'integer',
                Rule::exists('fee_invoices', 'id')
                    ->where('school_id', $schoolId)
                    ->when($studentId > 0, fn ($rule) => $rule->where('student_id', $studentId))
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
            'idempotency_key' => ['required', 'string', 'min:8', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'picked.required' => __('Choose the invoice this money is being received against.'),
            'picked.*.exists' => __('That invoice does not belong to this school, or has already been closed.'),
            'amounts.*.numeric' => __('Enter the amount received as a number greater than zero.'),
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

                $invoice = FeeInvoice::query()->find($id);

                if ($invoice === null || ! in_array($invoice->status, FeeInvoice::OPEN_STATUSES, true)
                    || round((float) $invoice->due_total, 2) <= 0) {
                    $closed[] = $id;
                }
            }

            if ($missing !== []) {
                $validator->errors()->add('amounts', __('Enter the amount received for the invoice you selected.'));
            }

            if ($closed !== []) {
                $validator->errors()->add('amounts', __('That invoice is already paid or closed, so it cannot receive money.'));
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
