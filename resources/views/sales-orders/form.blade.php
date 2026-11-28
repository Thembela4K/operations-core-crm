<div class="grid gap-4 md:grid-cols-2">
    <label>
        <span class="label">Accepted Quotation</span>
        <select class="input" name="sales_quotation_id">
            <option value="">No quotation (standalone order)</option>
            @foreach($quotations as $quotation)
                <option value="{{ $quotation->id }}" @selected((int) old('sales_quotation_id', $order->sales_quotation_id) === $quotation->id)>
                    {{ $quotation->quotation_number }} - {{ $quotation->title }} ({{ $quotation->status }})
                </option>
            @endforeach
        </select>
        <span class="mt-1 block text-xs text-neutral-500">One accepted quotation can produce one sales order.</span>
    </label>

    <label>
        <span class="label">Client Purchase Order</span>
        <input class="input" name="client_po_reference" value="{{ old('client_po_reference', $order->client_po_reference) }}" placeholder="Client PO / award reference">
    </label>

    <label>
        <span class="label">Client</span>
        <select class="input" name="client_id" required>
            <option value="">Select client</option>
            @foreach($clients as $client)
                <option value="{{ $client->id }}" @selected((int) old('client_id', $order->client_id) === $client->id)>{{ $client->name }}</option>
            @endforeach
        </select>
    </label>

    <label>
        <span class="label">Department</span>
        <select class="input" name="department_id">
            <option value="">Unassigned</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((int) old('department_id', $order->department_id) === $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </label>

    <label>
        <span class="label">Order Number</span>
        <input class="input" name="order_number" value="{{ old('order_number', $order->order_number) }}" placeholder="Auto-generated">
    </label>

    <label>
        <span class="label">Status</span>
        <select class="input" name="status" required>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $order->status) === $status)>{{ $status }}</option>
            @endforeach
        </select>
    </label>

    <label class="md:col-span-2">
        <span class="label">Title</span>
        <input class="input" name="title" value="{{ old('title', $order->title) }}" required placeholder="What the company has committed to deliver">
    </label>

    <label class="md:col-span-2">
        <span class="label">Agreed Scope</span>
        <textarea class="input min-h-24" name="scope">{{ old('scope', $order->scope) }}</textarea>
    </label>

    <label>
        <span class="label">Order Value</span>
        <input class="input" type="number" step="0.01" min="0" name="value" value="{{ old('value', $order->value ?? 0) }}">
    </label>

    <label>
        <span class="label">Order Date</span>
        <input class="input" type="date" name="order_date" value="{{ old('order_date', optional($order->order_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}">
    </label>

    <label>
        <span class="label">Delivery Date</span>
        <input class="input" type="date" name="delivery_date" value="{{ old('delivery_date', optional($order->delivery_date)->format('Y-m-d')) }}">
    </label>

    <label class="md:col-span-2">
        <span class="label">Delivery Requirements</span>
        <textarea class="input min-h-20" name="delivery_requirements">{{ old('delivery_requirements', $order->delivery_requirements) }}</textarea>
    </label>

    <label class="md:col-span-2">
        <span class="label">Notes</span>
        <textarea class="input min-h-20" name="notes">{{ old('notes', $order->notes) }}</textarea>
    </label>
</div>
