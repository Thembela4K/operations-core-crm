@php
    $rows = old('items');

    if ($rows === null) {
        $rows = $expense->exists && $expense->items->isNotEmpty()
            ? $expense->items->map(fn ($item) => [
                'description' => $item->description,
                'item_type' => $item->item_type,
                'quantity' => $item->quantity,
                'unit_cost' => $item->unit_cost,
                'duration_months' => $item->duration_months,
            ])->all()
            : [['description' => '', 'item_type' => 'Material', 'quantity' => 1, 'unit_cost' => 0, 'duration_months' => 1]];
    }
@endphp

<div class="grid gap-4 md:grid-cols-4">
    <label><span class="label">Expense Number</span><input class="input" name="expense_number" value="{{ old('expense_number', $expense->expense_number) }}"></label>
    <label>
        <span class="label">Category</span>
        <select class="input" name="category" required>
            @foreach($categories as $category)
                <option value="{{ $category }}" @selected(old('category', $expense->category) === $category)>{{ $category }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Expense Type</span>
        <select class="input" name="expense_type">
            @foreach(\App\Models\Expense::TYPES as $type)
                <option value="{{ $type }}" @selected(old('expense_type', $expense->expense_type ?? 'Operational') === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Department</span>
        <select class="input" name="department_id">
            <option value="">Company-wide</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((int) old('department_id', $expense->department_id) === $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Linked Supplier</span>
        <select class="input" name="supplier_id">
            <option value="">No linked supplier</option>
            @foreach($suppliers as $supplier)
                <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $expense->supplier_id) === $supplier->id)>{{ $supplier->name }}</option>
            @endforeach
        </select>
    </label>
    <label><span class="label">Payee</span><input class="input" name="payee" value="{{ old('payee', $expense->payee) }}" required></label>
    <label><span class="label">Expense Date</span><input class="input" type="date" name="expense_date" value="{{ old('expense_date', optional($expense->expense_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" required></label>
    <label><span class="label">Duration (months)</span><input class="input" type="number" min="1" max="120" name="duration_months" value="{{ old('duration_months', $expense->duration_months ?? 1) }}" placeholder="1"></label>
    <label>
        <span class="label">Status</span>
        <select class="input" name="status" required>
            @foreach(\App\Models\Expense::STATUSES as $status)
                <option value="{{ $status }}" @selected(old('status', $expense->status ?: 'Recorded') === $status)>{{ $status }}</option>
            @endforeach
        </select>
    </label>
    <label><span class="label">VAT Amount</span><input class="input" type="number" min="0" step="0.01" name="vat_amount" value="{{ old('vat_amount', $expense->vat_amount ?? 0) }}" data-vat required></label>
</div>

<div class="mt-6">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h2 class="section-title">Expense Worksheet</h2>
            <p class="page-subtitle">One line per item. Duration multiplies the line (e.g. labour for 3 months).</p>
        </div>
        <button class="btn-secondary" type="button" data-add-expense-row>Add Line</button>
    </div>

    <div class="mt-4 overflow-x-auto">
        <table class="data-table" data-expense-rows>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="w-32">Type</th>
                    <th class="w-24">Qty</th>
                    <th class="w-32">Unit Cost</th>
                    <th class="w-28">Duration (mo)</th>
                    <th class="w-32 text-right">Line Total</th>
                    <th class="w-16"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $index => $row)
                    @php($lineTotal = \App\Models\ExpenseItem::computeLineTotal((float) ($row['quantity'] ?? 0), (float) ($row['unit_cost'] ?? 0), (int) ($row['duration_months'] ?? 1)))
                    <tr>
                        <td><input class="input" name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}" placeholder="e.g. Datamatics labour — network engineer"></td>
                        <td>
                            <select class="input" name="items[{{ $index }}][item_type]">
                                @foreach($itemTypes as $type)
                                    <option value="{{ $type }}" @selected(($row['item_type'] ?? 'Material') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input class="input" type="number" step="0.01" min="0" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] ?? 1 }}" data-qty></td>
                        <td><input class="input" type="number" step="0.01" min="0" name="items[{{ $index }}][unit_cost]" value="{{ $row['unit_cost'] ?? 0 }}" data-cost></td>
                        <td><input class="input" type="number" min="1" max="120" name="items[{{ $index }}][duration_months]" value="{{ $row['duration_months'] ?? 1 }}" data-months></td>
                        <td class="text-right"><span data-line-total>{{ number_format($lineTotal, 2) }}</span></td>
                        <td><button class="btn-secondary px-2 py-1" type="button" data-remove-expense-row>Remove</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4 grid gap-4 md:grid-cols-3">
        <label><span class="label">Amount Excl. VAT (from worksheet)</span><input class="input" type="number" min="0" step="0.01" name="amount" value="{{ old('amount', $expense->amount ?? 0) }}" data-amount required></label>
        <div class="flex items-end">
            <p class="text-sm text-neutral-700">
                Worksheet total: <strong>E<span data-worksheet-total>0.00</span></strong><br>
                <span class="text-xs text-neutral-500">The lines decide the amount when any line has a description.</span>
            </p>
        </div>
        <div class="flex items-end justify-end">
            <p class="text-sm text-neutral-700">Total incl. VAT: <strong>E<span data-grand-total>0.00</span></strong></p>
        </div>
    </div>

    <label class="mt-4 block"><span class="label">Notes</span><textarea class="input min-h-20" name="notes">{{ old('notes', $expense->notes) }}</textarea></label>
