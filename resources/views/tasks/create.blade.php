@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">New Task</h1>
            <p class="page-subtitle">Assign work to a staff member or department.</p>
        </div>
        <a class="btn-secondary" href="{{ route('tasks.index') }}">Back</a>
    </div>
    <form class="panel mt-6" method="POST" action="{{ route('tasks.store') }}">
        @csrf
        @include('tasks.form')
        <div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Create Task</button></div>
    </form>
@endsection
