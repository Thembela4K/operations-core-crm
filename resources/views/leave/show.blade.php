@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Leave Application</h1>
            <p class="page-subtitle">{{ $leave->typeLabel() }} &middot; {{ $leave->user->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('leave.print', $leave) }}" target="_blank">Print Form</a>
            <a class="btn-secondary" href="{{ route('leave.pdf', $leave) }}" target="_blank">Download PDF</a>
            <a class="btn-secondary" href="{{ route('leave.index') }}">Back to Leave</a>
        </div>
    </div>

    <section class="panel mt-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="section-title">Details</h2>
            <span class="status-pill">{{ $leave->status }}</span>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card"><span>Employee</span><strong class="text-base">{{ $leave->user->name }}</strong><div class="metric-foot">{{ $leave->department?->name ?: 'No department' }}</div></div>
            <div class="metric-card"><span>Leave type</span><strong class="text-base">{{ $leave->typeLabel() }}</strong><div class="metric-foot">{{ $leave->deductsFromBalance() ? 'Deducts from annual balance' : 'Not deducted from leave' }}</div></div>
            <div class="metric-card"><span>Working days</span><strong>{{ rtrim(rtrim(number_format($leave->working_days, 1), '0'), '.') }}</strong><div class="metric-foot">Weekends and holidays excluded</div></div>
            <div class="metric-card"><span>Back at work</span><strong class="text-base">{{ $leave->back_at_work_on?->toFormattedDateString() ?: '-' }}</strong><div class="metric-foot">Expected return</div></div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <span class="label">First day</span>
                <p class="text-sm text-neutral-900">{{ $leave->from_date->toFormattedDateString() }}</p>
            </div>
            <div>
                <span class="label">Last day</span>
                <p class="text-sm text-neutral-900">{{ $leave->to_date->toFormattedDateString() }}</p>
            </div>
            <div>
                <span class="label">Applied on</span>
                <p class="text-sm text-neutral-900">{{ $leave->applied_on->toFormattedDateString() }}</p>
            </div>
            <div>
                <span class="label">Leave year</span>
                <p class="text-sm text-neutral-900">{{ $leave->leave_year }}</p>
            </div>
        </div>

        @if($leave->reason)
            <div class="mt-4">
                <span class="label">Reason / notes</span>
                <p class="text-sm text-neutral-900">{{ $leave->reason }}</p>
            </div>
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-3">
            @if($leave->attachment_path)
                <a class="link" href="{{ route('leave.attachment', $leave) }}">Download supporting document</a>
            @elseif($leave->certificate_attached)
                <span class="text-sm text-neutral-500">Certificate marked as attached on the original form.</span>
            @elseif($leave->requiresCertificate())
                <span class="text-sm text-amber-700">This leave type normally requires a supporting certificate.</span>
            @endif
        </div>
    </section>

    @if(! $leave->isPending())
        <section class="panel mt-6">
            <h2 class="section-title">Decision</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-3">
                <div>
                    <span class="label">Outcome</span>
                    <p class="text-sm text-neutral-900">{{ $leave->status }}</p>
                </div>
                <div>
                    <span class="label">Decided by</span>
                    <p class="text-sm text-neutral-900">{{ $leave->decider?->name ?: '-' }}</p>
                </div>
                <div>
                    <span class="label">Decided on</span>
                    <p class="text-sm text-neutral-900">{{ $leave->decided_at?->toFormattedDateString() ?: '-' }}</p>
                </div>
            </div>
            @if($leave->decision_note)
                <div class="mt-4">
                    <span class="label">Note</span>
                    <p class="text-sm text-neutral-900">{{ $leave->decision_note }}</p>
                </div>
            @endif
        </section>
    @endif

    @if($canDecide)
        <section class="panel mt-6 grid gap-4 md:grid-cols-2">
            <form method="POST" action="{{ route('leave.approve', $leave) }}" class="flex flex-col gap-3">
                @csrf
                <label>
                    <span class="label">Approval note (optional)</span>
                    <input class="input" name="decision_note" placeholder="Optional">
                </label>
                <button class="btn-primary" type="submit">Approve</button>
            </form>

            <form method="POST" action="{{ route('leave.reject', $leave) }}" class="flex flex-col gap-3">
                @csrf
                <label>
                    <span class="label">Rejection reason</span>
                    <input class="input" name="decision_note" placeholder="Reason for rejection">
                </label>
                <button class="btn-secondary" type="submit">Reject</button>
            </form>
        </section>
    @endif

    @if($canCancel)
        <section class="panel mt-6">
            <form method="POST" action="{{ route('leave.cancel', $leave) }}">
                @csrf
                <button class="btn-secondary" type="submit">Cancel Application</button>
            </form>
        </section>
    @endif
@endsection
