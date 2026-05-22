@php($staffRows = collect(old('staff_members', $department->relationLoaded('staffMembers') ? $department->staffMembers->map(fn ($staff) => ['name' => $staff->name, 'email' => $staff->email, 'is_active' => $staff->is_active])->all() : [])))
@php($rowCount = max(6, $staffRows->count()))

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

<div class="mt-8">
    <h2 class="section-title">Department Staff</h2>
    <p class="page-subtitle">Staff names are used for assignment routing. Leave email blank when the department mailbox should receive notifications.</p>
    <div class="mt-4 space-y-3">
        @for($i = 0; $i < $rowCount; $i++)
            @php($row = $staffRows->get($i, ['is_active' => true]))
            <div class="grid gap-3 md:grid-cols-[1fr_1fr_120px]">
                <input class="input" name="staff_members[{{ $i }}][name]" placeholder="Staff name" value="{{ $row['name'] ?? '' }}">
                <input class="input" type="email" name="staff_members[{{ $i }}][email]" placeholder="Optional direct email" value="{{ $row['email'] ?? '' }}">
                <label class="flex items-center gap-2 text-sm text-zinc-700">
                    <input class="rounded border-zinc-300" type="checkbox" name="staff_members[{{ $i }}][is_active]" value="1" @checked($row['is_active'] ?? true)>
                    Active
                </label>
            </div>
        @endfor
    </div>
</div>
