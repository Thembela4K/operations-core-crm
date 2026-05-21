<div class="grid gap-4 md:grid-cols-3">
    <label><span class="label">Quotation ID</span><input class="input" name="quotation_code" value="{{ old('quotation_code', $quotation->quotation_code) }}" required></label>
    <label><span class="label">Client</span><input class="input" name="client" value="{{ old('client', $quotation->client) }}" required></label>
    <label><span class="label">Opportunity / Request</span><input class="input" name="opportunity" value="{{ old('opportunity', $quotation->opportunity) }}" required></label>
    <label><span class="label">Status</span><select class="input" name="status">@foreach($statuses as $status)<option @selected(old('status', $quotation->status) === $status)>{{ $status }}</option>@endforeach</select></label>
    <label><span class="label">Priority</span><select class="input" name="priority">@foreach($priorities as $priority)<option @selected(old('priority', $quotation->priority) === $priority)>{{ $priority }}</option>@endforeach</select></label>
    <label><span class="label">Issue Date</span><input class="input" type="date" name="issue_date" value="{{ old('issue_date', $quotation->issue_date ? \Illuminate\Support\Carbon::parse($quotation->issue_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" required></label>
    <label><span class="label">Due / Valid Until</span><input class="input" type="date" name="valid_until" value="{{ old('valid_until', $quotation->valid_until ? \Illuminate\Support\Carbon::parse($quotation->valid_until)->format('Y-m-d') : now()->addMonth()->format('Y-m-d')) }}" required></label>
    <label class="md:col-span-3"><span class="label">Notes</span><textarea class="input min-h-28" name="notes">{{ old('notes', $quotation->notes) }}</textarea></label>
</div>
