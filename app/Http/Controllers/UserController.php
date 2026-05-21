<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users.index', [
            'users' => User::query()
                ->with('department')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'user' => new User(['role' => User::ROLE_DEPARTMENT_USER, 'is_active' => true]),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'roles' => User::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        User::query()->create($this->validated($request));

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function show(User $user): RedirectResponse
    {
        return redirect()->route('users.edit', $user);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'roles' => User::ROLES,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if (! $data['password']) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return back()->with('warning', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'department_id' => ['nullable', 'exists:departments,id'],
            'is_active' => ['nullable', 'boolean'],
            'receives_submissions' => ['nullable', 'boolean'],
            'can_access_sppra' => ['nullable', 'boolean'],
        ]) + [
            'is_active' => $request->boolean('is_active'),
            'receives_submissions' => $request->boolean('receives_submissions'),
            'can_access_sppra' => $request->boolean('can_access_sppra'),
        ];
    }
}
