@extends('layouts.app')

@section('content')
    <h1 class="page-title">Edit Client</h1>
    <form class="panel mt-6" method="POST" action="{{ route('clients.update', $client) }}">
        @csrf
        @method('PUT')
        @include('clients.form')
        <div class="mt-6 flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('clients.show', $client) }}">Cancel</a>
            <button class="btn-primary" type="submit">Save Client</button>
        </div>
    </form>
@endsection
