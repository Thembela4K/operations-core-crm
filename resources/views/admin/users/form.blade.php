<div class="grid gap-4 md:grid-cols-2">
    <label><span class="label">Name</span><input class="input" name="name" value="{{ old('name', $user->name) }}" @required($user->exists) placeholder="{{ $user->exists ? '' : 'Optional - user can confirm on invitation' }}"></label>
    <label><span class="label">Username</span><input class="input" name="username" value="{{ old('username', $user->username) }}" placeholder="name.surname"></label>

    <label>
        <span class="label">Employee Number</span>
        <input class="input" name="employee_no" value="{{ old('employee_no', $user->employee_no) }}" placeholder="e.g. DAT-014">
    </label>
    <label>
        <span class="label">Job Title / Position</span>
        <input class="input" name="job_title" value="{{ old('job_title', $user->job_title) }}" placeholder="e.g. MIS Trainee">
        <span class="mt-1 block text-xs text-neutral-500">Position held. Permissions come from the role below.</span>
    </label>

    <label><span class="label">Email</span><input class="input" type="email" name="email" value="{{ old('email', $user->email) }}" placeholder="name@example.com" @required(! $user->exists)></label>

    @if($user->exists)
        <label><span class="label">Password</span><input class="input" type="password" name="password" placeholder="Leave blank to keep current password"></label>
    @else
        <label>
            <span class="label">Password (optional)</span>
            <input class="input" type="password" name="password" autocomplete="new-password" placeholder="Leave blank to send an invitation">
            <span class="mt-1 block text-xs text-neutral-500">Required only when activating immediately.</span>
        </label>
    @endif

    <label>
        <span class="label">Role</span>
        <select class="input" name="role" required>
            @foreach($roles as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>
        <span class="label">Department</span>
        <select class="input" name="department_id">
            <option value="">No department</option>
            @foreach($departments as $department)
                <option value="{{ $department->id }}" @selected((int) old('department_id', $user->department_id) === $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
    </label>

    @if(! $user->exists)
        <div class="md:col-span-2 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
            <label class="flex items-center gap-2">
                <input class="rounded border-sky-300" type="checkbox" name="activate_now" value="1" @checked(old('activate_now'))>
                <strong>Activate immediately with the password above</strong>
            </label>
            <p class="mt-1 text-xs text-sky-900">
                Leave unticked to send the user an invitation email so they set their own password.
                Ticking this skips the email &mdash; useful for testing or for staff without a mailbox.
            </p>
        </div>
    @endif

    @if($user->exists)
        <div class="flex items-center gap-2">
            <label class="mt-7 flex items-center gap-2 text-sm text-zinc-700">
                <input class="rounded border-zinc-300" type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                Active
            </label>
        </div>
    @endif
</div>
