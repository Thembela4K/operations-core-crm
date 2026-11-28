@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Staff Information</h1>
            <p class="page-subtitle">Personal, contact and banking details maintained by employees, the Business Admin and Directors.</p>
        </div>
        <a class="btn-secondary" href="{{ route('profile.information') }}">My Information</a>
    </div>

    <form class="panel mt-6 flex flex-wrap items-end gap-3" method="GET">
        <label class="flex-1 min-w-52">
            <span class="label">Search</span>
            <input class="input" name="search" value="{{ request('search') }}" placeholder="Name, username or email">
        </label>
        <label class="w-56">
            <span class="label">Department</span>
            <select class="input" name="department_id">
                <option value="">All departments</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected((int) request('department_id') === $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <section class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Phone</th>
                    <th>Personal Email</th>
                    <th>Bank</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $employee)
                    <tr>
                        <td>
                            <strong>{{ $employee->name }}</strong><br>
                            <span class="text-xs text-neutral-500">{{ $employee->email }}</span>
                        </td>
                        <td>{{ $employee->department?->name ?? '—' }}</td>
                        <td>{{ $employee->profile?->phone ?? '—' }}</td>
                        <td>{{ $employee->profile?->personal_email ?? '—' }}</td>
                        <td>{{ $employee->profile?->bank_name ?? '—' }}</td>
                        <td class="text-right"><a class="link" href="{{ route('user-information.show', $employee) }}">{{ $employee->profile ? 'Open' : 'Add details' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><p class="empty">No staff found.</p></td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $users->links() }}</div>
    </section>
@endsection
