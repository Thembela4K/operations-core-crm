@extends('layouts.app')

@section('content')
    <h1 class="page-title">New Department</h1>
    <form class="panel mt-6" method="POST" action="{{ route('departments.store') }}">
        @csrf
        @include('admin.departments.form')
        <div class="mt-6 flex gap-2">
            <button class="btn-primary" type="submit">Create Department</button>
            <a class="btn-secondary" href="{{ route('departments.index') }}">Cancel</a>
        </div>
    </form>
@endsection
