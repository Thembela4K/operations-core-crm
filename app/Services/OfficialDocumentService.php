<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Services\Assistant\DocumentTextExtractor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialDocumentService
{
    public function __construct(private readonly SimplePdfService $pdf)
    {
    }

    public function salesQuotation(SalesQuotation $quotation): Document
    {
        $quotation->load(['client', 'items', 'department']);
        $lines = [
            'DATAMATICS ESWATINI',
            'Sales Quotation: '.$quotation->quotation_number,
            'Client: '.$quotation->client->name,
            'Department: '.($quotation->department?->name ?: 'Unassigned'),
            'Issue Date: '.$quotation->issue_date?->toDateString(),
            'Valid Until: '.$quotation->valid_until?->toDateString(),
            '',
            'Items',
        ];

        foreach ($quotation->items as $item) {
            $lines[] = $item->position.'. '.$item->description.' | Qty '.$item->quantity.' | Unit E'.$item->unit_price.' | Total E'.$item->line_total;
        }

        array_push($lines, '', 'Subtotal: E'.$quotation->subtotal, 'VAT 15%: E'.$quotation->vat_total, 'Total: E'.$quotation->total, '', (string) $quotation->terms);

        return $this->store($quotation, $quotation->quotation_number.'.pdf', $lines, 'Sales quotation PDF');
    }

    public function invoice(Invoice $invoice): Document
    {
        $invoice->load(['client', 'items', 'payments', 'department']);
        $lines = [
            'DATAMATICS ESWATINI',
            'Invoice: '.$invoice->invoice_number,
            'Client: '.$invoice->client->name,
            'Department: '.($invoice->department?->name ?: 'Unassigned'),
            'Issue Date: '.$invoice->issue_date?->toDateString(),
            'Due Date: '.$invoice->due_date?->toDateString(),
            '',
            'Items',
        ];

        foreach ($invoice->items as $item) {
            $lines[] = $item->position.'. '.$item->description.' | Qty '.$item->quantity.' | Unit E'.$item->unit_price.' | Total E'.$item->line_total;
        }

        array_push($lines, '', 'Subtotal: E'.$invoice->subtotal, 'VAT 15%: E'.$invoice->vat_total, 'Total: E'.$invoice->total, 'Paid: E'.$invoice->amount_paid, 'Balance Due: E'.$invoice->balance_due, '', (string) $invoice->terms);

        return $this->store($invoice, $invoice->invoice_number.'.pdf', $lines, 'Invoice PDF');
    }

    public function payment(Payment $payment): Document
    {
        $payment->load(['invoice.client', 'recorder']);
        $invoice = $payment->invoice;
        $lines = [
            'DATAMATICS ESWATINI',
            'Receipt: '.$payment->payment_number,
            'Invoice: '.$invoice->invoice_number,
            'Client: '.$invoice->client->name,
            'Payment Date: '.$payment->payment_date?->toDateString(),
            'Method: '.$payment->method,
            'Reference: '.($payment->reference ?: 'None'),
            'Amount: E'.$payment->amount,
            'Recorded By: '.($payment->recorder?->name ?: 'System'),
            '',
            (string) $payment->notes,
        ];

        return $this->store($payment, $payment->payment_number.'.pdf', $lines, 'Payment receipt PDF', Document::CATEGORY_RECEIPT);
    }

    public function requisition(Requisition $requisition): Document
    {
        $requisition->load(['department', 'requester', 'approver', 'releaser', 'items']);
        $lines = [
            'DATAMATICS ESWATINI',
            'Requisition: '.$requisition->requisition_number,
            'Title: '.$requisition->title,
            'Department: '.($requisition->department?->name ?: 'Unassigned'),
            'Requested By: '.($requisition->requester?->name ?: 'Unknown'),
            'Status: '.$requisition->status,
            'Needed By: '.$requisition->needed_by?->toDateString(),
            '',
            'Purpose',
            (string) $requisition->purpose,
            '',
            'Items',
        ];

        foreach ($requisition->items as $item) {
            $lines[] = $item->position.'. '.$item->description.' | '.$item->payment_type.' | Qty '.$item->quantity.' | Total E'.$item->estimated_total;
        }

        array_push($lines, '', 'Bank Total: E'.$requisition->bank_total, 'Cash Total: E'.$requisition->cash_total, 'Other Total: E'.$requisition->other_total, 'Estimated Total: E'.$requisition->estimated_total);

        return $this->store($requisition, $requisition->requisition_number.'.pdf', $lines, 'Requisition PDF');
    }

    public function response(Document $document): StreamedResponse
    {
        return Storage::download($document->path, $document->original_name, ['Content-Type' => 'application/pdf']);
    }

    private function store(Model $record, string $name, array $lines, string $title, string $category = Document::CATEGORY_GENERATED_PDF): Document
    {
        $path = 'documents/generated/'.class_basename($record).'/'.$record->getKey().'/'.$name;
        $bytes = $this->pdf->fromLines($lines, $title);
        Storage::put($path, $bytes);

        $document = $record->documents()->create([
            'category' => $category,
            'title' => $title,
            'tags' => 'generated,pdf,official',
            'is_generated' => true,
            'original_name' => $name,
            'stored_name' => basename($path),
            'path' => $path,
            'mime_type' => 'application/pdf',
            'size' => strlen($bytes),
            'uploaded_by' => auth()->id(),
        ]);

        app(DocumentTextExtractor::class)->index($document);

        return $document;
    }
}
