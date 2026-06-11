@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Edit Task</h1>
            <p class="page-subtitle">{{ $task->task_number }}</p>
        </div>
        <a class="btn-secondary" href="{{ route('tasks.show', $task) }}">Back</a>
    </div>
    <form class="panel mt-6" method="POST" action="{{ route('tasks.update', $task) }}">
        @csrf
        @method('PUT')
        @include('tasks.form')
        <div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Save Changes</button></div>
    </form>
@endsection
