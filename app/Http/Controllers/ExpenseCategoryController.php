<?php

namespace App\Http\Controllers;

use App\Http\Requests\Finance\StoreExpenseCategoryRequest;
use App\Http\Requests\Finance\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $this->requirePermission('expenses.view');

        return view('expenses.categories', [
            'categories' => ExpenseCategory::query()
                ->withCount('expenses')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        ExpenseCategory::query()->create([
            'name' => $request->validated()['name'],
            'code' => strtoupper((string) $request->validated()['code']),
            'description' => $request->validated()['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', __('The expense category was created.'));
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $category->update([
            'name' => $request->validated()['name'],
            'code' => strtoupper((string) $request->validated()['code']),
            'description' => $request->validated()['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', __('The expense category was updated.'));
    }
}
