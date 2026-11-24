<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Department;
use App\Models\PurchaseRecord;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Services\AccountingService;
use App\Services\AuditLogService;
use App\Services\FinanceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseRecordController extends Controller
{
    public function index(Request $request): View
    {
        $purchases = PurchaseRecord::query()
            ->visibleTo($request->user())
            ->with(['supplier', 'requisition', 'department', 'creator'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('purchase_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('purchase_date')
            ->paginate(12)
            ->withQueryString();

        return view('purchases.index', [
            'purchases' => $purchases,
            'statuses' => PurchaseRecord::STATUSES,
        ]);
    }

    public function create(Request $request, FinanceNumberService $numbers): View
    {
        $this->authorizeManage($request);

        return view('purchases.create', [
            'purchase' => new PurchaseRecord([
                'purchase_number' => $numbers->purchaseNumber(),
                'status' => PurchaseRecord::STATUS_PLANNED,
                'purchase_date' => now()->toDateString(),
            ]),
            ...$this->formData($request),
        ]);
    }

    public function store(Request $request, FinanceNumberService $numbers, AuditLogService $audit, AccountingService $accounting): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request);
        $items = $this->validatedItems($request);
        $data['purchase_number'] = ($data['purchase_number'] ?? null) ?: $numbers->purchaseNumber();
        $data['created_by'] = $request->user()->id;
        $data['amount'] = $items === [] ? ($data['amount'] ?? 0) : 0;

        $purchase = PurchaseRecord::query()->create($data);

        if ($items !== []) {
            $purchase->syncItems($items);
        }

        $audit->record('created', $purchase, "Purchase {$purchase->purchase_number} created.");

        // Purchase records are treated as expenses (decision A1).
        $accounting->postPurchase($purchase->fresh(), $request->user());

        return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase record created.');
    }

    public function show(Request $request, PurchaseRecord $purchase): View
    {
        $this->authorizeView($request, $purchase);

        return view('purchases.show', [
            'purchase' => $purchase->load(['supplier', 'requisition', 'department', 'creator', 'items.catalogItem']),
        ]);
    }

    public function print(Request $request, PurchaseRecord $purchase): View
    {
        $this->authorizeView($request, $purchase);

        return view('purchases.print', [
            'purchase' => $purchase->load(['supplier', 'department', 'items.catalogItem']),
        ]);
    }

    public function edit(Request $request, PurchaseRecord $purchase): View
    {
        $this->authorizeManage($request);

        return view('purchases.edit', [
            'purchase' => $purchase->load('items'),
            ...$this->formData($request),
        ]);
    }

    public function update(Request $request, PurchaseRecord $purchase, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        $items = $this->validatedItems($request);
        $before = $purchase->only(['status', 'amount', 'supplier_id']);
        $purchase->update($this->validated($request, $purchase));

        if ($items !== []) {
            $purchase->syncItems($items);
        }

        $purchase->refresh();
        $audit->record('updated', $purchase, "Purchase {$purchase->purchase_number} updated.", $before, $purchase->only(['status', 'amount', 'supplier_id']));

        return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase record updated.');
    }

    public function destroy(Request $request, PurchaseRecord $purchase, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeManage($request);
        $audit->record('deleted', $purchase, "Purchase {$purchase->purchase_number} deleted.");
        $purchase->delete();

        return redirect()->route('purchases.index')->with('success', 'Purchase record deleted.');
    }

    private function validated(Request $request, ?PurchaseRecord $purchase = null): array
    {
        return $request->validate([
            'purchase_number' => ['nullable', 'string', 'max:40', Rule::unique('purchase_records', 'purchase_number')->ignore($purchase)],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'requisition_id' => ['nullable', 'exists:requisitions,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(PurchaseRecord::STATUSES)],
            'purchase_date' => ['nullable', 'date'],
            'ship_to' => ['nullable', 'string'],
            'terms' => ['nullable', 'string', 'max:60'],
            'expected_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * QuickBooks-style PO lines: qty x rate = amount.
     */
    private function validatedItems(Request $request): array
    {
        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.catalog_item_id' => ['nullable', 'exists:catalog_items,id'],
            'items.*.description' => ['required_with:items', 'nullable', 'string'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        return collect($data['items'] ?? [])
            ->filter(fn (array $item): bool => trim((string) ($item['description'] ?? '')) !== '')
            ->values()
            ->all();
    }

    private function formData(Request $request): array
    {
        return [
            'statuses' => PurchaseRecord::STATUSES,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'requisitions' => Requisition::query()->visibleTo($request->user())->orderByDesc('created_at')->limit(100)->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'catalogItems' => CatalogItem::query()->where('is_active', true)->with('department')->orderBy('name')->get(),
            'paymentTerms' => PurchaseRecord::PAYMENT_TERMS,
        ];
    }

    private function authorizeView(Request $request, PurchaseRecord $purchase): void
    {
        if ($request->user()->canViewReports() || $request->user()->canManageFinance() || $purchase->department_id === $request->user()->department_id) {
            return;
        }

        abort(403);
    }

    private function authorizeManage(Request $request): void
    {
        if (! $request->user()->canManageFinance()) {
            abort(403);
        }
    }
}
