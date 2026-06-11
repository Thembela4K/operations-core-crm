<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Expense;
use App\Models\Supplier;
use App\Services\FinanceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $expenses = Expense::query()
            ->visibleTo($request->user())
            ->with(['department', 'recorder', 'supplier'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('expense_number', 'like', "%{$search}%")
                        ->orWhere('payee', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->latest('expense_date')
            ->paginate(12)
            ->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function create(Request $request, FinanceNumberService $numbers): View
    {
        $this->authorizeManage($request);

        return view('expenses.create', [
            'expense' => new Expense([
                'expense_number' => $numbers->expenseNumber(),
                'expense_date' => now()->toDateString(),
                'status' => 'Recorded',
            ]),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function store(Request $request, FinanceNumberService $numbers): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request);
        $data['expense_number'] = $data['expense_number'] ?: $numbers->expenseNumber();
        $data['recorded_by'] = $request->user()->id;
        $data['total_amount'] = round((float) $data['amount'] + (float) $data['vat_amount'], 2);

        Expense::query()->create($data);

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function edit(Request $request, Expense $expense): View
    {
        $this->authorizeManage($request);

        return view('expenses.edit', [
            'expense' => $expense->load('documents.uploader'),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => Expense::CATEGORIES,
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request, $expense);
        $data['total_amount'] = round((float) $data['amount'] + (float) $data['vat_amount'], 2);
        $expense->update($data);

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizeManage($request);
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Expense deleted.');
    }

    private function validated(Request $request, ?Expense $expense = null): array
    {
        return $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'expense_number' => ['nullable', 'string', 'max:40', Rule::unique('expenses', 'expense_number')->ignore($expense)],
            'category' => ['required', Rule::in(Expense::CATEGORIES)],
            'payee' => ['required', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'vat_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function authorizeManage(Request $request): void
    {
        if (! $request->user()->canManageFinance()) {
            abort(403);
        }
    }
}
