<div class="grid gap-4 md:grid-cols-3">
    <label><span class="label">Quotation Number</span><input class="input" name="quotation_number" value="{{ old('quotation_number', $salesQuotation->quotation_number) }}"></label>
    <label class="md:col-span-2"><span class="label">Title</span><input class="input" name="title" value="{{ old('title', $salesQuotation->title) }}" required></label>
    <label>
        <span class="label">Client</span>
        <select class="input" name="client_id" required>
            <option value="">Select client</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}" @selected((int) old('client_id', $salesQuotation->client_id) === $client->id)>{{ $client->name }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Department</span>
        <select class="input" name="department_id">
            <option value="">Unassigned</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((int) old('department_id', $salesQuotation->department_id) === $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </label>
    <label><span class="label">Issue Date</span><input class="input" type="date" name="issue_date" value="{{ old('issue_date', optional($salesQuotation->issue_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" required></label>
    <label><span class="label">Valid Until</span><input class="input" type="date" name="valid_until" value="{{ old('valid_until', optional($salesQuotation->valid_until)->format('Y-m-d') ?: now()->addDays(30)->format('Y-m-d')) }}" required></label>
    <label class="md:col-span-3"><span class="label">Notes</span><textarea class="input min-h-24" name="notes">{{ old('notes', $salesQuotation->notes) }}</textarea></label>
    <label class="md:col-span-3"><span class="label">Terms</span><textarea class="input min-h-24" name="terms">{{ old('terms', $salesQuotation->terms) }}</textarea></label>
</div>

<div class="mt-6 border-t border-neutral-200 pt-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="section-title">Line Items</h2>
        <span class="text-xs font-semibold uppercase tracking-wide text-neutral-500">VAT is fixed at {{ $vatRate }}%</span>
    </div>
    @php
        $items = $salesQuotation->items?->map(fn ($item) => [
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
