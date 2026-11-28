@extends('layouts.app')

@section('content')
    <h1 class="page-title">Attendance Types</h1>
    <p class="page-subtitle">Where and how staff work. Exactly one active type is the default.</p>

    <section class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr><th>Type</th><th>Description</th><th>Default</th><th>Active</th><th></th></tr>
            </thead>
            <tbody>
                @foreach($types as $type)
                    @php($formId = 'attendance-type-'.$type->id)
                    <tr>
                        <td>
                            <form id="{{ $formId }}" method="POST" action="{{ route('attendance-types.update', $type) }}">
                                @csrf
                                @method('PATCH')
                            </form>
                            <input class="input" name="name" form="{{ $formId }}" value="{{ $type->name }}" required>
                        </td>
                        <td><input class="input" name="description" form="{{ $formId }}" value="{{ $type->description }}"></td>
                        <td class="text-center"><input type="checkbox" name="is_default" form="{{ $formId }}" value="1" @checked($type->is_default)></td>
                        <td class="text-center"><input type="checkbox" name="is_active" form="{{ $formId }}" value="1" @checked($type->is_active)></td>
                        <td><button class="btn-secondary" type="submit" form="{{ $formId }}">Save</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="panel mt-6">
        <h2 class="section-title">Add a Type</h2>
        <form class="mt-4 grid gap-3 md:grid-cols-[1fr_2fr_auto]" method="POST" action="{{ route('attendance-types.store') }}">
            @csrf
            <label><span class="label">Name</span><input class="input" name="name" required placeholder="e.g. Client Site"></label>
            <label><span class="label">Description</span><input class="input" name="description" placeholder="When this type applies"></label>
            <div class="flex items-end"><button class="btn-primary" type="submit">Add Type</button></div>
        </form>
    </section>

    <section class="panel mt-6 overflow-x-auto">
        <h2 class="section-title">Working Week</h2>
        <table class="data-table mt-4">
            <thead><tr><th>Day</th><th>Start</th><th>End</th><th>Working Day</th></tr></thead>
            <tbody>
                @foreach($schedules as $schedule)
                    <tr>
                        <td>{{ $schedule->name }}</td>
                        <td>{{ $schedule->start_time ? substr($schedule->start_time, 0, 5) : '—' }}</td>
                        <td>{{ $schedule->end_time ? substr($schedule->end_time, 0, 5) : '—' }}</td>
                        <td>{{ $schedule->is_working_day ? 'Yes' : 'No' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="mt-3 text-xs text-neutral-500">Clock-out reminders fire ten minutes before the end time.</p>
    </section>
@endsection
