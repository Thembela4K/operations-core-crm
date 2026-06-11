@extends('layouts.app')

@section('content')
    <h1 class="page-title">New Sales Quotation</h1>
    <form class="panel mt-6" method="POST" action="{{ route('sales-quotations.store') }}">
        @csrf
        @include('sales_quotations.form')
        <div class="mt-6 flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('sales-quotations.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">Save Draft</button>
        </div>
    </form>
@endsection
