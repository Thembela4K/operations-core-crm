<div class="grid gap-4 md:grid-cols-3">
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
    <label><span class="label">Payee / Supplier</span><input class="input" name="payee" value="{{ old('payee', $expense->payee) }}" required></label>
    <label><span class="label">Expense Date</span><input class="input" type="date" name="expense_date" value="{{ old('expense_date', optional($expense->expense_date)->format('Y-m-d') ?: now()->format('Y-m-d')) }}" required></label>
    <label><span class="label">Status</span><input class="input" name="status" value="{{ old('status', $expense->status ?: 'Recorded') }}" required></label>
    <label><span class="label">Amount Excl. VAT</span><input class="input" type="number" min="0" step="0.01" name="amount" value="{{ old('amount', $expense->amount ?? 0) }}" required></label>
    <label><span class="label">VAT Amount</span><input class="input" type="number" min="0" step="0.01" name="vat_amount" value="{{ old('vat_amount', $expense->vat_amount ?? 0) }}" required></label>
    <label class="md:col-span-3"><span class="label">Notes</span><textarea class="input min-h-24" name="notes">{{ old('notes', $expense->notes) }}</textarea></label>
</div>
