@extends('layouts.app')

@section('content')
    <h1 class="page-title">Edit {{ $order->order_number }}</h1>
    <form class="panel mt-6" method="POST" action="{{ route('sales-orders.update', $order) }}">
        @csrf
        @method('PUT')
        @include('sales-orders.form')
        <div class="mt-6 flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('sales-orders.show', $order) }}">Cancel</a>
            <button class="btn-primary" type="submit">Save Changes</button>
        </div>
    </form>
@endsection
