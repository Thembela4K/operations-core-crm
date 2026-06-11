@extends('layouts.app')

@section('content')
    <h1 class="page-title">New Client</h1>
    <form class="panel mt-6" method="POST" action="{{ route('clients.store') }}">
        @csrf
        @include('clients.form')
        <div class="mt-6 flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('clients.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">Create Client</button>
        </div>
    </form>
@endsection
