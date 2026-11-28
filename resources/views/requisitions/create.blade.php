@extends('layouts.app')

@section('content')
    <div>
        <h1 class="page-title">New Requisition</h1>
        <p class="page-subtitle">Create a tracked internal request instead of sending it through email.</p>
    </div>

    <form class="panel mt-6" method="POST" action="{{ route('requisitions.store') }}" enctype="multipart/form-data">
        @csrf
        @if($requisition->source_ticket_id)
            <input type="hidden" name="source_ticket_id" value="{{ $requisition->source_ticket_id }}">
            <div class="mb-4 rounded border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950">
                Raised from IT ticket
                @if(isset($sourceTicket) && $sourceTicket)
                    <a class="text-link" href="{{ route('tickets.show', $sourceTicket) }}">{{ $sourceTicket->ticket_number }}</a>
                    &mdash; {{ $sourceTicket->title }}
                @endif
            </div>
        @endif
        @include('requisitions.form')
        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <a class="btn-secondary" href="{{ route('requisitions.index') }}">Cancel</a>
            <button class="btn-secondary" type="submit" name="action" value="draft">Save Draft</button>
            <button class="btn-primary" type="submit" name="action" value="submit">Submit Requisition</button>
        </div>
    </form>
@endsection
