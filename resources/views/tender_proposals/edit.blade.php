@extends('layouts.app')

@section('content')
    <h1 class="page-title">Edit Tender Proposal</h1>
    <form method="POST" action="{{ route('tender-proposals.update', $tenderProposal) }}" enctype="multipart/form-data" class="panel mt-6">
        @csrf
        @method('PUT')
        @include('tender_proposals.form')
        <div class="mt-6 flex gap-2">
            <button class="btn-primary" type="submit">Save Changes</button>
            <a class="btn-secondary" href="{{ route('tender-proposals.show', $tenderProposal) }}">Cancel</a>
        </div>
    </form>
@endsection
