@extends('layouts.app')

@section('content')
    <h1 class="page-title">Edit Sales Quotation</h1>
    <form class="panel mt-6" method="POST" action="{{ route('sales-quotations.update', $salesQuotation) }}">
        @csrf
        @method('PUT')
        @include('sales_quotations.form')
        <div class="mt-6 flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('sales-quotations.show', $salesQuotation) }}">Cancel</a>
            <button class="btn-primary" type="submit">Save Draft</button>
        </div>
    </form>
@endsection
