<div class="grid gap-4 md:grid-cols-2">
    <label><span class="label">Client</span><select class="input" name="client_id" required>@foreach($clients as $client)<option value="{{ $client->id }}" @selected((int) old('client_id', $activity->client_id) === $client->id)>{{ $client->name }}</option>@endforeach</select></label>
    <label><span class="label">Responsible User</span><select class="input" name="responsible_user_id"><option value="">Unassigned</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((int) old('responsible_user_id', $activity->responsible_user_id) === $user->id)>{{ $user->name }}</option>@endforeach</select></label>
    <label><span class="label">Type</span><select class="input" name="type" required>@foreach($types as $type)<option value="{{ $type }}" @selected(old('type', $activity->type) === $type)>{{ $type }}</option>@endforeach</select></label>
    <label><span class="label">Status</span><select class="input" name="status" required>@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status', $activity->status) === $status)>{{ $status }}</option>@endforeach</select></label>
    <label class="md:col-span-2"><span class="label">Subject</span><input class="input" name="subject" value="{{ old('subject', $activity->subject) }}" required></label>
    <label><span class="label">Activity Date</span><input class="input" type="date" name="activity_date" value="{{ old('activity_date', optional($activity->activity_date)->format('Y-m-d')) }}"></label>
    <label><span class="label">Next Follow-up</span><input class="input" type="date" name="next_follow_up_date" value="{{ old('next_follow_up_date', optional($activity->next_follow_up_date)->format('Y-m-d')) }}"></label>
    <label class="md:col-span-2"><span class="label">Notes</span><textarea class="input min-h-28" name="notes">{{ old('notes', $activity->notes) }}</textarea></label>
</div>
