@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="page-title">Edit Supplier</h1><p class="page-subtitle">{{ $supplier->supplier_code }}</p></div><a class="btn-secondary" href="{{ route('suppliers.show', $supplier) }}">Back</a></div>
    <form class="panel mt-6" method="POST" action="{{ route('suppliers.update', $supplier) }}">@csrf @method('PUT') @include('suppliers.form')<div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Save Supplier</button></div></form>
@endsection
