@extends('layouts.app')

@section('content')
    <div class="flex items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Users</h1>
            <p class="page-subtitle">Create staff accounts, roles, review access, and department-level permissions.</p>
        </div>
        <a class="btn-primary" href="{{ route('users.create') }}">New User</a>
    </div>

    <section class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Login</th><th>Role</th><th>Department</th><th>Reviewer</th><th>SPPRA</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->username ?: '-' }}<br><span class="text-xs text-neutral-500">{{ $user->email ?: 'No email' }}</span></td>
                        <td>{{ \App\Models\User::ROLES[$user->role] ?? $user->role }}</td>
                        <td>{{ $user->department?->name ?? '-' }}</td>
                        <td>{{ $user->canReviewSubmissions() ? 'Yes' : 'No' }}</td>
                        <td>{{ $user->canAccessSppra() ? 'Yes' : 'No' }}</td>
                        <td>{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="text-right"><a class="link" href="{{ route('users.edit', $user) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $users->links() }}</div>
    </section>
@endsection
