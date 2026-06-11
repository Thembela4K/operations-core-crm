@php
    $lineItems = old('items', $items ?? [['description' => '', 'quantity' => 1, 'unit_price' => 0, 'discount_amount' => 0, 'taxable' => true]]);
@endphp

<div class="finance-lines" data-line-items data-vat-rate="{{ $vatRate ?? 15 }}">
    <div class="overflow-x-auto">
        <table class="data-table finance-lines-table">
            <thead>
                <tr>
                    <th class="w-10">Move</th>
                    <th>Item / Description</th>
                    <th class="w-28">Qty</th>
                    <th class="w-36">Unit Price</th>
                    <th class="w-36">Discount</th>
                    <th class="w-24">VAT</th>
                    <th class="w-36 text-right">Total</th>
                    <th class="w-20"></th>
                </tr>
            </thead>
            <tbody data-line-items-body>
                @foreach($lineItems as $index => $item)
                    <tr data-line-item-row draggable="true">
                        <td>
                            <button class="btn-secondary px-2 py-1" type="button" data-line-drag-handle title="Drag to reorder">::</button>
                        </td>
                        <td>
                            <select class="input mb-2" name="items[{{ $index }}][catalog_item_id]" data-catalog-select>
                                <option value="">Custom line item</option>
                                @foreach($catalogItems as $catalogItem)
                                    <option
                                        value="{{ $catalogItem->id }}"
                                        data-description="{{ $catalogItem->description ?: $catalogItem->name }}"
                                        data-price="{{ $catalogItem->unit_price }}"
                                        data-taxable="{{ $catalogItem->taxable ? 1 : 0 }}"
                                        @selected((int) ($item['catalog_item_id'] ?? 0) === $catalogItem->id)
                                    >
                                        {{ $catalogItem->name }} | {{ $catalogItem->department?->name ?? 'Shared' }}
                                    </option>
                                @endforeach
                            </select>
                            <textarea class="input min-h-20" name="items[{{ $index }}][description]" data-line-description required>{{ $item['description'] ?? '' }}</textarea>
                        </td>
                        <td><input class="input" type="number" step="0.01" min="0.01" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? 1 }}" data-line-quantity required></td>
                        <td><input class="input" type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? 0 }}" data-line-unit-price required></td>
                        <td><input class="input" type="number" step="0.01" min="0" name="items[{{ $index }}][discount_amount]" value="{{ $item['discount_amount'] ?? 0 }}" data-line-discount></td>
                        <td>
                            <label class="flex items-center gap-2 text-sm text-neutral-700">
                                <input type="hidden" name="items[{{ $index }}][taxable]" value="0">
                                <input class="rounded border-neutral-300" type="checkbox" name="items[{{ $index }}][taxable]" value="1" data-line-taxable @checked((bool) ($item['taxable'] ?? true))>
                                15%
                            </label>
                        </td>
                        <td class="text-right">
                            <strong data-line-total>0.00</strong>
                            <small class="block text-xs text-neutral-500" data-line-vat>VAT 0.00</small>
                        </td>
                        <td>
                            <button class="btn-secondary px-2 py-1" type="button" data-line-remove>Remove</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <button class="btn-secondary" type="button" data-line-add>Add Line Item</button>
        <div class="finance-total-box">
            <div><span>Subtotal</span><strong data-lines-subtotal>0.00</strong></div>
            <div><span>VAT 15%</span><strong data-lines-vat>0.00</strong></div>
            <div><span>Grand Total</span><strong data-lines-total>0.00</strong></div>
        </div>
    </div>
</div>