</div>

<script>
    (function () {
        var next = {{ count($rows) }};
        var table = document.querySelector('[data-expense-rows]');
        var amountInput = document.querySelector('[data-amount]');
        var vatInput = document.querySelector('[data-vat]');
        var types = @json($itemTypes);

        function recalc() {
            var total = 0;

            table.querySelectorAll('tbody tr').forEach(function (row) {
                var qty = parseFloat(row.querySelector('[data-qty]').value) || 0;
                var cost = parseFloat(row.querySelector('[data-cost]').value) || 0;
                var months = Math.max(1, parseInt(row.querySelector('[data-months]').value, 10) || 1);
                var line = qty * cost * months;
                total += line;
                row.querySelector('[data-line-total]').textContent = line.toFixed(2);
            });

            total = Math.round(total * 100) / 100;
            var vat = parseFloat(vatInput.value) || 0;

            document.querySelector('[data-worksheet-total]').textContent = total.toFixed(2);
            document.querySelector('[data-grand-total]').textContent = (total + vat).toFixed(2);
        }

        table.addEventListener('input', recalc);
        vatInput.addEventListener('input', recalc);

        var addButton = document.querySelector('[data-add-expense-row]');
        if (addButton) {
            addButton.addEventListener('click', function () {
                var index = next++;
                var row = document.createElement('tr');
                var options = types.map(function (type) {
                    return '<option value="' + type + '">' + type + '</option>';
                }).join('');

                row.innerHTML =
                    '<td><input class="input" name="items[' + index + '][description]" placeholder="e.g. Rent — November"></td>' +
                    '<td><select class="input" name="items[' + index + '][item_type]">' + options + '</select></td>' +
                    '<td><input class="input" type="number" step="0.01" min="0" name="items[' + index + '][quantity]" value="1" data-qty></td>' +
                    '<td><input class="input" type="number" step="0.01" min="0" name="items[' + index + '][unit_cost]" value="0" data-cost></td>' +
                    '<td><input class="input" type="number" min="1" max="120" name="items[' + index + '][duration_months]" value="1" data-months></td>' +
                    '<td class="text-right"><span data-line-total>0.00</span></td>' +
                    '<td><button class="btn-secondary px-2 py-1" type="button" data-remove-expense-row>Remove</button></td>';

                table.querySelector('tbody').appendChild(row);
                recalc();
            });
        }

        document.addEventListener('click', function (event) {
            var remove = event.target.closest('[data-remove-expense-row]');
            if (!remove) return;
            var rows = table.querySelectorAll('tbody tr');
            if (rows.length > 1) {
                remove.closest('tr').remove();
                recalc();
            }
        });

        recalc();
    })();
</script>
