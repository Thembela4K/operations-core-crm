@php
    $clientAddress = optional($invoice->client)->address;
    $termsValue = old('terms', $invoice->terms);
@endphp

{{-- QuickBooks-style header: two labelled columns, then Bill To / Ship To --}}
<div class="rounded border border-neutral-300">
    <div class="grid gap-x-6 gap-y-3 border-b border-neutral-200 bg-neutral-50 px-4 py-4 md:grid-cols-2">
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="sm:col-span-2">
                <span class="label">Customer:Job</span>
                <select class="input" name="client_id" required>
                    <option value="">Select customer</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" @selected((int) old('client_id', $invoice->client_id) === $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Class</span>
                <select class="input" name="department_id">
                    <option value="">Unassigned</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((int) old('department_id', $invoice->department_id) === $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Status</span>
                <select class="input" name="status" required>
                    @foreach([\App\Models\Invoice::STATUS_DRAFT, \App\Models\Invoice::STATUS_ISSUED] as $status)
                        <option value="{{ $status }}" @selected(old('status', $invoice->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label>
                <span class="label">Date</span>
                <input class="input" type="date" name="issue_date" value="{{ old('issue_date', optional($invoice->issue_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" required data-qb-issue-date>
            </label>
            <label>
                <span class="label">Invoice #</span>
                <input class="input" name="invoice_number" value="{{ old('invoice_number', $invoice->invoice_number) }}" placeholder="Auto-generated">
            </label>
            <label>
                <span class="label">P.O. No.</span>
                <input class="input" name="po_number" value="{{ old('po_number', $invoice->po_number) }}">
            </label>
            <label>
                <span class="label">Terms</span>
                <select class="input" name="terms" data-qb-terms>
                    <option value="">Select terms</option>
                    @foreach($paymentTerms as $label => $days)
                        <option value="{{ $label }}" data-days="{{ $days }}" @selected($termsValue === $label)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Due Date</span>
                <input class="input" type="date" name="due_date" value="{{ old('due_date', optional($invoice->due_date)->format('Y-m-d') ?: now()->addDays(30)->format('Y-m-d')) }}" required data-qb-due-date>
            </label>
            <label>
                <span class="label">Ship Date</span>
                <input class="input" type="date" name="ship_date" value="{{ old('ship_date', optional($invoice->ship_date)->format('Y-m-d')) }}">
            </label>
            <label class="sm:col-span-2">
                <span class="label">Ship Via</span>
                <input class="input" name="ship_via" value="{{ old('ship_via', $invoice->ship_via) }}" placeholder="e.g. Courier, Collect">
            </label>
        </div>
    </div>

    <div class="grid gap-6 px-4 py-4 md:grid-cols-2">
        <div>
            <span class="label">Bill To</span>
            <p class="mt-1 text-sm text-neutral-700">
                {{ optional($invoice->client)->name ?? 'Select a customer above' }}
                @if($clientAddress)
                    <br>{!! nl2br(e($clientAddress)) !!}
                @endif
            </p>
        </div>
        <label>
            <span class="label">Ship To</span>
            <textarea class="input min-h-20" name="ship_to" placeholder="Leave blank if the same as Bill To">{{ old('ship_to', $invoice->ship_to) }}</textarea>
        </label>
    </div>
</div>

<div class="mt-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="section-title">Line Items</h2>
        <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">VAT is fixed at {{ $vatRate }}%</span>
    </div>
    @php
        $items = $invoice->items?->map(fn ($item) => [
            'catalog_item_id' => $item->catalog_item_id,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'discount_amount' => $item->discount_amount,
            'taxable' => $item->taxable,
        ])->all() ?: null;
    @endphp
    @include('finance.line-items', ['items' => $items, 'catalogItems' => $catalogItems, 'vatRate' => $vatRate])
</div>

<div class="mt-6 grid gap-4 border-t border-neutral-200 pt-6 md:grid-cols-2">
    <label>
        <span class="label">Customer Message</span>
        <textarea class="input min-h-20" name="customer_message" placeholder="Shown on the printed invoice">{{ old('customer_message', $invoice->customer_message) }}</textarea>
    </label>
    <label>
        <span class="label">Memo</span>
        <textarea class="input min-h-20" name="notes">{{ old('notes', $invoice->notes) }}</textarea>
    </label>
</div>

<script>
    (function () {
        var terms = document.querySelector('[data-qb-terms]');
        var issue = document.querySelector('[data-qb-issue-date]');
        var due = document.querySelector('[data-qb-due-date]');
        if (!terms || !issue || !due) return;

        // QuickBooks behaviour: picking Terms recalculates Due Date from Date.
        function applyTerms() {
            var option = terms.options[terms.selectedIndex];
            if (!option || !option.dataset.days || !issue.value) return;
            var date = new Date(issue.value + 'T00:00:00');
            date.setDate(date.getDate() + parseInt(option.dataset.days, 10));
            due.value = date.toISOString().slice(0, 10);
        }

        terms.addEventListener('change', applyTerms);
        issue.addEventListener('change', applyTerms);
    })();
</script>
