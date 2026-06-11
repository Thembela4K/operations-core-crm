<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogItemController extends Controller
{
    public function index(Request $request): View
    {
        $items = CatalogItem::query()
            ->visibleTo($request->user())
            ->with('department')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('catalog_items.index', [
            'items' => $items,
            'types' => CatalogItem::TYPES,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeItemMutation($request);

        return view('catalog_items.create', [
            'item' => new CatalogItem([
                'type' => CatalogItem::TYPE_SERVICE,
                'department_id' => $request->user()->department_id,
                'taxable' => true,
                'is_active' => true,
            ]),
            'departments' => $this->departmentsFor($request),
            'types' => CatalogItem::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeItemMutation($request);

        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['department_id'] = $request->user()->canManageFinance() ? $data['department_id'] : $request->user()->department_id;

        CatalogItem::query()->create($data);

        return redirect()->route('catalog-items.index')->with('success', 'Catalog item created.');
    }

    public function edit(Request $request, CatalogItem $catalogItem): View
    {
        $this->authorizeItemMutation($request, $catalogItem);

        return view('catalog_items.edit', [
            'item' => $catalogItem,
            'departments' => $this->departmentsFor($request),
            'types' => CatalogItem::TYPES,
        ]);
    }

    public function update(Request $request, CatalogItem $catalogItem): RedirectResponse
    {
        $this->authorizeItemMutation($request, $catalogItem);

        $data = $this->validated($request);
        $data['department_id'] = $request->user()->canManageFinance() ? $data['department_id'] : $request->user()->department_id;
        $catalogItem->update($data);

        return redirect()->route('catalog-items.index')->with('success', 'Catalog item updated.');
    }

    public function destroy(Request $request, CatalogItem $catalogItem): RedirectResponse
    {
        $this->authorizeItemMutation($request, $catalogItem);
        $catalogItem->delete();

        return redirect()->route('catalog-items.index')->with('success', 'Catalog item deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'department_id' => ['nullable', 'exists:departments,id'],
            'type' => ['required', Rule::in(array_keys(CatalogItem::TYPES))],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'taxable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]) + [
            'taxable' => $request->boolean('taxable'),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function departmentsFor(Request $request)
    {
        if (! $request->user()->canManageFinance()) {
            return Department::query()->whereKey($request->user()->department_id)->get();
        }

        return Department::query()->where('is_active', true)->orderBy('name')->get();
    }

    private function authorizeItemMutation(Request $request, ?CatalogItem $item = null): void
    {
        if ($request->user()->canManageFinance()) {
            return;
        }

        if ($request->user()->hasRole(User::ROLE_DEPARTMENT_USER) && (! $item || $item->department_id === $request->user()->department_id)) {
            return;
        }

        abort(403);
    }
}
