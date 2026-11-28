@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Weekly Report &amp; Plan</h1>
            <p class="page-subtitle">{{ $start->format('d M Y') }} – {{ $end->format('d M Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('week-reports.edit', ['week' => $previousWeek]) }}">&larr; Previous week</a>
            <a class="btn-secondary" href="{{ route('week-reports.edit', ['week' => $nextWeek]) }}">Next week &rarr;</a>
            <a class="btn-secondary" href="{{ route('week-reports.index') }}">All reports</a>
        </div>
    </div>

    <form class="mt-6 space-y-6" method="POST" action="{{ route('week-reports.save') }}" data-week-form>
        @csrf
        <input type="hidden" name="week_start" value="{{ $start->toDateString() }}">

        <section class="panel">
            <h2 class="section-title">Last Week — How Far I Got</h2>
            <p class="page-subtitle">{{ $lastWeekStart->format('d M') }} – {{ $lastWeekEnd->format('d M Y') }}</p>

            <div class="mt-4 overflow-x-auto">
                <table class="data-table" data-rows="last_week">
                    <thead><tr><th>Task</th><th class="w-28">Progress %</th><th>Notes</th><th class="w-16"></th></tr></thead>
                    <tbody>
                        @foreach($lastWeekRows as $index => $row)
                            <tr>
                                <td><input class="input" name="last_week[{{ $index }}][title]" value="{{ $row['title'] }}" required>
                                    <input type="hidden" name="last_week[{{ $index }}][task_id]" value="{{ $row['task_id'] }}"></td>
                                <td><input class="input" type="number" min="0" max="100" name="last_week[{{ $index }}][progress_percent]" value="{{ $row['progress_percent'] }}"></td>
                                <td><input class="input" name="last_week[{{ $index }}][notes]" value="{{ $row['notes'] }}"></td>
                                <td><button class="btn-secondary px-2 py-1" type="button" data-remove-row>Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button class="btn-secondary mt-3" type="button" data-add-row="last_week">Add Task</button>

            <label class="mt-4 block">
                <span class="label">Summary of last week</span>
                <textarea class="input min-h-24" name="last_week_progress" placeholder="Anything worth explaining beyond the percentages">{{ $report->last_week_progress }}</textarea>
            </label>
        </section>

        <section class="panel">
            <h2 class="section-title">This Week — What I Am Doing</h2>
            <p class="page-subtitle">Unfinished work from last week is carried over automatically.</p>

            <div class="mt-4 overflow-x-auto">
                <table class="data-table" data-rows="this_week">
                    <thead><tr><th>Task</th><th class="w-28">Progress %</th><th class="w-28">Carried over</th><th>Notes</th><th class="w-16"></th></tr></thead>
                    <tbody>
                        @foreach($thisWeekRows as $index => $row)
                            <tr>
                                <td><input class="input" name="this_week[{{ $index }}][title]" value="{{ $row['title'] }}" required>
                                    <input type="hidden" name="this_week[{{ $index }}][task_id]" value="{{ $row['task_id'] }}"></td>
                                <td><input class="input" type="number" min="0" max="100" name="this_week[{{ $index }}][progress_percent]" value="{{ $row['progress_percent'] }}"></td>
                                <td class="text-center"><input type="hidden" name="this_week[{{ $index }}][carried_over]" value="0"><input type="checkbox" name="this_week[{{ $index }}][carried_over]" value="1" @checked($row['carried_over'])></td>
                                <td><input class="input" name="this_week[{{ $index }}][notes]" value="{{ $row['notes'] }}"></td>
                                <td><button class="btn-secondary px-2 py-1" type="button" data-remove-row>Remove</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button class="btn-secondary mt-3" type="button" data-add-row="this_week">Add Task</button>

            <label class="mt-4 block">
                <span class="label">Plan for this week</span>
                <textarea class="input min-h-24" name="this_week_plan" placeholder="What you intend to finish this week">{{ $report->this_week_plan }}</textarea>
            </label>
        </section>

        <section class="panel">
            <h2 class="section-title">Contributors</h2>
            <p class="page-subtitle">Employees from the staff list, or outsourced people you worked with.</p>

            <div class="mt-4 space-y-3" data-rows="contributors">
                @php($contributorRows = $report->exists ? $report->contributors : collect())
                @foreach($contributorRows as $index => $contributor)
                    <div class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_1fr_auto]">
                        <select class="input" name="contributors[{{ $index }}][user_id]">
                            <option value="">Outsourced / not listed</option>
                            @foreach($staff as $employee)
                                <option value="{{ $employee->id }}" @selected($contributor->user_id === $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                        <input class="input" name="contributors[{{ $index }}][name]" value="{{ $contributor->name }}" placeholder="Name (outsourced)">
                        <input class="input" name="contributors[{{ $index }}][organisation]" value="{{ $contributor->organisation }}" placeholder="Organisation">
                        <input class="input" name="contributors[{{ $index }}][contribution]" value="{{ $contributor->contribution }}" placeholder="What they did">
                        <button class="btn-secondary px-2 py-1" type="button" data-remove-row>Remove</button>
                    </div>
                @endforeach
            </div>

            <button class="btn-secondary mt-3" type="button" data-add-contributor>Add Contributor</button>
        </section>

        <div class="flex flex-wrap justify-end gap-3">
            <a class="btn-secondary" href="{{ route('week-reports.index') }}">Cancel</a>
            <button class="btn-secondary" type="submit" name="status" value="Draft">Save Draft</button>
            <button class="btn-primary" type="submit" name="status" value="Submitted">Submit Report</button>
        </div>
    </form>

    <script>
        (function () {
            var counters = {
                last_week: {{ count($lastWeekRows) }},
                this_week: {{ count($thisWeekRows) }},
                contributors: {{ $report->exists ? $report->contributors->count() : 0 }}
            };

            function addTaskRow(section) {
                var table = document.querySelector('[data-rows="' + section + '"] tbody');
                if (!table) return;
                var index = counters[section]++;
                var carried = section === 'this_week'
                    ? '<td class="text-center"><input type="hidden" name="' + section + '[' + index + '][carried_over]" value="0"><input type="checkbox" name="' + section + '[' + index + '][carried_over]" value="1"></td>'
                    : '';

                var row = document.createElement('tr');
                row.innerHTML =
                    '<td><input class="input" name="' + section + '[' + index + '][title]" placeholder="Task"><input type="hidden" name="' + section + '[' + index + '][task_id]" value=""></td>' +
                    '<td><input class="input" type="number" min="0" max="100" name="' + section + '[' + index + '][progress_percent]" value="0"></td>' +
                    carried +
                    '<td><input class="input" name="' + section + '[' + index + '][notes]"></td>' +
                    '<td><button class="btn-secondary px-2 py-1" type="button" data-remove-row>Remove</button></td>';
                table.appendChild(row);
            }

            document.querySelectorAll('[data-add-row]').forEach(function (button) {
                button.addEventListener('click', function () {
                    addTaskRow(button.getAttribute('data-add-row'));
                });
            });

            var addContributor = document.querySelector('[data-add-contributor]');
            if (addContributor) {
                addContributor.addEventListener('click', function () {
                    var wrap = document.querySelector('[data-rows="contributors"]');
                    var index = counters.contributors++;
                    var options = @json($staff->map(fn ($employee) => ['id' => $employee->id, 'name' => $employee->name])->values());
                    var select = '<select class="input" name="contributors[' + index + '][user_id]"><option value="">Outsourced / not listed</option>' +
                        options.map(function (o) { return '<option value="' + o.id + '">' + o.name + '</option>'; }).join('') + '</select>';

                    var row = document.createElement('div');
                    row.className = 'grid gap-3 md:grid-cols-[1fr_1fr_1fr_1fr_auto]';
                    row.innerHTML = select +
                        '<input class="input" name="contributors[' + index + '][name]" placeholder="Name (outsourced)">' +
                        '<input class="input" name="contributors[' + index + '][organisation]" placeholder="Organisation">' +
                        '<input class="input" name="contributors[' + index + '][contribution]" placeholder="What they did">' +
                        '<button class="btn-secondary px-2 py-1" type="button" data-remove-row>Remove</button>';
                    wrap.appendChild(row);
                });
            }

            document.addEventListener('click', function (event) {
                var remove = event.target.closest('[data-remove-row]');
                if (!remove) return;
                var row = remove.closest('tr') || remove.closest('div.grid');
                if (row) row.remove();
            });
        })();
    </script>
@endsection
