@php
    $relatedValue = old('related_record');
    if (! $relatedValue && $task->taskable_type && $task->taskable_id) {
        $map = [
            \App\Models\Client::class => 'client',
            \App\Models\TenderProposal::class => 'tender',
            \App\Models\Quotation::class => 'quotation',
            \App\Models\SalesQuotation::class => 'sales_quotation',
            \App\Models\Invoice::class => 'invoice',
            \App\Models\Requisition::class => 'requisition',
            \App\Models\Expense::class => 'expense',
        ];
        $relatedValue = ($map[$task->taskable_type] ?? '').':'.$task->taskable_id;
    }
@endphp

<div class="grid gap-4 lg:grid-cols-3">
    <label><span class="label">Task Number</span><input class="input" name="task_number" value="{{ old('task_number', $task->task_number) }}"></label>
    <label class="lg:col-span-2"><span class="label">Title</span><input class="input" name="title" value="{{ old('title', $task->title) }}" required></label>
    <label>
        <span class="label">Department</span>
        <select class="input" name="department_id">
            <option value="">No department</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((int) old('department_id', $task->department_id) === $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Assignee</span>
        <select class="input" name="assigned_to">
            <option value="">Unassigned</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" @selected((int) old('assigned_to', $task->assigned_to) === $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
    </label>
    <label><span class="label">Due Date</span><input class="input" type="date" name="due_date" value="{{ old('due_date', optional($task->due_date)->format('Y-m-d')) }}"></label>
    <label>
        <span class="label">Status</span>
        <select class="input" name="status" required>@foreach($statuses as $status)<option value="{{ $status }}" @selected(old('status', $task->status) === $status)>{{ $status }}</option>@endforeach</select>
    </label>
    <label>
        <span class="label">Priority</span>
        <select class="input" name="priority" required>@foreach($priorities as $priority)<option value="{{ $priority }}" @selected(old('priority', $task->priority) === $priority)>{{ $priority }}</option>@endforeach</select>
    </label>
    <label>
        <span class="label">Linked Record</span>
        <select class="input" name="related_record">
            <option value="">Standalone task</option>
            @foreach($relatedRecords as $group => $records)
                @if($records)
                    <optgroup label="{{ $group }}">
                        @foreach($records as $record)
                            <option value="{{ $record['value'] }}" @selected($relatedValue === $record['value'])>{{ $record['label'] }}</option>
                        @endforeach
                    </optgroup>
                @endif
            @endforeach
        </select>
    </label>
    <label class="lg:col-span-3"><span class="label">Description</span><textarea class="input min-h-28" name="description">{{ old('description', $task->description) }}</textarea></label>
</div>
