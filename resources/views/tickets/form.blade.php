<div class="grid gap-4 md:grid-cols-2">
    <label class="md:col-span-2">
        <span class="label">Title</span>
        <input class="input" name="title" value="{{ old('title', $ticket->title) }}" required placeholder="Short summary of the problem">
    </label>

    <label>
        <span class="label">Category</span>
        <select class="input" name="ticket_category_id">
            <option value="">Select category</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((int) old('ticket_category_id', $ticket->ticket_category_id) === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </label>

    <label>
        <span class="label">Priority</span>
        <select class="input" name="priority" required>
            @foreach($priorities as $priority)
                <option value="{{ $priority }}" @selected(old('priority', $ticket->priority) === $priority)>{{ $priority }}</option>
            @endforeach
        </select>
    </label>

    <label class="md:col-span-2">
        <span class="label">What is happening?</span>
        <textarea class="input min-h-32" name="description" required placeholder="Describe the issue in plain language">{{ old('description', $ticket->description) }}</textarea>
    </label>

    <p class="md:col-span-2 text-xs text-neutral-500">
        Requester and department are captured automatically from your account. Attach screenshots once the ticket is created.
    </p>
</div>
