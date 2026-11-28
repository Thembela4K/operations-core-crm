@extends('layouts.app')

@section('content')
    <h1 class="page-title">New IT Ticket</h1>
    <p class="page-subtitle">Tell IT what is wrong. You will get a ticket number straight away.</p>

    <form class="panel mt-6" method="POST" action="{{ route('tickets.store') }}">
        @csrf
        @include('tickets.form')
        <div class="mt-6 flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('tickets.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">Submit Ticket</button>
        </div>
    </form>
@endsection
