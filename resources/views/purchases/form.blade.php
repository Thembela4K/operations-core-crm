@php
    $supplierAddress = optional($purchase->supplier)->address;
    $existing = old('items', $purchase->items?->map(fn ($item) => [
        'catalog_item_id' => $item->catalog_item_id,
        'description' => $item->description,
        'quantity' => $item->quantity,
        'unit_price' => $item->unit_price,
    ])->all() ?: [['description' => '', 'quantity' => 1, 'unit_price' => 0]]);
@endphp

{{-- QuickBooks-style purchase order header --}}
<div class="rounded border border-neutral-300">
    <div class="grid gap-x-6 gap-y-3 border-b border-neutral-200 bg-neutral-50 px-4 py-4 md:grid-cols-2">
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="sm:col-span-2">
                <span class="label">Vendor</span>
                <select class="input" name="supplier_id">
                    <option value="">No vendor</option>
                    @foreach($suppliers as $supplier)
                        <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $purchase->supplier_id) === $supplier->id)>{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Class</span>
                <select class="input" name="department_id">
                    <option value="">Company-wide</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((int) old('department_id', $purchase->department_id) === $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Status</span>
                <select class="input" name="status" required>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" @selected(old('status', $purchase->status) === $status)>{{ $status }}</option>
                    @endforeach
                </select>
            </label>
            <label class="sm:col-span-2">
                <span class="label">Requisition</span>
                <select class="input" name="requisition_id">
                    <option value="">No requisition</option>
                    @foreach($requisitions as $requisition)
                        <option value="{{ $requisition->id }}" @selected((int) old('requisition_id', $purchase->requisition_id) === $requisition->id)>{{ $requisition->requisition_number }} - {{ $requisition->title }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <label>
                <span class="label">Date</span>
                <input class="input" type="date" name="purchase_date" value="{{ old('purchase_date', optional($purchase->purchase_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}">
            </label>
            <label>
                <span class="label">P.O. Number</span>
                <input class="input" name="purchase_number" value="{{ old('purchase_number', $purchase->purchase_number) }}" placeholder="Auto-generated">
            </label>
            <label>
                <span class="label">Terms</span>
                <select class="input" name="terms" data-po-terms>
                    <option value="">Select terms</option>
                    @foreach($paymentTerms as $label => $days)
                        <option value="{{ $label }}" data-days="{{ $days }}" @selected(old('terms', $purchase->terms) === $label)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Expected Date</span>
                <input class="input" type="date" name="expected_date" value="{{ old('expected_date', optional($purchase->expected_date)->format('Y-m-d')) }}" data-po-expected>
            </label>
            <label class="sm:col-span-2">
                <span class="label">Title</span>
                <input class="input" name="title" value="{{ old('title', $purchase->title) }}" required placeholder="Short description of this order">
            </label>
        </div>
    </div>

    <div class="grid gap-6 px-4 py-4 md:grid-cols-2">
        <div>
            <span class="label">Vendor Address</span>
            <p class="mt-1 text-sm text-neutral-700">
                {{ optional($purchase->supplier)->name ?? 'Select a vendor above' }}
                @if($supplierAddress)
                    <br>{!! nl2br(e($supplierAddress)) !!}
                @endif
            </p>
        </div>
        <label>
            <span class="label">Ship To</span>
            <textarea class="input min-h-20" name="ship_to" placeholder="Where the goods should be delivered">{{ old('ship_to', $purchase->ship_to) }}</textarea>
        </label>
    </div>
</div>

{{-- Line items: Item / Description / Qty / Rate / Amount --}}
<div class="mt-6" data-po-lines>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="section-title">Line Items</h2>
        <button class="btn-secondary" type="button" data-po-add>Add Line</button>
    </div>

    <div class="mt-3 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Description</th>
                    <th class="w-24">Qty</th>
                    <th class="w-32">Rate</th>
                    <th class="w-32 text-right">Amount</th>
                    <th class="w-16"></th>
                </tr>
            </thead>
            <tbody data-po-body>
                @foreach($existing as $index => $item)
                    <tr data-po-row>
                        <td>
                            <select class="input" name="items[{{ $index }}][catalog_item_id]" data-po-catalog>
                                <option value="">Custom item</option>
                                @foreach($catalogItems as $catalogItem)
                                    <option
                                        value="{{ $catalogItem->id }}"
                                        data-description="{{ $catalogItem->description ?: $catalogItem->name }}"
                                        data-price="{{ $catalogItem->unit_price }}"
                                        @selected((int) ($item['catalog_item_id'] ?? 0) === $catalogItem->id)
                                    >{{ $catalogItem->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><textarea class="input min-h-16" name="items[{{ $index }}][description]" data-po-description>{{ $item['description'] ?? '' }}</textarea></td>
                        <td><input class="input" type="number" step="0.01" min="0.01" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" data-po-qty></td>
                        <td><input class="input" type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? 0 }}" data-po-rate></td>
                        <td class="text-right"><strong data-po-amount>E0.00</strong></td>
                        <td><button class="btn-secondary px-2 py-1" type="button" data-po-remove>Remove</button></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="text-right font-semibold">Order Total</td>
                    <td class="text-right"><strong data-po-total>E0.00</strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="mt-6 grid gap-4 border-t border-neutral-200 pt-6 md:grid-cols-2">
    <label>
        <span class="label">Memo</span>
        <textarea class="input min-h-20" name="notes">{{ old('notes', $purchase->notes) }}</textarea>
    </label>
</div>

<script>
    (function () {
        var root = document.querySelector('[data-po-lines]');
        if (!root) return;

        var body = root.querySelector('[data-po-body]');
        var addButton = root.querySelector('[data-po-add]');
        var totalCell = root.querySelector('[data-po-total]');
        var terms = document.querySelector('[data-po-terms]');
        var dateInput = document.querySelector('input[name="purchase_date"]');
        var expected = document.querySelector('[data-po-expected]');
        var nextIndex = {{ count($existing) }};

        function money(value) {
            return 'E' + (Math.round(value * 100) / 100).toFixed(2);
        }

        function recalculate() {
            var total = 0;
            body.querySelectorAll('[data-po-row]').forEach(function (row) {
                var qty = parseFloat(row.querySelector('[data-po-qty]').value) || 0;
                var rate = parseFloat(row.querySelector('[data-po-rate]').value) || 0;
                var amount = qty * rate;
                total += amount;
                row.querySelector('[data-po-amount]').textContent = money(amount);
            });
            totalCell.textContent = money(total);
        }

        function addRow() {
            var first = body.querySelector('[data-po-row]');
            if (!first) return;
            var clone = first.cloneNode(true);
            clone.querySelectorAll('input, select, textarea').forEach(function (field) {
                var name = field.getAttribute('name');
                if (name) {
                    field.setAttribute('name', name.replace(/items\[\d+\]/, 'items[' + nextIndex + ']'));
                }
                if (field.tagName === 'SELECT') {
                    field.selectedIndex = 0;
                } else if (field.type === 'number') {
                    field.value = field.hasAttribute('data-po-qty') ? '1' : '0';
                } else {
                    field.value = '';
                }
            });
            nextIndex++;
            body.appendChild(clone);
            recalculate();
        }

        addButton.addEventListener('click', addRow);

        root.addEventListener('click', function (event) {
            var remove = event.target.closest('[data-po-remove]');
            if (!remove) return;
            if (body.querySelectorAll('[data-po-row]').length <= 1) return;
            remove.closest('[data-po-row]').remove();
            recalculate();
        });

        body.addEventListener('input', recalculate);

        body.addEventListener('change', function (event) {
            if (!event.target.matches('[data-po-catalog]')) return;
            var row = event.target.closest('[data-po-row]');
            var option = event.target.options[event.target.selectedIndex];
            if (option && option.dataset.description) {
                row.querySelector('[data-po-description]').value = option.dataset.description;
            }
            if (option && option.dataset.price) {
                row.querySelector('[data-po-rate]').value = option.dataset.price;
            }
            recalculate();
        });

        // QuickBooks behaviour: Terms drives the expected date.
        function applyTerms() {
            if (!terms || !dateInput || !expected) return;
            var option = terms.options[terms.selectedIndex];
            if (!option || !option.dataset.days || !dateInput.value) return;
            var date = new Date(dateInput.value + 'T00:00:00');
            date.setDate(date.getDate() + parseInt(option.dataset.days, 10));
            expected.value = date.toISOString().slice(0, 10);
        }

        if (terms) terms.addEventListener('change', applyTerms);

        recalculate();
    })();
</script>
