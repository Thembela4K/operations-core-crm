@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Apply for Leave</h1>
            <p class="page-subtitle">Complete the leave application. Annual leave draws down your working-day balance.</p>
        </div>
        <a class="btn-secondary" href="{{ route('leave.index') }}">Back to Leave</a>
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

    <section class="panel mt-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title">Balance</h2>
            <span class="text-sm text-neutral-500">
                {{ $balance['remaining'] }} remaining of {{ $balance['total'] }} working days for {{ $balance['year'] }}
                @if($balance['pending'] > 0)
                    &middot; {{ $balance['pending'] }} pending
                @endif
            </span>
        </div>
    </section>

    <form class="panel mt-6 grid gap-4 md:grid-cols-2" method="POST" action="{{ route('leave.store') }}" enctype="multipart/form-data">
        @csrf

        @if($canApplyForOthers && $users->isNotEmpty())
            <label class="md:col-span-2">
                <span class="label">Employee</span>
                <select class="input" name="user_id">
                    <option value="{{ auth()->id() }}">Myself ({{ auth()->user()->name }})</option>
                    @foreach($users as $staff)
                        @continue($staff->id === auth()->id())
                        <option value="{{ $staff->id }}" @selected((int) old('user_id') === $staff->id)>{{ $staff->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <label class="md:col-span-2">
            <span class="label">Leave type</span>
            <select class="input" name="leave_type" id="leave-type" required>
                @foreach($types as $value => $detail)
                    <option value="{{ $value }}" @selected(old('leave_type') === $value)>{{ $detail['label'] }}</option>
                @endforeach
            </select>
            <span class="mt-1 block text-xs text-neutral-500" id="leave-type-note"></span>
        </label>

        <label>
            <span class="label">From (first day)</span>
            <input class="input" type="date" name="from_date" value="{{ old('from_date') }}" required>
        </label>

        <label>
            <span class="label">To (last day)</span>
            <input class="input" type="date" name="to_date" value="{{ old('to_date') }}" required>
        </label>

        <label>
            <span class="label">Back at work on</span>
            <input class="input" type="date" name="back_at_work_on" value="{{ old('back_at_work_on') }}">
            <span class="mt-1 block text-xs text-neutral-500">Leave blank to use the next working day automatically.</span>
        </label>

        <div class="flex items-end">
            <label class="flex items-center gap-2 text-sm text-neutral-700">
                <input type="checkbox" name="certificate_attached" value="1" @checked(old('certificate_attached'))>
                Certificate / proof attached
            </label>
        </div>

        <label class="md:col-span-2">
            <span class="label">Supporting document (optional)</span>
            <input class="input" type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
            <span class="mt-1 block text-xs text-neutral-500">Medical certificate, exam notice, or proof of reason. Maximum 5 MB.</span>
        </label>

        <label class="md:col-span-2">
            <span class="label">Reason / notes</span>
            <textarea class="input" name="reason" rows="3" placeholder="Optional context for the approver">{{ old('reason') }}</textarea>
        </label>

        <div class="md:col-span-2 flex justify-end gap-2">
            <a class="btn-secondary" href="{{ route('leave.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">Submit Application</button>
        </div>
    </form>

    <script>
        (function () {
            const notes = @json(collect($types)->map(fn ($detail) => $detail['note']));
            const select = document.getElementById('leave-type');
            const target = document.getElementById('leave-type-note');

            function refresh() {
                target.textContent = notes[select.value] || '';
            }

            select.addEventListener('change', refresh);
            refresh();
        })();
    </script>
@endsection
