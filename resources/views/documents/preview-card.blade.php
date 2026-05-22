@php($documents = collect($documents))

@if($documents->isNotEmpty())
    <div class="document-card" data-document-preview>
        <div class="document-list">
            @foreach($documents as $document)
                <div class="document-list-row">
                    <div class="document-list-meta">
                        <span>{{ \App\Models\Document::CATEGORIES[$document->category] ?? 'Document' }}</span>
                        <strong>{{ $document->original_name }}</strong>
                    </div>
                    <div class="document-list-actions">
                        @if($document->isPreviewable())
                            <button
                                class="btn-secondary"
                                type="button"
                                data-document-preview-open
                                data-preview-url="{{ route('documents.preview', $document) }}#toolbar=0&navpanes=0&view=FitH"
                                data-download-url="{{ route('documents.download', $document) }}"
                                data-title="{{ $document->original_name }}"
                            >
                                Preview
                            </button>
                        @else
                            <span class="document-preview-note">No preview</span>
                        @endif
                        <a class="btn-secondary" href="{{ route('documents.download', $document) }}">Download</a>
                        @if(auth()->user()->canManage())
                            <form method="POST" action="{{ route('documents.destroy', $document) }}">
                                @csrf
                                @method('DELETE')
                                <button class="text-rose-700" type="submit">Remove</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="document-preview-modal" hidden data-document-preview-modal>
            <div class="document-preview-backdrop" data-document-preview-close></div>
            <section class="document-preview-dialog" role="dialog" aria-modal="true" aria-label="Document preview">
                <header class="document-preview-header">
                    <span data-document-preview-title>Document preview</span>
                    <div class="flex items-center gap-2">
                        <a class="btn-secondary" href="#" data-document-preview-download>Download</a>
                        <button class="btn-secondary" type="button" data-document-preview-close>Close</button>
                    </div>
                </header>
                <iframe class="document-preview-frame" title="Document preview" data-document-preview-frame></iframe>
            </section>
        </div>
    </div>
@else
    <p class="empty">No documents.</p>
@endif
