<div class="grid gap-4 md:grid-cols-2">
    <label>
        <span class="label">Name</span>
        <input class="input" name="name" value="{{ old('name', $department->name) }}" required>
    </label>
    <label>
        <span class="label">Email</span>
        <input class="input" type="email" name="email" value="{{ old('email', $department->email) }}">
    </label>
    <label class="mt-7 flex items-center gap-2 text-sm text-zinc-700">
        <input class="rounded border-zinc-300" type="checkbox" name="is_active" value="1" @checked(old('is_active', $department->is_active))>
        Active
    </label>
</div>
