@extends('layouts.app')

@section('content')
    <h1 class="page-title">Edit Project</h1>
    <form method="POST" action="{{ route('projects.update', $project) }}" class="panel mt-6">
        @csrf
        @method('PUT')
        @include('projects.form')
        <div class="mt-6 flex gap-2">
            <button class="btn-primary" type="submit">Save Changes</button>
            <a class="btn-secondary" href="{{ route('projects.show', $project) }}">Cancel</a>
        </div>
    </form>
@endsection
