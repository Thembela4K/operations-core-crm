@extends('layouts.app')

@section('content')
    <h1 class="page-title">New Project</h1>
    <form method="POST" action="{{ route('projects.store') }}" class="panel mt-6">
        @csrf
        @include('projects.form')
        <div class="mt-6 flex gap-2">
            <button class="btn-primary" type="submit">Create Project</button>
            <a class="btn-secondary" href="{{ route('projects.index') }}">Cancel</a>
        </div>
    </form>
@endsection
