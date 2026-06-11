<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\FinanceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->visibleTo($request->user())
            ->withCount(['expenses', 'requisitions', 'purchases'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('supplier_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create(FinanceNumberService $numbers): View
    {
        $this->authorizeManage();

        return view('suppliers.create', [
            'supplier' => new Supplier(['supplier_code' => $numbers->supplierCode(), 'is_active' => true]),
        ]);
    }

    public function store(Request $request, FinanceNumberService $numbers, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeManage();

        $data = $this->validated($request);
        $data['supplier_code'] = $data['supplier_code'] ?: $numbers->supplierCode();
        $data['created_by'] = $request->user()->id;
        $supplier = Supplier::query()->create($data);
        $audit->record('created', $supplier, "Supplier {$supplier->supplier_code} created.");

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier created.');
    }

    public function show(Supplier $supplier): View
    {
        return view('suppliers.show', [
            'supplier' => $supplier->load(['expenses.department', 'requisitions.department', 'purchases.department']),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorizeManage();

        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeManage();

        $before = $supplier->only(['name', 'email', 'phone', 'is_active']);
        $supplier->update($this->validated($request, $supplier));
        $audit->record('updated', $supplier, "Supplier {$supplier->supplier_code} updated.", $before, $supplier->only(['name', 'email', 'phone', 'is_active']));

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeManage();
        $audit->record('deleted', $supplier, "Supplier {$supplier->supplier_code} deleted.");
        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted.');
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'supplier_code' => ['nullable', 'string', 'max:40', Rule::unique('suppliers', 'supplier_code')->ignore($supplier)],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'vat_number' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function authorizeManage(): void
    {
        if (! request()->user()->canManageFinance()) {
            abort(403);
        }
    }
}
