@php($limited = $quotation->exists && ! auth()->user()->canManage())

@if($limited)
    <div class="grid gap-4 md:grid-cols-3">
        <label><span class="label">Status</span><select class="input" name="status">@foreach($statuses as $status)<option @selected(old('status', $quotation->status) === $status)>{{ $status }}</option>@endforeach</select></label>
        <label><span class="label">Win Probability %</span><input class="input" type="number" min="0" max="100" name="win_probability_percent" value="{{ old('win_probability_percent', $quotation->win_probability_percent) }}" required></label>
        <label class="md:col-span-3"><span class="label">Notes</span><textarea class="input min-h-28" name="notes">{{ old('notes', $quotation->notes) }}</textarea></label>
    </div>
@else
    <div class="grid gap-4 md:grid-cols-3">
        <label><span class="label">Quotation ID</span><input class="input" name="quotation_code" value="{{ old('quotation_code', $quotation->quotation_code) }}" required></label>
        <label><span class="label">Client</span><input class="input" name="client" value="{{ old('client', $quotation->client) }}" required></label>
        <label><span class="label">Opportunity</span><input class="input" name="opportunity" value="{{ old('opportunity', $quotation->opportunity) }}" required></label>
        <label><span class="label">Owner</span><input class="input" name="owner" value="{{ old('owner', $quotation->owner) }}" required></label>
        <label><span class="label">Owner Email</span><input class="input" type="email" name="owner_email" value="{{ old('owner_email', $quotation->owner_email) }}"></label>
        <label><span class="label">Status</span><select class="input" name="status">@foreach($statuses as $status)<option @selected(old('status', $quotation->status) === $status)>{{ $status }}</option>@endforeach</select></label>
        <label><span class="label">Priority</span><select class="input" name="priority">@foreach($priorities as $priority)<option @selected(old('priority', $quotation->priority) === $priority)>{{ $priority }}</option>@endforeach</select></label>
        <label><span class="label">Risk</span><select class="input" name="risk">@foreach($risks as $risk)<option @selected(old('risk', $quotation->risk) === $risk)>{{ $risk }}</option>@endforeach</select></label>
        <label><span class="label">Rating</span><input class="input" type="number" min="0" max="5" step="0.1" name="rating" value="{{ old('rating', $quotation->rating) }}" required></label>
        <label><span class="label">Win Probability %</span><input class="input" type="number" min="0" max="100" name="win_probability_percent" value="{{ old('win_probability_percent', $quotation->win_probability_percent) }}" required></label>
        <label><span class="label">Quoted Amount</span><input class="input" type="number" min="0" step="0.01" name="quoted_amount" value="{{ old('quoted_amount', $quotation->quoted_amount) }}" required></label>
        <label><span class="label">Expected Cost</span><input class="input" type="number" min="0" step="0.01" name="expected_cost" value="{{ old('expected_cost', $quotation->expected_cost) }}" required></label>
        <label><span class="label">Issue Date</span><input class="input" type="date" name="issue_date" value="{{ old('issue_date', $quotation->issue_date ? \Illuminate\Support\Carbon::parse($quotation->issue_date)->format('Y-m-d') : now()->format('Y-m-d')) }}" required></label>
        <label><span class="label">Valid Until</span><input class="input" type="date" name="valid_until" value="{{ old('valid_until', $quotation->valid_until ? \Illuminate\Support\Carbon::parse($quotation->valid_until)->format('Y-m-d') : now()->addMonth()->format('Y-m-d')) }}" required></label>
        <label class="md:col-span-3"><span class="label">Notes</span><textarea class="input min-h-28" name="notes">{{ old('notes', $quotation->notes) }}</textarea></label>
    </div>
@endif
