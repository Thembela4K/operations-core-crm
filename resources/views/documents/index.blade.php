@extends('layouts.app')

@section('content')
    <div>
        <h1 class="page-title">Document Registry</h1>
        <p class="page-subtitle">Search uploaded and generated documents across CRM modules.</p>
    </div>
    <form class="panel mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_minmax(170px,220px)_repeat(2,minmax(140px,180px))_auto]" method="GET">
        <input class="input" name="search" value="{{ request('search') }}" placeholder="Search file name, title, or tags">
        <select class="input" name="category"><option value="">All categories</option>@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>@endforeach</select>
        <input class="input" type="date" name="date_from" value="{{ request('date_from') }}">
        <input class="input" type="date" name="date_to" value="{{ request('date_to') }}">
        <button class="btn-secondary" type="submit">Filter</button>
    </form>
    <section class="panel mt-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Document</th><th>Category</th><th>Linked Record</th><th>Uploaded</th><th></th></tr></thead>
                <tbody>
                    @forelse($documents as $document)
                        <tr>
                            <td><strong>{{ $document->title ?: $document->original_name }}</strong><br><span class="text-xs text-neutral-500">{{ $document->tags ?: $document->original_name }}</span></td>
                            <td>{{ $categories[$document->category] ?? $document->category }}</td>
                            <td>{{ $document->documentable ? class_basename($document->documentable).' #'.$document->documentable->getKey() : 'Unlinked' }}</td>
                            <td>{{ $document->created_at->toFormattedDateString() }}<br><span class="text-xs text-neutral-500">{{ $document->uploader?->name ?: 'System' }}</span></td>
                            <td class="text-right"><a class="link" href="{{ route('documents.download', $document) }}">Download</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><p class="empty">No documents found.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $documents->links() }}</div>
    </section>
@endsection
