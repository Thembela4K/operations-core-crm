@extends('layouts.app')

@section('content')
    <h1 class="page-title">New Invoice</h1>
    <form class="panel mt-6" method="POST" action="{{ route('invoices.store') }}">
        @csrf
        @include('invoices.form')
        <div class="mt-6 flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('invoices.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">Save Invoice</button>
        </div>
    </form>
@endsection
