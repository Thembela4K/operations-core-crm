@php
    $neededBy = $requisition->needed_by;
    $neededByValue = $neededBy instanceof \Carbon\CarbonInterface ? $neededBy->toDateString() : $neededBy;
    $itemRows = collect(old('items', $requisition->relationLoaded('items') && $requisition->items->isNotEmpty()
        ? $requisition->items->map(fn ($item) => [
            'description' => $item->description,
            'payment_type' => $item->payment_type,
            'quantity' => $item->quantity,
            'estimated_unit_cost' => $item->estimated_unit_cost,
            'source' => $item->source,
            'notes' => $item->notes,
        ])->all()
        : [[
            'description' => '',
            'payment_type' => 'Cash',
            'quantity' => 1,
            'estimated_unit_cost' => 0,
            'source' => '',
            'notes' => '',
        ]]));
@endphp

<div class="grid gap-4 lg:grid-cols-3">
    <label>
        <span class="label">To</span>
        <input class="input" name="addressed_to" value="{{ old('addressed_to', $requisition->addressed_to ?: 'Directors') }}" required>
    </label>
    <label>
        <span class="label">Requisition Number</span>
        <input class="input" name="requisition_number" value="{{ old('requisition_number', $requisition->requisition_number) }}">
    </label>
    <label>
        <span class="label">Department</span>
        <select class="input" name="department_id">
            <option value="">Company-wide</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((int) old('department_id', $requisition->department_id) === $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Preferred Supplier</span>
        <select class="input" name="supplier_id">
            <option value="">No supplier selected</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $requisition->supplier_id) === $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Needed By</span>
        <input class="input" type="date" name="needed_by" value="{{ old('needed_by', $neededByValue) }}">
    </label>
    <label class="lg:col-span-3">
        <span class="label">Title</span>
        <input class="input" name="title" value="{{ old('title', $requisition->title) }}" placeholder="Example: Tender document purchase and binding funds" required>
    </label>
    <label>
        <span class="label">Category</span>
        <select class="input" name="category" required>
            @foreach($categories as $category)
                <option value="{{ $category }}" @selected(old('category', $requisition->category) === $category)>{{ $category }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Priority</span>
        <select class="input" name="priority" required>
            @foreach($priorities as $priority)
                <option value="{{ $priority }}" @selected(old('priority', $requisition->priority) === $priority)>{{ $priority }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Attachments</span>
        <input class="input" type="file" name="attachments[]" multiple>
    </label>
    <label class="lg:col-span-3">
        <span class="label">Purpose</span>
        <textarea class="input min-h-24" name="purpose">{{ old('purpose', $requisition->purpose) }}</textarea>
    </label>
    <label class="lg:col-span-3">
        <span class="label">Notes</span>
        <textarea class="input min-h-20" name="notes">{{ old('notes', $requisition->notes) }}</textarea>
    </label>
</div>

<section class="mt-6 rounded-md border border-neutral-200 bg-neutral-50 p-3" data-requisition-items>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="section-title">Requested Items</h2>
        <p class="page-subtitle">Add each amount requested for director approval and indicate whether it is bank, cash, revenue, or another source.</p>
        </div>
        <button class="btn-secondary" type="button" data-requisition-line-add>Add Item</button>
    </div>

    <div class="mt-4 overflow-x-auto">
        <table class="data-table min-w-[980px] bg-white">
            <thead>
                <tr>
                    <th>Details</th>
                    <th>Payment Type</th>
                    <th>Quantity</th>
                    <th>Est. Unit Cost</th>
                    <th>Line Total</th>
                    <th>Source / Account Details</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-requisition-line-body>
                @foreach($itemRows as $index => $item)
                    <tr data-requisition-line-row>
                        <td>
                            <textarea class="input min-h-16" name="items[{{ $index }}][description]" required data-requisition-description>{{ $item['description'] ?? '' }}</textarea>
                        </td>
                        <td>
                            <select class="input" name="items[{{ $index }}][payment_type]" data-requisition-payment-type>
                                @foreach(['Bank', 'Cash', 'Revenue', 'Other'] as $type)
                                    <option value="{{ $type }}" @selected(($item['payment_type'] ?? 'Cash') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input class="input" type="number" step="0.01" min="0.01" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" data-requisition-quantity></td>
                        <td><input class="input" type="number" step="0.01" min="0" name="items[{{ $index }}][estimated_unit_cost]" value="{{ $item['estimated_unit_cost'] ?? 0 }}" data-requisition-unit-cost></td>
                        <td class="font-semibold">E<span data-requisition-line-total>0.00</span></td>
                        <td>
                            <textarea class="input min-h-16" name="items[{{ $index }}][source]" placeholder="Bank account, cash supplier, revenue source, or notes">{{ $item['source'] ?? '' }}</textarea>
                            <input type="hidden" name="items[{{ $index }}][notes]" value="{{ $item['notes'] ?? '' }}">
                        </td>
                        <td class="text-right"><button class="text-rose-700" type="button" data-requisition-line-remove>Remove</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex justify-end">
        <div class="finance-total-box">
            <div><span>Total Bank</span><strong>E<span data-requisition-bank-total>0.00</span></strong></div>
            <div><span>Total Cash</span><strong>E<span data-requisition-cash-total>0.00</span></strong></div>
            <div><span>Total Other</span><strong>E<span data-requisition-other-total>0.00</span></strong></div>
            <div><span>Estimated Total</span><strong>E<span data-requisition-total>0.00</span></strong></div>
        </div>
    </div>
</section>
