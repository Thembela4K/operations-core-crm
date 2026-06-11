@extends('layouts.app')

@section('content')
    <div>
        <h1 class="page-title">New Requisition</h1>
        <p class="page-subtitle">Create a tracked internal request instead of sending it through email.</p>
    </div>

    <form class="panel mt-6" method="POST" action="{{ route('requisitions.store') }}" enctype="multipart/form-data">
        @csrf
        @include('requisitions.form')
        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <a class="btn-secondary" href="{{ route('requisitions.index') }}">Cancel</a>
            <button class="btn-secondary" type="submit" name="action" value="draft">Save Draft</button>
            <button class="btn-primary" type="submit" name="action" value="submit">Submit Requisition</button>
        </div>
    </form>
@endsection
