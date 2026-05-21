<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'module' => ['required', 'in:project,quotation'],
            'record_id' => ['required', 'integer'],
            'document' => ['required', 'file', 'max:10240'],
        ]);

        $record = $this->resolveRecord($data['module'], (int) $data['record_id']);
        $this->authorizeRecordAccess($request, $record);

        $file = $request->file('document');
        $path = $file->store("documents/{$data['module']}/{$record->id}");

        $record->documents()->create([
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => basename($path),
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Document uploaded.');
    }

    public function download(Request $request, Document $document): StreamedResponse
    {
        $document->load('documentable');
        $this->authorizeRecordAccess($request, $document->documentable);

        return Storage::download($document->path, $document->original_name);
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        $document->load('documentable');
        $this->authorizeRecordAccess($request, $document->documentable);

        Storage::delete($document->path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }

    private function resolveRecord(string $module, int $id): Project|Quotation
    {
        return $module === 'project'
            ? Project::query()->findOrFail($id)
            : Quotation::query()->findOrFail($id);
    }

    private function authorizeRecordAccess(Request $request, Project|Quotation $record): void
    {
        if ($request->user()->canManage()) {
            return;
        }

        if (! $record->assignments()->where('department_id', $request->user()->department_id)->exists()) {
            abort(403);
        }
    }
}
