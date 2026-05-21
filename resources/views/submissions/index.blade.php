@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Submissions</h1>
            <p class="page-subtitle">Returned tender and quotation responses tied to their original assignments.</p>
        </div>
    </div>

    <section class="panel mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Record</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Submitted By</th>
                    <th>Submitted</th>
                    <th>Documents</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($submissions as $submission)
                    @php($record = $submission->submittable)
                    @php($route = $record instanceof \App\Models\TenderProposal ? route('tender-proposals.show', $record) : route('quotations.show', $record))
                    <tr>
                        <td>
                            <div class="font-medium">{{ $record->tender_reference ?? $record->quotation_code }}</div>
                            <div class="text-zinc-600">{{ $record->title ?? $record->opportunity }}</div>
                        </td>
                        <td>{{ $submission->department->name }}</td>
                        <td>{{ $submission->status }}</td>
                        <td>{{ $submission->submitter?->name ?? 'Unknown' }}</td>
                        <td>{{ $submission->submitted_at?->toDayDateTimeString() }}</td>
                        <td>{{ $submission->documents->count() }}</td>
                        <td class="text-right"><a class="link" href="{{ $route }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty">No submissions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="mt-4">{{ $submissions->links() }}</div>
    </section>
@endsection
