@extends('layouts.app')

@section('content')
    <h1 class="page-title">Edit Department</h1>
    <form class="panel mt-6" method="POST" action="{{ route('departments.update', $department) }}">
        @csrf
        @method('PUT')
        @include('admin.departments.form')
        <div class="mt-6 flex gap-2">
            <button class="btn-primary" type="submit">Save Changes</button>
            <a class="btn-secondary" href="{{ route('departments.index') }}">Cancel</a>
        </div>
    </form>
    <form method="POST" action="{{ route('departments.destroy', $department) }}" class="mt-4" onsubmit="return confirm('Delete this department?')">
        @csrf
        @method('DELETE')
        <button class="btn-danger" type="submit">Delete Department</button>
    </form>
@endsection
