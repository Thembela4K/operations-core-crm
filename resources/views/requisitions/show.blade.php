@extends('layouts.app')

@section('content')
    @php
        $user = auth()->user();
        $ownDepartment = $requisition->department_id === $user->department_id || $requisition->requested_by === $user->id;
        $canEdit = $requisition->isEditable() && ($ownDepartment || $user->canReleaseRequisitionFunds());
        $canReview = ($user->canApproveRequisitions() || $user->canReleaseRequisitionFunds()) && in_array($requisition->status, [\App\Models\Requisition::STATUS_SUBMITTED, \App\Models\Requisition::STATUS_IN_REVIEW], true);
        $canApprove = $user->canApproveRequisitions() && in_array($requisition->status, [\App\Models\Requisition::STATUS_SUBMITTED, \App\Models\Requisition::STATUS_IN_REVIEW], true);
        $canReleaseFunds = $user->canReleaseRequisitionFunds() && $requisition->status === \App\Models\Requisition::STATUS_APPROVED;
        $canCancel = ! in_array($requisition->status, [\App\Models\Requisition::STATUS_APPROVED, \App\Models\Requisition::STATUS_FUNDS_RELEASED], true) && ($ownDepartment || $user->canReleaseRequisitionFunds());
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="page-title">{{ $requisition->title }}</h1>
            <p class="page-subtitle">{{ $requisition->requisition_number }} | {{ $requisition->department?->name ?? 'Company-wide' }} | {{ $requisition->status }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('requisitions.index') }}">Back</a>
            <a class="btn-secondary" href="{{ route('requisitions.print', $requisition) }}" target="_blank">Print</a>
            <a class="btn-secondary" href="{{ route('requisitions.pdf', $requisition) }}">PDF</a>
            @if($canEdit)
                <a class="btn-secondary" href="{{ route('requisitions.edit', $requisition) }}">Edit</a>
                @if($requisition->status !== \App\Models\Requisition::STATUS_SUBMITTED)
                    <form method="POST" action="{{ route('requisitions.submit', $requisition) }}">@csrf<button class="btn-primary" type="submit">Submit</button></form>
                @endif
            @endif
            @if($canCancel)
                <form method="POST" action="{{ route('requisitions.cancel', $requisition) }}">@csrf<button class="btn-secondary" type="submit">Cancel</button></form>
            @endif
            @if($user->isSuperAdmin())
                <form method="POST" action="{{ route('requisitions.destroy', $requisition) }}" onsubmit="return confirm('Delete this requisition?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn-danger" type="submit">Delete</button>
                </form>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_0.85fr]">
        <section class="panel">
            <h2 class="section-title">Requisition Summary</h2>
            <dl class="mt-4 grid gap-4 md:grid-cols-2">
                <div><dt class="label">Category</dt><dd>{{ $requisition->category }}</dd></div>
                <div><dt class="label">Priority</dt><dd>{{ $requisition->priority }}</dd></div>
                <div><dt class="label">Needed By</dt><dd>{{ $requisition->needed_by?->toFormattedDateString() ?? 'Not set' }}</dd></div>
                <div><dt class="label">To</dt><dd>{{ $requisition->addressed_to }}</dd></div>
                <div><dt class="label">Supplier</dt><dd>{{ $requisition->supplier?->name ?: 'Not selected' }}</dd></div>
                <div><dt class="label">Total Bank</dt><dd>E{{ number_format((float) $requisition->bank_total, 2) }}</dd></div>
                <div><dt class="label">Total Cash</dt><dd>E{{ number_format((float) $requisition->cash_total, 2) }}</dd></div>
                <div><dt class="label">Total Other</dt><dd>E{{ number_format((float) $requisition->other_total, 2) }}</dd></div>
                <div><dt class="label">Grand Total</dt><dd>E{{ number_format((float) $requisition->estimated_total, 2) }}</dd></div>
                <div><dt class="label">Prepared By</dt><dd>{{ $requisition->requester?->name ?? 'Unknown' }}</dd></div>
                <div><dt class="label">Approved By</dt><dd>{{ $requisition->approver?->name ?? 'Not approved' }}</dd></div>
                <div><dt class="label">Funds Released By</dt><dd>{{ $requisition->releaser?->name ?? 'Not released' }}</dd></div>
            </dl>
            @if(filled($requisition->purpose))
                <div class="mt-5">
                    <h3 class="label">Purpose</h3>
                    <p class="text-sm leading-6 text-neutral-700">{{ $requisition->purpose }}</p>
                </div>
            @endif
            @if(filled($requisition->notes))
                <div class="mt-5">
                    <h3 class="label">Notes</h3>
                    <p class="text-sm leading-6 text-neutral-700">{{ $requisition->notes }}</p>
                </div>
            @endif
            @if(filled($requisition->decision_notes))
                <div class="mt-5 rounded-md border border-neutral-200 bg-neutral-50 p-4">
                    <h3 class="label">Decision Notes</h3>
                    <p class="text-sm leading-6 text-neutral-700">{{ $requisition->decision_notes }}</p>
                </div>
            @endif
            @if(filled($requisition->release_notes))
                <div class="mt-5 rounded-md border border-neutral-200 bg-neutral-50 p-4">
                    <h3 class="label">Funds Release Notes</h3>
                    <p class="text-sm leading-6 text-neutral-700">{{ $requisition->release_notes }}</p>
                </div>
            @endif
        </section>

        <section class="panel">
            <h2 class="section-title">Workflow Tracking</h2>
            <div class="workflow-grid">
                <div><span>Created</span><strong>{{ $requisition->created_at?->toDayDateTimeString() }}</strong></div>
                <div><span>Submitted</span><strong>{{ $requisition->submitted_at?->toDayDateTimeString() ?? 'Pending' }}</strong></div>
                <div><span>In Review</span><strong>{{ $requisition->reviewed_at?->toDayDateTimeString() ?? 'Pending' }}</strong></div>
                <div><span>Approved</span><strong>{{ $requisition->approved_at?->toDayDateTimeString() ?? 'Pending' }}</strong></div>
                <div><span>Rejected</span><strong>{{ $requisition->rejected_at?->toDayDateTimeString() ?? 'None' }}</strong></div>
                <div><span>Funds Released</span><strong>{{ $requisition->funds_released_at?->toDayDateTimeString() ?? 'Pending' }}</strong></div>
            </div>

            @if($canReview)
                <form method="POST" action="{{ route('requisitions.in-review', $requisition) }}" class="mt-4">
                    @csrf
                    <button class="btn-secondary" type="submit">Mark In Review</button>
                </form>
            @endif

            @if($canApprove)
                <div class="mt-4 grid gap-4">
                    <form method="POST" action="{{ route('requisitions.approve', $requisition) }}" class="space-y-3">
                        @csrf
                        <label><span class="label">Approval Notes</span><textarea class="input min-h-20" name="decision_notes"></textarea></label>
                        <button class="btn-primary" type="submit">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('requisitions.reject', $requisition) }}" class="space-y-3">
                        @csrf
                        <label><span class="label">Rejection Notes</span><textarea class="input min-h-20" name="decision_notes" required></textarea></label>
                        <button class="btn-danger" type="submit">Reject</button>
                    </form>
                </div>
            @endif

            @if($canReleaseFunds)
                <form method="POST" action="{{ route('requisitions.release-funds', $requisition) }}" class="mt-4 space-y-3">
                    @csrf
                    <label><span class="label">Funds Release Notes</span><textarea class="input min-h-20" name="release_notes"></textarea></label>
                    <button class="btn-primary" type="submit">Mark Funds Released</button>
                </form>
            @endif
        </section>
    </div>

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="p-5">
            <h2 class="section-title">Requested Items</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Details</th><th>Payment Type</th><th>Quantity</th><th>Unit Price</th><th>Total Price</th><th>Source</th></tr></thead>
                <tbody>
                    @foreach($requisition->items as $item)
                        <tr>
                            <td>{{ $item->description }}</td>
                            <td>{{ $item->payment_type }}</td>
                            <td>{{ number_format((float) $item->quantity, 2) }}</td>
                            <td>E{{ number_format((float) $item->estimated_unit_cost, 2) }}</td>
                            <td>E{{ number_format((float) $item->estimated_total, 2) }}</td>
                            <td>{{ $item->source ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_0.85fr]">
        <section class="panel">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="section-title">Documents</h2>
                @if($canEdit || $user->canReleaseRequisitionFunds())
                    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <input type="hidden" name="module" value="requisition">
                        <input type="hidden" name="record_id" value="{{ $requisition->id }}">
                        <input type="hidden" name="category" value="{{ \App\Models\Document::CATEGORY_REQUISITION_ATTACHMENT }}">
                        <input class="input max-w-xs" type="file" name="document" required>
                        <button class="btn-secondary" type="submit">Upload</button>
                    </form>
                @endif
            </div>
            <div class="mt-4">
                @include('documents.preview-card', ['documents' => $requisition->documents])
            </div>
        </section>

        <section class="panel">
            <h2 class="section-title">Email Log</h2>
            <div class="mt-4 divide-y divide-neutral-100">
                @forelse($requisition->emailLogs->sortByDesc('created_at') as $log)
                    <div class="py-3 text-sm">
                        <div class="font-semibold">{{ $log->subject }}</div>
                        <div class="text-neutral-500">{{ $log->recipient ?: 'No recipient' }} | {{ $log->status }} | {{ $log->created_at->toDayDateTimeString() }}</div>
                        @if($log->status === 'Failed' && filled($log->message))
                            <div class="mt-1 text-rose-700">{{ $log->message }}</div>
                        @endif
                    </div>
                @empty
                    <p class="empty">No requisition email activity yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
