<?php

namespace App\Http\Controllers;

use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\FinanceCalculatorService;
use App\Services\FinanceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $invoices = Invoice::query()
            ->visibleTo($request->user())
            ->with(['client', 'department', 'salesQuotation'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($client) => $client->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('payment_state')->toString() === 'unpaid', fn ($query) => $query->where('balance_due', '>', 0)->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED]))
            ->latest('issue_date')
            ->paginate(12)
            ->withQueryString();

        return view('invoices.index', [
            'invoices' => $invoices,
            'statuses' => Invoice::STATUSES,
        ]);
    }

    public function create(Request $request, FinanceNumberService $numbers): View
    {
        $this->authorizeManage($request);

        return view('invoices.create', [
            'invoice' => new Invoice([
                'invoice_number' => $numbers->invoiceNumber(),
                'status' => Invoice::STATUS_DRAFT,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'terms' => 'Payment due within 30 days from invoice date.',
            ]),
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'catalogItems' => CatalogItem::query()->where('is_active', true)->orderBy('name')->get(),
            'vatRate' => app(FinanceCalculatorService::class)->vatRatePercent(),
        ]);
    }

    public function store(Request $request, FinanceNumberService $numbers, FinanceCalculatorService $calculator): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request);
        $data['invoice_number'] = $data['invoice_number'] ?: $numbers->invoiceNumber();
        $data['created_by'] = $request->user()->id;
        $items = $this->validatedItems($request);

        $invoice = DB::transaction(function () use ($data, $items, $calculator): Invoice {
            $invoice = Invoice::query()->create($data);
            $calculator->syncInvoiceItems($invoice, $items);

            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice drafted.');
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $this->authorizeView($request, $invoice);

        return view('invoices.show', [
            'invoice' => $invoice->load(['client.contacts', 'department', 'creator', 'salesQuotation', 'items.catalogItem', 'payments.recorder']),
            'paymentMethods' => Payment::METHODS,
            'vatRate' => app(FinanceCalculatorService::class)->vatRatePercent(),
        ]);
    }

    public function edit(Request $request, Invoice $invoice): View
    {
        $this->authorizeManage($request);

        if (! in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_ISSUED], true)) {
            abort(403);
        }

        return view('invoices.edit', [
            'invoice' => $invoice->load('items'),
            'clients' => Client::query()->where('is_active', true)->orderBy('name')->get(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'catalogItems' => CatalogItem::query()->where('is_active', true)->orderBy('name')->get(),
            'vatRate' => app(FinanceCalculatorService::class)->vatRatePercent(),
        ]);
    }

    public function update(Request $request, Invoice $invoice, FinanceCalculatorService $calculator): RedirectResponse
    {
        $this->authorizeManage($request);

        if (! in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_ISSUED], true)) {
            abort(403);
        }

        $data = $this->validated($request, $invoice);
        $items = $this->validatedItems($request);

        DB::transaction(function () use ($invoice, $data, $items, $calculator): void {
            $invoice->update($data);
            $calculator->syncInvoiceItems($invoice, $items);
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated.');
    }

    public function destroy(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeManage($request);
        $invoice->delete();

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted.');
    }

    public function issue(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeManage($request);

        $invoice->update([
            'status' => Invoice::STATUS_ISSUED,
            'issued_at' => $invoice->issued_at ?: now(),
        ]);

        return back()->with('success', 'Invoice issued.');
    }

    public function markSent(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeManage($request);

        $invoice->update([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => $invoice->sent_at ?: now(),
        ]);

        return back()->with('success', 'Invoice marked as sent.');
    }

    public function email(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeManage($request);

        $recipient = $invoice->client->billing_email ?: $invoice->client->email;
        if (! $recipient) {
            return back()->with('warning', 'Client has no email or billing email.');
        }

        Mail::send('emails.invoice', ['invoice' => $invoice->load(['client', 'items', 'payments'])], function ($message) use ($recipient, $invoice): void {
            $message->to($recipient)->subject("Invoice {$invoice->invoice_number}");
        });

        $invoice->update([
            'status' => Invoice::STATUS_SENT,
            'sent_at' => $invoice->sent_at ?: now(),
        ]);

        return back()->with('success', 'Invoice emailed to client.');
    }

    public function print(Request $request, Invoice $invoice): View
    {
        $this->authorizeView($request, $invoice);

        return view('invoices.print', [
            'invoice' => $invoice->load(['client.contacts', 'department', 'salesQuotation', 'items', 'payments']),
            'vatRate' => app(FinanceCalculatorService::class)->vatRatePercent(),
        ]);
    }

    private function validated(Request $request, ?Invoice $invoice = null): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'invoice_number' => ['nullable', 'string', 'max:40', Rule::unique('invoices', 'invoice_number')->ignore($invoice)],
            'status' => ['required', Rule::in([Invoice::STATUS_DRAFT, Invoice::STATUS_ISSUED])],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
        ]);
    }

    private function validatedItems(Request $request): array
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.catalog_item_id' => ['nullable', 'exists:catalog_items,id'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.taxable' => ['nullable', 'boolean'],
        ]);

        return collect($data['items'])
            ->filter(fn (array $item): bool => trim((string) ($item['description'] ?? '')) !== '')
            ->map(fn (array $item): array => $item + ['discount_amount' => 0, 'taxable' => false])
            ->values()
            ->all();
    }

    private function authorizeManage(Request $request): void
    {
        if (! $request->user()->canManageFinance()) {
            abort(403);
        }
    }

    private function authorizeView(Request $request, Invoice $invoice): void
    {
        if ($request->user()->canViewReports() || $invoice->department_id === $request->user()->department_id) {
            return;
        }

        abort(403);
    }
}
