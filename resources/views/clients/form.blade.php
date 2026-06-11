<div class="grid gap-4 md:grid-cols-3">
    <label><span class="label">Client Code</span><input class="input" name="client_code" value="{{ old('client_code', $client->client_code) }}"></label>
    <label class="md:col-span-2"><span class="label">Client Name</span><input class="input" name="name" value="{{ old('name', $client->name) }}" required></label>
    <label><span class="label">Main Email</span><input class="input" type="email" name="email" value="{{ old('email', $client->email) }}"></label>
    <label><span class="label">Billing Email</span><input class="input" type="email" name="billing_email" value="{{ old('billing_email', $client->billing_email) }}"></label>
    <label><span class="label">Phone</span><input class="input" name="phone" value="{{ old('phone', $client->phone) }}"></label>
    <label><span class="label">VAT / Tax Number</span><input class="input" name="vat_number" value="{{ old('vat_number', $client->vat_number) }}"></label>
    <label><span class="label">City</span><input class="input" name="city" value="{{ old('city', $client->city) }}"></label>
    <label><span class="label">Country</span><input class="input" name="country" value="{{ old('country', $client->country ?: 'Eswatini') }}"></label>
    <label class="md:col-span-3"><span class="label">Billing Address</span><textarea class="input min-h-24" name="address">{{ old('address', $client->address) }}</textarea></label>
    <label class="md:col-span-3"><span class="label">Notes</span><textarea class="input min-h-24" name="notes">{{ old('notes', $client->notes) }}</textarea></label>
    <label class="flex items-center gap-2 text-sm text-neutral-700">
        <input class="rounded border-neutral-300" type="checkbox" name="is_active" value="1" @checked(old('is_active', $client->is_active ?? true))>
        Active
    </label>
</div>

<div class="mt-6 border-t border-neutral-200 pt-6">
    <h2 class="section-title">Primary Contact</h2>
    @php($primaryContact = $client->contacts?->firstWhere('is_primary', true))
    <div class="mt-4 grid gap-4 md:grid-cols-4">
        <label><span class="label">Name</span><input class="input" name="contact_name" value="{{ old('contact_name', $primaryContact?->name) }}"></label>
        <label><span class="label">Email</span><input class="input" type="email" name="contact_email" value="{{ old('contact_email', $primaryContact?->email) }}"></label>
        <label><span class="label">Phone</span><input class="input" name="contact_phone" value="{{ old('contact_phone', $primaryContact?->phone) }}"></label>
        <label><span class="label">Position</span><input class="input" name="contact_position" value="{{ old('contact_position', $primaryContact?->position) }}"></label>
    </div>
</div>
