@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="page-title">Edit Purchase</h1><p class="page-subtitle">{{ $purchase->purchase_number }}</p></div><a class="btn-secondary" href="{{ route('purchases.show', $purchase) }}">Back</a></div>
    <form class="panel mt-6" method="POST" action="{{ route('purchases.update', $purchase) }}">@csrf @method('PUT') @include('purchases.form')<div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Save Purchase</button></div></form>
@endsection
