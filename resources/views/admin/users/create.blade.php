@extends('layouts.app')

@section('content')
    <h1 class="page-title">Add User</h1>
    <p class="page-subtitle">Assign role, department and position, then either send a setup link or set a password directly.</p>
    <form class="panel mt-6" method="POST" action="{{ route('users.store') }}">
        @csrf
        @include('admin.users.form')
        <div class="mt-6 flex gap-2">
            <button class="btn-primary" type="submit">Create User</button>
            <a class="btn-secondary" href="{{ route('users.index') }}">Cancel</a>
        </div>
    </form>
@endsection
