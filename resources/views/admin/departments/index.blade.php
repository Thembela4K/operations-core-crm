@extends('layouts.app')

@section('content')
    <div class="flex items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Departments</h1>
            <p class="page-subtitle">Manage assignment departments.</p>
        </div>
        <a class="btn-primary" href="{{ route('departments.create') }}">New Department</a>
    </div>

    <section class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Users</th><th>Assignments</th><th></th></tr></thead>
            <tbody>
                @foreach($departments as $department)
                    <tr>
                        <td>{{ $department->name }}</td>
                        <td>{{ $department->email ?: '-' }}</td>
                        <td>{{ $department->is_active ? 'Active' : 'Inactive' }}</td>
                        <td>{{ $department->users_count }}</td>
                        <td>{{ $department->assignments_count }}</td>
                        <td class="text-right"><a class="link" href="{{ route('departments.edit', $department) }}">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endsection
