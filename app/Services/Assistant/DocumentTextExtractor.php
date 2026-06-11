<?php

namespace App\Services\Assistant;

use App\Models\Document;
use App\Models\DocumentText;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class DocumentTextExtractor
{
    private const MAX_STORED_CHARS = 200000;

    public function index(Document $document): ?DocumentText
    {
        if (! Schema::hasTable('document_texts')) {
            return null;
        }

        try {
            [$status, $content, $error] = $this->extract($document);
        } catch (\Throwable $exception) {
            $status = DocumentText::STATUS_FAILED;
            $content = null;
            $error = $exception->getMessage();
        }

        return DocumentText::query()->updateOrCreate(
            ['document_id' => $document->id],
            [
                'status' => $status,
                'content' => $content,
                'char_count' => mb_strlen((string) $content),
                'extracted_at' => now(),
                'error' => $error,
            ],
        );
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function extract(Document $document): array
    {
        if (! $document->path || ! Storage::exists($document->path)) {
            return [DocumentText::STATUS_FAILED, null, 'File not found in storage.'];
        }

        $mime = strtolower((string) $document->mime_type);

        if (str_contains($mime, 'text/') || in_array($mime, ['application/json', 'application/xml', 'text/csv'], true)) {
            return [DocumentText::STATUS_INDEXED, $this->clean(Storage::get($document->path)), null];
        }

        if ($mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || str_ends_with(strtolower($document->original_name), '.docx')) {
            return $this->extractDocx($document);
        }

        if ($mime === 'application/pdf' || str_ends_with(strtolower($document->original_name), '.pdf')) {
            return [DocumentText::STATUS_INDEXED, $this->extractPdfText(Storage::get($document->path)), null];
        }

        return [DocumentText::STATUS_UNSUPPORTED, null, 'This file type cannot be text-indexed locally.'];
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}
     */
    private function extractDocx(Document $document): array
    {
        if (! class_exists(ZipArchive::class)) {
            return [DocumentText::STATUS_UNSUPPORTED, null, 'PHP ZipArchive is not available.'];
        }

        $zip = new ZipArchive();
        $path = Storage::path($document->path);

        if ($zip->open($path) !== true) {
            return [DocumentText::STATUS_FAILED, null, 'Could not open DOCX archive.'];
        }

        $parts = [];

        foreach (['word/document.xml', 'word/header1.xml', 'word/footer1.xml', 'word/footnotes.xml'] as $entry) {
            $xml = $zip->getFromName($entry);

            if ($xml === false) {
                continue;
            }

            $xml = preg_replace('/<\/w:p>/', "\n", $xml) ?? $xml;
            $parts[] = html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        $zip->close();

        $content = $this->clean(implode("\n", $parts));

        return $content === ''
            ? [DocumentText::STATUS_UNSUPPORTED, null, 'No extractable DOCX text found.']
            : [DocumentText::STATUS_INDEXED, $content, null];
    }

    private function extractPdfText(string $bytes): string
    {
        preg_match_all('/\((?:\\\\.|[^\\\\)]){2,}\)/s', $bytes, $matches);

        $strings = collect($matches[0] ?? [])
            ->map(fn (string $value): string => substr($value, 1, -1))
            ->map(fn (string $value): string => str_replace(['\\(', '\\)', '\\\\', '\r', '\n'], ['(', ')', '\\', "\r", "\n"], $value))
            ->filter(fn (string $value): bool => preg_match('/[A-Za-z0-9]/', $value) === 1)
            ->implode("\n");

        if ($strings !== '') {
            return $this->clean($strings);
        }

        $fallback = preg_replace('/[^\x20-\x7E\r\n\t]+/', ' ', $bytes) ?? '';

        return $this->clean($fallback);
    }

    private function clean(string $content): string
    {
        $content = preg_replace('/[ \t]+/', ' ', $content) ?? $content;
        $content = preg_replace('/\R{3,}/', "\n\n", $content) ?? $content;
        $content = trim($content);

        return mb_substr($content, 0, self::MAX_STORED_CHARS);
    }
}
