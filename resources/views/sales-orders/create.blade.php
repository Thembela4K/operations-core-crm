@extends('layouts.app')

@section('content')
    <h1 class="page-title">New Sales Order</h1>
    <form class="panel mt-6" method="POST" action="{{ route('sales-orders.store') }}">
        @csrf
        @include('sales-orders.form')
        <div class="mt-6 flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('sales-orders.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">Create Sales Order</button>
        </div>
    </form>
@endsection
