@extends('layouts.app')

@section('content')
    <h1 class="page-title">New Quotation</h1>
    <form method="POST" action="{{ route('quotations.store') }}" class="panel mt-6">
        @csrf
        @include('quotations.form')
        <div class="mt-6 flex gap-2">
            <button class="btn-primary" type="submit">Create Quotation</button>
            <a class="btn-secondary" href="{{ route('quotations.index') }}">Cancel</a>
        </div>
    </form>
@endsection
