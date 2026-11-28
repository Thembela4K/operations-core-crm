@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">New Assignment</h1>
            <p class="page-subtitle">Assign work or a meeting to yourself or someone else. It lands in their Week Plan.</p>
        </div>
        <a class="btn-secondary" href="{{ route('assignments.index') }}">Back to assignments</a>
    </div>

    <form class="panel mt-6 space-y-5" method="POST" action="{{ route('assignments.store') }}">
        @csrf

        <div class="grid gap-4 md:grid-cols-3">
            <label>
                <span class="label">Kind</span>
                <select class="input" name="kind" data-kind>
                    @foreach($kinds as $kind)
                        <option value="{{ $kind }}" @selected(old('kind', 'work') === $kind)>{{ ucfirst($kind) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="md:col-span-2">
                <span class="label">Title</span>
                <input class="input" name="title" value="{{ old('title') }}" required placeholder="e.g. Prepare the tender response / Client kick-off meeting">
            </label>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <label>
                <span class="label">Assign To</span>
                <select class="input" name="assigned_user_id">
                    <option value="">Choose a person…</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}" @selected((int) old('assigned_user_id') === $user->id)>{{ $user->name }}@if($user->department) — {{ $user->department->name }}@endif</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span class="label">Department (when no person)</span>
                <select class="input" name="department_id">
                    <option value="">Same as assignee</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" @selected((int) old('department_id') === $department->id)>{{ $department->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="flex items-end gap-2 pb-2 text-sm">
                <input type="checkbox" name="assign_to_me" value="1" @checked(old('assign_to_me'))> Assign to me
            </label>
        </div>

        <div class="grid gap-4 md:grid-cols-3" data-work-fields>
            <label><span class="label">Due date</span><input class="input" type="date" name="due_date" value="{{ old('due_date') }}"></label>
            <label class="md:col-span-2">
                <span class="label">Link to a record (optional)</span>
                <select class="input" name="target">
                    <option value="">No linked record</option>
                    <optgroup label="Tender Proposals">
                        @foreach($tenderProposals as $tender)
                            <option value="tender_proposal:{{ $tender->id }}" @selected(old('target') === "tender_proposal:{$tender->id}")>{{ $tender->tender_reference }} — {{ $tender->title }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Quotation Requests">
                        @foreach($quotations as $quotation)
                            <option value="quotation:{{ $quotation->id }}" @selected(old('target') === "quotation:{$quotation->id}")>{{ $quotation->quotation_code }} — {{ $quotation->opportunity }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </label>
        </div>

        <div class="grid gap-4 md:grid-cols-3" data-meeting-fields>
            <label><span class="label">Starts</span><input class="input" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}"></label>
            <label><span class="label">Ends</span><input class="input" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}"></label>
            <label><span class="label">Location / link</span><input class="input" name="location" value="{{ old('location') }}" placeholder="Boardroom / Teams link"></label>
        </div>

        <label class="block">
            <span class="label">Instructions</span>
            <textarea class="input min-h-24" name="instructions">{{ old('instructions') }}</textarea>
        </label>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="send_email" value="1" @checked(old('send_email'))> Email the notice to the assignee
        </label>

        <div class="flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('assignments.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">Save Assignment</button>
        </div>
    </form>

    <script>
        (function () {
            var kind = document.querySelector('[data-kind]');
            var work = document.querySelector('[data-work-fields]');
            var meeting = document.querySelector('[data-meeting-fields]');

            function toggle() {
                var isMeeting = kind.value === 'meeting';
                meeting.style.display = isMeeting ? '' : 'none';
                work.style.display = isMeeting ? 'none' : '';
            }

            kind.addEventListener('change', toggle);
            toggle();
        })();
    </script>
@endsection
