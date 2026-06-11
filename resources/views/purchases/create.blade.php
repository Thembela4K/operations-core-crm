@extends('layouts.app')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-3"><div><h1 class="page-title">New Purchase</h1><p class="page-subtitle">Track a procurement action from requisition to supplier.</p></div><a class="btn-secondary" href="{{ route('purchases.index') }}">Back</a></div>
    <form class="panel mt-6" method="POST" action="{{ route('purchases.store') }}">@csrf @include('purchases.form')<div class="mt-5 flex justify-end"><button class="btn-primary" type="submit">Create Purchase</button></div></form>
@endsection
