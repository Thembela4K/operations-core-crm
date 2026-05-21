@extends('layouts.app')

@section('content')
    <h1 class="page-title">New Tender Proposal</h1>
    <form method="POST" action="{{ route('tender-proposals.store') }}" enctype="multipart/form-data" class="panel mt-6">
        @csrf
        @include('tender_proposals.form')
        <div class="mt-6 flex gap-2">
            <button class="btn-primary" type="submit">Create Tender Proposal</button>
            <a class="btn-secondary" href="{{ route('tender-proposals.index') }}">Cancel</a>
        </div>
    </form>
@endsection
