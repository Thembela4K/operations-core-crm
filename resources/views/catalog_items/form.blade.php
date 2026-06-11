<div class="grid gap-4 md:grid-cols-3">
    <label>
        <span class="label">Type</span>
        <select class="input" name="type" required>
            @foreach($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $item->type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="md:col-span-2"><span class="label">Item Name</span><input class="input" name="name" value="{{ old('name', $item->name) }}" required></label>
    <label>
        <span class="label">Department</span>
        <select class="input" name="department_id">
            <option value="">Shared Item</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((int) old('department_id', $item->department_id) === $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </label>
    <label><span class="label">Unit Price</span><input class="input" type="number" min="0" step="0.01" name="unit_price" value="{{ old('unit_price', $item->unit_price ?? 0) }}" required></label>
    <div class="grid gap-3 md:grid-cols-2">
        <label class="mt-7 flex items-center gap-2 text-sm text-neutral-700">
            <input class="rounded border-neutral-300" type="checkbox" name="taxable" value="1" @checked(old('taxable', $item->taxable ?? true))>
            VAT 15%
        </label>
        <label class="mt-7 flex items-center gap-2 text-sm text-neutral-700">
            <input class="rounded border-neutral-300" type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true))>
            Active
        </label>
    </div>
    <label class="md:col-span-3"><span class="label">Description</span><textarea class="input min-h-28" name="description">{{ old('description', $item->description) }}</textarea></label>
</div>
