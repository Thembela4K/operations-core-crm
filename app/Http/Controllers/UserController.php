<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()
                ->with(['department', 'invitations' => fn ($query) => $query->latest()])
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(Request $request): View
    {
        $actor = $request->user();

        abort_unless($actor->canCreateTeamMembers(), 403);

        // Officers and heads only ever add juniors to their own department.
        $restricted = ! $actor->canManageUsers();

        return view('admin.users.create', [
            'user' => new User([
                'role' => User::ROLE_DEPARTMENT_USER,
                'level' => $restricted ? User::LEVEL_TRAINEE : null,
                'department_id' => $restricted ? $actor->department_id : null,
                'is_active' => true,
            ]),
            'departments' => $restricted
                ? Department::query()->whereKey($actor->department_id)->get()
                : Department::query()->where('is_active', true)->orderBy('name')->get(),
            'roles' => $actor->assignableRoles(),
            'levels' => $actor->assignableLevels(),
            'restricted' => $restricted,
        ]);
    }

    public function store(Request $request, UserInvitationService $invitations): RedirectResponse
    {
        $actor = $request->user();

        abort_unless($actor->canCreateTeamMembers(), 403);

        $data = $this->validatedForInvite($request);
        $this->guardAssignableRole($request, (string) $data['role']);

        // A departmental officer may only create trainees/interns in their own
        // department, regardless of what the form sends.
        if (! $actor->canManageUsers()) {
            if ($actor->department_id === null) {
                return back()->withInput()->withErrors([
                    'department_id' => 'Your account is not linked to a department, so you cannot add team members.',
                ]);
            }

            if (! in_array($data['level'] ?? null, User::JUNIOR_LEVELS, true)) {
                return back()->withInput()->withErrors([
                    'level' => 'You may only add trainees and interns.',
                ]);
            }

            $data['role'] = User::ROLE_DEPARTMENT_USER;
            $data['department_id'] = $actor->department_id;
        }

        $data['name'] = ($data['name'] ?? null) ?: $this->nameFromEmail($data['email']);
        $data['username'] = ($data['username'] ?? null) ?: null;
        $data['receives_submissions'] = $request->boolean('receives_submissions');
        $data['can_access_sppra'] = $request->boolean('can_access_sppra');

        $landing = $actor->canManageUsers() ? route('users.index') : route('team.create');

        // Invitation onboarding stays the default. An administrator may instead
        // hand over credentials directly, which is what makes local testing and
        // staff without a mailbox workable.
        if ($request->boolean('activate_now')) {
            $activation = $request->validate([
                'password' => ['required', 'string', 'min:8'],
            ]);

            User::query()->create($data + [
                'password' => $activation['password'],
                'is_active' => true,
                'invited_by' => $actor->id,
                'invited_at' => now(),
                'invitation_accepted_at' => now(),
            ])->forceFill(['email_verified_at' => now()])->save();

            return redirect($landing)
                ->with('success', 'User created and activated with the password you set.');
        }

        $data['password'] = Str::password(24);
        $data['is_active'] = false;

        $user = User::query()->create($data);
        $invitation = $invitations->invite($user, $actor);

        $message = $invitation->status === UserInvitation::STATUS_EMAIL_FAILED
            ? 'User invitation was created, but the email could not be sent. Check SMTP settings and resend.'
            : 'Invitation sent.';

        return redirect($landing)->with($invitation->status === UserInvitation::STATUS_EMAIL_FAILED ? 'warning' : 'success', $message);
    }

    public function show(User $user): RedirectResponse
    {
        return redirect()->route('users.edit', $user);
    }

    public function edit(Request $request, User $user): View
    {
        $this->guardManagedUser($request, $user);

        return view('admin.users.edit', [
            'user' => $user,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'roles' => $request->user()->assignableRoles(),
            'levels' => User::LEVELS,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->guardManagedUser($request, $user);

        $data = $this->validated($request, $user);
        $this->guardAssignableRole($request, (string) $data['role']);

        if (! $data['password']) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function resendInvitation(Request $request, User $user, UserInvitationService $invitations): RedirectResponse
    {
        $this->guardManagedUser($request, $user);

        if (! $user->email) {
            return back()->with('warning', 'This user has no email address to invite.');
        }

        if ($user->invitation_accepted_at) {
            return back()->with('warning', 'This user has already accepted an invitation.');
        }

        $invitation = $invitations->resend($user, $request->user());

        return back()->with(
            $invitation->status === UserInvitation::STATUS_EMAIL_FAILED ? 'warning' : 'success',
            $invitation->status === UserInvitation::STATUS_EMAIL_FAILED
                ? 'Invitation regenerated, but the email could not be sent. Check SMTP settings.'
                : 'Invitation resent.',
        );
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->guardManagedUser($request, $user);

        if (! $request->user()->canDeleteUsers()) {
            abort(403);
        }

        if ($request->user()->is($user)) {
            return back()->with('warning', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }

    /**
     * Only staff administrators may manage accounts, and only a super admin
     * may act on another super admin.
     */
    private function guardManagedUser(Request $request, ?User $target = null): void
    {
        $actor = $request->user();

        if (! $actor->canManageUsers()) {
            abort(403);
        }

        if ($target !== null && $target->isBusinessAdmin() && ! $actor->isBusinessAdmin()) {
            abort(403, 'Only a super admin may manage a super admin account.');
        }
    }

    /**
     * Blocks privilege escalation: an actor may only assign roles they are
     * allowed to hand out (business admins cannot create super admins).
     */
    private function guardAssignableRole(Request $request, string $role): void
    {
        if (! array_key_exists($role, $request->user()->assignableRoles())) {
            abort(403, 'You are not allowed to assign that role.');
        }
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required_without:email', 'nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required_without:username', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'employee_no' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'level' => ['nullable', Rule::in(User::LEVELS)],
            'department_id' => ['nullable', Rule::requiredIf($request->input('role') === User::ROLE_DEPARTMENT_USER), 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
            'receives_submissions' => ['nullable', 'boolean'],
            'can_access_sppra' => ['nullable', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'receives_submissions' => $request->boolean('receives_submissions'),
            'can_access_sppra' => $request->boolean('can_access_sppra'),
        ];
    }

    private function validatedForInvite(Request $request): array
    {
        return $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'employee_no' => ['nullable', 'string', 'max:40'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'level' => ['nullable', Rule::in(User::LEVELS)],
            'department_id' => ['nullable', Rule::requiredIf($request->input('role') === User::ROLE_DEPARTMENT_USER), 'exists:departments,id'],
            'receives_submissions' => ['nullable', 'boolean'],
            'can_access_sppra' => ['nullable', 'boolean'],
        ]);
    }

    private function nameFromEmail(string $email): string
    {
        return Str::of($email)
            ->before('@')
            ->replace(['.', '_', '-'], ' ')
            ->title()
            ->toString();
    }
}
