<div class="grid gap-4 md:grid-cols-2">
    <label><span class="label">Supplier Code</span><input class="input" name="supplier_code" value="{{ old('supplier_code', $supplier->supplier_code) }}"></label>
    <label><span class="label">Supplier Name</span><input class="input" name="name" value="{{ old('name', $supplier->name) }}" required></label>
    <label><span class="label">Contact Person</span><input class="input" name="contact_person" value="{{ old('contact_person', $supplier->contact_person) }}"></label>
    <label><span class="label">Email</span><input class="input" type="email" name="email" value="{{ old('email', $supplier->email) }}"></label>
    <label><span class="label">Phone</span><input class="input" name="phone" value="{{ old('phone', $supplier->phone) }}"></label>
    <label><span class="label">VAT / Tax Number</span><input class="input" name="vat_number" value="{{ old('vat_number', $supplier->vat_number) }}"></label>
    <label class="md:col-span-2"><span class="label">Address</span><textarea class="input min-h-20" name="address">{{ old('address', $supplier->address) }}</textarea></label>
    <label class="md:col-span-2"><span class="label">Notes</span><textarea class="input min-h-24" name="notes">{{ old('notes', $supplier->notes) }}</textarea></label>
    <label class="flex items-center gap-2 text-sm text-neutral-700"><input class="rounded border-neutral-300" type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->is_active))> Active</label>
</div>
