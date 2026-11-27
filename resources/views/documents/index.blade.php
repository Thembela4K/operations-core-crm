@extends('layouts.app')

@section('content')
    <div>
        <h1 class="page-title">Document Registry</h1>
        <p class="page-subtitle">
            @if($tab === 'company')
                Company documents — licences, registrations and operational paperwork.
            @else
                Attachments that belong to a record you can access.
            @endif
        </p>
    </div>

    <nav class="mt-4 flex flex-wrap gap-2">
        <a class="btn-{{ $tab === 'attachments' ? 'primary' : 'secondary' }}" href="{{ route('documents.index', ['tab' => 'attachments']) }}">Attachments</a>
        <a class="btn-{{ $tab === 'company' ? 'primary' : 'secondary' }}" href="{{ route('documents.index', ['tab' => 'company']) }}">Company Documents</a>
    </nav>

    <form class="panel mt-6 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(220px,1fr)_repeat(4,minmax(150px,190px))_auto]" method="GET">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input class="input" name="search" value="{{ request('search') }}" placeholder="Search file name, title, or tags">
        <select class="input" name="category">
            <option value="">All categories</option>
            @foreach($categories as $value => $label)
                <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @if($tab === 'attachments')
            <select class="input" name="module"><option value="">All modules</option>@foreach($modules as $value => $label)<option value="{{ $value }}" @selected(request('module') === $value)>{{ $label }}</option>@endforeach</select>
            <select class="input" name="linked_type"><option value="">Any submission type</option>@foreach($linkedTypes as $value => $label)<option value="{{ $value }}" @selected(request('linked_type') === $value)>{{ $label }}</option>@endforeach</select>
        @endif
        <input class="input" type="date" name="date_from" value="{{ request('date_from') }}">
        <input class="input" type="date" name="date_to" value="{{ request('date_to') }}">
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    @if($tab === 'company' && $canManageCompany)
        <section class="panel mt-6">
            <h2 class="section-title">Upload a Company Document</h2>
            <form class="mt-4 grid gap-3 md:grid-cols-[minmax(180px,220px)_1fr_1fr_auto]" method="POST" action="{{ route('documents.company.store') }}" enctype="multipart/form-data">
                @csrf
                <label>
                    <span class="label">Category</span>
                    <select class="input" name="category" required>
                        @foreach($categories as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label><span class="label">Title</span><input class="input" name="title" placeholder="e.g. Trading Licence 2026"></label>
                <label><span class="label">File</span><input class="input" type="file" name="document" required></label>
                <div class="flex items-end"><button class="btn-primary" type="submit">Upload</button></div>
            </form>
            <p class="mt-2 text-xs text-neutral-500">Only the Business Admin and Directors can upload or delete company documents. Everyone else can view and download them.</p>
        </section>
    @endif

    <section class="panel mt-6 overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                    @if($tab === 'company')
                        <tr><th>Document</th><th>Category</th><th>Uploaded</th><th>Size</th><th></th></tr>
                    @else
                        <tr><th>Document</th><th>Category</th><th>Linked Record</th><th>Uploaded</th><th></th></tr>
                    @endif
                </thead>
                <tbody>
                    @forelse($documents as $document)
                        <tr>
                            <td>
                                <strong>{{ $document->title ?: $document->original_name }}</strong><br>
                                <span class="text-xs text-neutral-500">{{ $document->tags ?: $document->original_name }}</span>
                            </td>
                            <td>{{ $categories[$document->category] ?? $document->category }}</td>
                            @if($tab === 'company')
                                <td>{{ $document->created_at?->toFormattedDateString() }}<br><span class="text-xs text-neutral-500">{{ $document->uploader?->name ?: 'System' }}</span></td>
                                <td>{{ number_format(($document->size ?? 0) / 1024, 1) }} KB</td>
                            @else
                                <td>{{ $document->documentable ? class_basename($document->documentable).' #'.$document->documentable->getKey() : 'Unlinked' }}</td>
                                <td>{{ $document->created_at?->toFormattedDateString() }}<br><span class="text-xs text-neutral-500">{{ $document->uploader?->name ?: 'System' }}</span></td>
                            @endif
                            <td class="text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <a class="link" href="{{ route('documents.download', $document) }}">Download</a>
                                    @if($tab === 'company' && $canManageCompany)
                                        <form method="POST" action="{{ route('documents.destroy', $document) }}" onsubmit="return confirm('Delete this company document?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="link text-rose-700" type="submit">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <p class="empty">{{ $tab === 'company' ? 'No company documents uploaded yet.' : 'No attachments found.' }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $documents->links() }}</div>
    </section>
@endsection
