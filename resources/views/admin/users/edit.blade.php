@extends('layouts.app')

@section('content')
    <h1 class="page-title">Edit User</h1>
    <form class="panel mt-6" method="POST" action="{{ route('users.update', $user) }}">
        @csrf
        @method('PUT')
        @include('admin.users.form')
        <div class="mt-6 flex gap-2">
            <button class="btn-primary" type="submit">Save Changes</button>
            <a class="btn-secondary" href="{{ route('users.index') }}">Cancel</a>
        </div>
    </form>
    <form method="POST" action="{{ route('users.destroy', $user) }}" class="mt-4" onsubmit="return confirm('Delete this user?')">
        @csrf
        @method('DELETE')
        <button class="btn-danger" type="submit">Delete User</button>
    </form>
@endsection
