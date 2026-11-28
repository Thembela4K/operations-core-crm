<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\Supplier;
use App\Services\AccountingService;
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
            ->with(['department', 'recorder', 'supplier', 'requisition'])
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
            'itemTypes' => ExpenseItem::TYPES,
        ]);
    }

    public function store(Request $request, FinanceNumberService $numbers, AccountingService $accounting): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request);
        $items = $data['items'] ?? [];
        unset($data['items']);

        $data['expense_number'] = ($data['expense_number'] ?? null) ?: $numbers->expenseNumber();
        $data['recorded_by'] = $request->user()->id;

        // When the worksheet has lines, the lines decide the amount.
        if ($items !== []) {
            $data['amount'] = $this->itemsTotal($items);
        }

        $data['total_amount'] = round((float) $data['amount'] + (float) $data['vat_amount'], 2);

        $expense = Expense::query()->create($data);
        $this->syncItems($expense, $items);

        // Purchase-type spend posts straight to the ledger (decision A1).
        $accounting->postExpense($expense, $request->user());

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function edit(Request $request, Expense $expense): View
    {
        $this->authorizeManage($request);

        return view('expenses.edit', [
            'expense' => $expense->load(['documents.uploader', 'items', 'requisition']),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => Expense::CATEGORIES,
            'itemTypes' => ExpenseItem::TYPES,
        ]);
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request, $expense);
        $items = $data['items'] ?? [];
        unset($data['items']);

        if ($items !== []) {
            $data['amount'] = $this->itemsTotal($items);
        }

        $data['total_amount'] = round((float) $data['amount'] + (float) $data['vat_amount'], 2);
        $expense->update($data);
        $this->syncItems($expense, $items);

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
            'expense_type' => ['nullable', Rule::in(Expense::TYPES)],
            'payee' => ['required', 'string', 'max:255'],
            'expense_date' => ['required', 'date'],
            'duration_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0'],
            'vat_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.item_type' => ['nullable', Rule::in(ExpenseItem::TYPES)],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.duration_months' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function itemsTotal(array $items): float
    {
        $total = 0.0;

        foreach ($items as $row) {
            if (trim((string) ($row['description'] ?? '')) === '') {
                continue;
            }

            $total += ExpenseItem::computeLineTotal(
                (float) ($row['quantity'] ?? 0),
                (float) ($row['unit_cost'] ?? 0),
                (int) ($row['duration_months'] ?? 1),
            );
        }

        return round($total, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Expense $expense, array $items): void
    {
        $expense->items()->delete();

        $position = 1;

        foreach ($items as $row) {
            $description = trim((string) ($row['description'] ?? ''));

            if ($description === '') {
                continue;
            }

            $duration = max(1, (int) ($row['duration_months'] ?? 1));
            $quantity = (float) ($row['quantity'] ?? 1);
            $unitCost = (float) ($row['unit_cost'] ?? 0);

            $expense->items()->create([
                'description' => $description,
                'item_type' => $row['item_type'] ?? 'Material',
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'duration_months' => $duration,
                'line_total' => ExpenseItem::computeLineTotal($quantity, $unitCost, $duration),
                'position' => $position++,
            ]);
        }
    }

    private function authorizeManage(Request $request): void
    {
        if (! $request->user()->canManageFinance()) {
            abort(403);
        }
    }
}
