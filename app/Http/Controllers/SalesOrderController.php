<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Department;
use App\Models\JobCard;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Services\AuditLogService;
use App\Services\FinanceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Sales Orders (report 2.4).
 *
 * An accepted quotation becomes an order; the order may then be fulfilled by
 * any number of job cards, and staged deliveries hang off those job cards.
 */
class SalesOrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = SalesOrder::query()
            ->visibleTo($request->user())
            ->with(['client', 'department', 'quotation'])
            ->withCount('jobCards')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('order_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('order_date')
            ->paginate(12)
            ->withQueryString();

        return view('sales-orders.index', [
            'orders' => $orders,
            'statuses' => SalesOrder::STATUSES,
        ]);
    }

    public function create(Request $request, FinanceNumberService $numbers): View
    {
        $this->authorizeManage($request);

        // Pre-populate from an accepted quotation when arriving from its page.
        $quotation = null;

        if ($request->filled('sales_quotation_id')) {
            $quotation = SalesQuotation::query()
                ->visibleTo($request->user())
                ->with('items')
                ->find($request->integer('sales_quotation_id'));
        }

        return view('sales-orders.create', [
            'order' => new SalesOrder([
                'sales_quotation_id' => $quotation?->id,
                'client_id' => $quotation?->client_id,
                'department_id' => $quotation?->department_id,
                'title' => $quotation?->title,
                'scope' => $quotation?->items
                    ->map(fn ($item) => trim($item->description.' x '.rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.')))
                    ->implode("\n"),
                'value' => $quotation?->total ?? 0,
                'order_date' => now()->toDateString(),
                'status' => SalesOrder::STATUS_CONFIRMED,
            ]),
            ...$this->formData($request),
        ]);
    }

    public function store(Request $request, FinanceNumberService $numbers, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request);
        $this->guardQuotation($request, $data['sales_quotation_id'] ?? null);

        $data['order_number'] = ($data['order_number'] ?? null) ?: $numbers->salesOrderNumber();
        $data['created_by'] = $request->user()->id;
        $data['accepted_at'] = now();

        $order = SalesOrder::query()->create($data);
        $audit->record('created', $order, "Sales order {$order->order_number} created.");

        return redirect()->route('sales-orders.show', $order)->with('success', 'Sales order created.');
    }

    public function show(Request $request, SalesOrder $salesOrder): View
    {
        $this->authorizeView($request, $salesOrder);

        return view('sales-orders.show', [
            'order' => $salesOrder->load(['client', 'department', 'quotation', 'creator', 'jobCards.invoice']),
        ]);
    }

    public function edit(Request $request, SalesOrder $salesOrder): View
    {
        $this->authorizeManage($request);

        return view('sales-orders.edit', [
            'order' => $salesOrder,
            ...$this->formData($request),
        ]);
    }

    public function update(Request $request, SalesOrder $salesOrder, AuditLogService $audit): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request, $salesOrder);
        $this->guardQuotation($request, $data['sales_quotation_id'] ?? null, $salesOrder);

        $before = $salesOrder->only(['status', 'value']);
        $salesOrder->update($data);
        $audit->record('updated', $salesOrder, "Sales order {$salesOrder->order_number} updated.", $before, $salesOrder->only(['status', 'value']));

        return redirect()->route('sales-orders.show', $salesOrder)->with('success', 'Sales order updated.');
    }

    private function validated(Request $request, ?SalesOrder $order = null): array
    {
        return $request->validate([
            'sales_quotation_id' => ['nullable', 'exists:sales_quotations,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'order_number' => ['nullable', 'string', 'max:40', Rule::unique('sales_orders', 'order_number')->ignore($order)],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(SalesOrder::STATUSES)],
            'client_po_reference' => ['nullable', 'string', 'max:80'],
            'scope' => ['nullable', 'string'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'order_date' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'delivery_requirements' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * A quotation may only be turned into an order once.
     */
    private function guardQuotation(Request $request, ?int $quotationId, ?SalesOrder $current = null): void
    {
        if ($quotationId === null) {
            return;
        }

        $exists = SalesOrder::query()
            ->where('sales_quotation_id', $quotationId)
            ->when($current !== null, fn ($query) => $query->whereKeyNot($current->id))
            ->exists();

        if ($exists) {
            abort(422, 'That quotation already has a sales order.');
        }
    }

    private function formData(Request $request): array
    {
        return [
            'statuses' => SalesOrder::STATUSES,
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'quotations' => SalesQuotation::query()
                ->visibleTo($request->user())
                ->whereIn('status', [SalesQuotation::STATUS_ACCEPTED, SalesQuotation::STATUS_CONVERTED, SalesQuotation::STATUS_SENT])
                ->latest('issue_date')
                ->limit(100)
                ->get(),
        ];
    }

    private function authorizeView(Request $request, SalesOrder $order): void
    {
        $user = $request->user();

        if ($user->canViewPortfolio() || $order->department_id === $user->department_id || $order->created_by === $user->id) {
            return;
        }

        abort(403);
    }

    private function authorizeManage(Request $request): void
    {
        if (! $request->user()->canManage() && ! $request->user()->canDraftFinance()) {
            abort(403);
        }
    }
}
