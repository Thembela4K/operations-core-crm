@extends('layouts.app')

@section('content')
    <h1 class="page-title">Edit Catalog Item</h1>
    <form class="panel mt-6" method="POST" action="{{ route('catalog-items.update', $item) }}">
        @csrf
        @method('PUT')
        @include('catalog_items.form')
        <div class="mt-6 flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('catalog-items.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">Save Item</button>
        </div>
    </form>
@endsection
