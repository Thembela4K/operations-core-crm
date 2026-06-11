@extends('layouts.app')

@section('content')
    <h1 class="page-title">New Catalog Item</h1>
    <form class="panel mt-6" method="POST" action="{{ route('catalog-items.store') }}">
        @csrf
        @include('catalog_items.form')
        <div class="mt-6 flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('catalog-items.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">Create Item</button>
        </div>
    </form>
@endsection
