@extends('layouts.app')

@section('content')
    <div>
        <h1 class="page-title">Edit Requisition</h1>
        <p class="page-subtitle">{{ $requisition->requisition_number }} | {{ $requisition->status }}</p>
    </div>

    <form class="panel mt-6" method="POST" action="{{ route('requisitions.update', $requisition) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('requisitions.form')
        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <a class="btn-secondary" href="{{ route('requisitions.show', $requisition) }}">Cancel</a>
            <button class="btn-secondary" type="submit" name="action" value="draft">Save Changes</button>
            <button class="btn-primary" type="submit" name="action" value="submit">Submit Requisition</button>
        </div>
    </form>
@endsection
