@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $project->exists ? 'Edit Project' : 'Add Project' }}</h1>
            <p class="page-subtitle">Follows the weekly Report &amp; Plan format: activity, status, progress, comment and plan dates.</p>
        </div>
        <a class="btn-secondary" href="{{ route('projects.index') }}">Back to Projects</a>
    </div>

    @if($errors->any())
        <div class="panel mt-6 border-red-200 bg-red-50">
            <strong class="text-sm text-red-800">Please correct the following:</strong>
            <ul class="mt-2 list-disc pl-5 text-sm text-red-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form class="panel mt-6 grid gap-4 md:grid-cols-2"
          method="POST"
          action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}">
        @csrf
        @if($project->exists)
            @method('PUT')
        @endif

        @if($users->isNotEmpty())
            <label class="md:col-span-2">
                <span class="label">Employee</span>
                <select class="input" name="user_id">
                    <option value="">Myself ({{ auth()->user()->name }})</option>
                    @foreach($users as $staff)
                        <option value="{{ $staff->id }}" @selected((int) old('user_id', $project->user_id) === $staff->id)>{{ $staff->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <label class="md:col-span-2">
            <span class="label">Activity / Project</span>
            <input class="input" name="title" value="{{ old('title', $project->title) }}" required>
        </label>

        <label>
            <span class="label">Status</span>
            <select class="input" name="status" required>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $project->status) === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </label>

        <label>
            <span class="label">Progress (%)</span>
            <input class="input" type="number" name="progress_percent" min="0" max="100" step="1"
                   value="{{ old('progress_percent', $project->progress_percent ?? 0) }}" required>
            <span class="mt-1 block text-xs text-neutral-500">Marking the project Completed sets this to 100 automatically.</span>
        </label>

        <label class="md:col-span-2">
            <span class="label">Comment / Notes</span>
            <textarea class="input" name="comment" rows="3" placeholder="What was done, what is outstanding">{{ old('comment', $project->comment) }}</textarea>
        </label>

        <label>
            <span class="label">Date</span>
            <input class="input" type="date" name="planned_date" value="{{ old('planned_date', $project->planned_date?->format('Y-m-d')) }}">
        </label>

        <label>
            <span class="label">By Whom</span>
            <input class="input" name="by_whom" value="{{ old('by_whom', $project->by_whom) }}" placeholder="e.g. Thembela and Samkelo">
        </label>

        <label>
            <span class="label">Time Frame</span>
            <input class="input" name="time_frame" value="{{ old('time_frame', $project->time_frame) }}" placeholder="e.g. 1 Day">
        </label>

        <label>
            <span class="label">Target Date</span>
            <input class="input" type="date" name="target_date" value="{{ old('target_date', $project->target_date?->format('Y-m-d')) }}">
        </label>

        <label>
            <span class="label">Started On</span>
            <input class="input" type="date" name="started_on" value="{{ old('started_on', $project->started_on?->format('Y-m-d')) }}">
        </label>

        <label class="md:col-span-2">
            <span class="label">Description</span>
            <textarea class="input" name="description" rows="2" placeholder="Optional background">{{ old('description', $project->description) }}</textarea>
        </label>

        <div class="md:col-span-2 flex justify-end gap-2">
            <a class="btn-secondary" href="{{ route('projects.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">{{ $project->exists ? 'Save Changes' : 'Add Project' }}</button>
        </div>
    </form>
@endsection
