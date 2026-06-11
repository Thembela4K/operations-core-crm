@extends('layouts.app')

@section('content')
    <h1 class="page-title">Edit Expense</h1>
    <form class="panel mt-6" method="POST" action="{{ route('expenses.update', $expense) }}">
        @csrf
        @method('PUT')
        @include('expenses.form')
        <div class="mt-6 flex justify-end gap-3">
            <a class="btn-secondary" href="{{ route('expenses.index') }}">Cancel</a>
            <button class="btn-primary" type="submit">Save Expense</button>
        </div>
    </form>

    <section class="panel mt-6">
        <h2 class="section-title">Receipt Documents</h2>
        <form class="mt-4 grid gap-3 md:grid-cols-[1fr_auto]" method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="module" value="expense">
            <input type="hidden" name="record_id" value="{{ $expense->id }}">
            <input type="hidden" name="category" value="{{ \App\Models\Document::CATEGORY_EXPENSE_RECEIPT }}">
            <input class="input" type="file" name="document" required>
            <button class="btn-secondary" type="submit">Upload Receipt</button>
        </form>
        <div class="mt-4">
            @include('documents.preview-card', ['documents' => $expense->documents])
        </div>
    </section>
@endsection
