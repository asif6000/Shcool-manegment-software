<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Support\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BankAccountController extends Controller
{
    public function index(): View
    {
        $this->requirePermission('bank.view');

        $accounts = BankAccount::query()
            ->withSum('transactions as ledger_total', 'amount')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('bank.index', [
            'accounts' => $accounts,
            'summary' => [
                'opening' => round((float) $accounts->sum('opening_balance'), 2),
                'ledger' => round((float) $accounts->sum(fn (BankAccount $account): float => (float) ($account->ledger_total ?? 0)), 2),
                'balance' => round((float) $accounts->sum(fn (BankAccount $account): float => (float) $account->opening_balance + (float) ($account->ledger_total ?? 0)), 2),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requirePermission('bank.manage');

        $schoolId = (int) app(SchoolContext::class)->id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'bank_name' => ['required', 'string', 'max:80'],
            'account_no' => [
                'required', 'string', 'max:40',
                Rule::unique('bank_accounts', 'account_no')->where('school_id', $schoolId),
            ],
            'branch' => ['nullable', 'string', 'max:80'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        BankAccount::query()->create([
            'name' => $data['name'],
            'bank_name' => $data['bank_name'],
            'account_no' => $data['account_no'],
            'branch' => $data['branch'] ?? null,
            'opening_balance' => round((float) ($data['opening_balance'] ?? 0), 2),
            'is_active' => true,
        ]);

        return back()->with('success', __('The bank account was added.'));
    }

    public function update(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->requirePermission('bank.manage');

        $schoolId = (int) app(SchoolContext::class)->id();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'bank_name' => ['required', 'string', 'max:80'],
            'account_no' => [
                'required', 'string', 'max:40',
                Rule::unique('bank_accounts', 'account_no')
                    ->where('school_id', $schoolId)
                    ->ignore($bankAccount->id),
            ],
            'branch' => ['nullable', 'string', 'max:80'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $bankAccount->update([
            'name' => $data['name'],
            'bank_name' => $data['bank_name'],
            'account_no' => $data['account_no'],
            'branch' => $data['branch'] ?? null,
            'opening_balance' => round((float) ($data['opening_balance'] ?? 0), 2),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', __('The bank account was updated.'));
    }
}
