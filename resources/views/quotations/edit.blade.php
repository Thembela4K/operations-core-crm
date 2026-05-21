@extends('layouts.app')

@section('content')
    <h1 class="page-title">Edit Quotation</h1>
    <form method="POST" action="{{ route('quotations.update', $quotation) }}" class="panel mt-6">
        @csrf
        @method('PUT')
        @include('quotations.form')
        <div class="mt-6 flex gap-2">
            <button class="btn-primary" type="submit">Save Changes</button>
            <a class="btn-secondary" href="{{ route('quotations.show', $quotation) }}">Cancel</a>
        </div>
    </form>
@endsection
