<?php

namespace App\Services;

class SimplePdfService
{
    /**
     * Generates a compact text-based PDF. It keeps official exports working even
     * when server-side Composer packages cannot be installed on shared hosting.
     *
     * @param  array<int, string>  $lines
     */
    public function fromLines(array $lines, string $title = 'Datamatics Eswatini Document'): string
    {
        $pages = array_chunk($lines, 42);
        $objects = [];
        $pageIds = [];
        $fontId = 3;

        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = null;
        $objects[$fontId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

        $nextId = 4;
        foreach ($pages as $pageLines) {
            $contentId = $nextId++;
            $pageId = $nextId++;
            $pageIds[] = $pageId;

            $content = "BT\n/F1 10 Tf\n50 790 Td\n14 TL\n";
            foreach ($pageLines as $line) {
                $content .= '('.$this->escape($line).") Tj\nT*\n";
            }
            $content .= "ET\n";

            $objects[$contentId] = "<< /Length ".strlen($content)." >>\nstream\n{$content}endstream";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontId} 0 R >> >> /Contents {$contentId} 0 R >>";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', array_map(fn (int $id): string => "{$id} 0 R", $pageIds)).'] /Count '.count($pageIds).' >>';

        ksort($objects);
        $pdf = "%PDF-1.4\n% ".$this->escape($title)."\n";
        $offsets = [0];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_keys($objects) as $id) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $value) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
