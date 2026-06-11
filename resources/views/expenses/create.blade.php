@extends('layouts.app')

@section('content')
    <h1 class="page-title">New Expense</h1>
    <form class="panel mt-6" method="POST" action="{{ route('expenses.store') }}">
        @csrf
        @include('expenses.form')
        <div class="mt-6 flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('expenses.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">Record Expense</button>
        </div>
    </form>
@endsection
