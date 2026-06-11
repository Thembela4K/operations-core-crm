@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="page-title">New Supplier</h1><p class="page-subtitle">Capture supplier details for procurement and expenses.</p></div><a class="btn-secondary" href="{{ route('suppliers.index') }}">Back</a></div>
    <form class="panel mt-6" method="POST" action="{{ route('suppliers.store') }}">@csrf @include('suppliers.form')<div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Create Supplier</button></div></form>
@endsection
