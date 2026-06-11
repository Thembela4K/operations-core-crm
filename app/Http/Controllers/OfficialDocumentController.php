<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Services\AuditLogService;
use App\Services\OfficialDocumentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialDocumentController extends Controller
{
    public function salesQuotation(Request $request, SalesQuotation $salesQuotation, OfficialDocumentService $documents, AuditLogService $audit): StreamedResponse
    {
        abort_unless($request->user()->canViewReports() || $salesQuotation->department_id === $request->user()->department_id, 403);
        $document = $documents->salesQuotation($salesQuotation);
        $audit->record('pdf_generated', $salesQuotation, "Generated PDF for {$salesQuotation->quotation_number}.");

        return $documents->response($document);
    }

    public function invoice(Request $request, Invoice $invoice, OfficialDocumentService $documents, AuditLogService $audit): StreamedResponse
    {
        abort_unless($request->user()->canViewReports() || $invoice->department_id === $request->user()->department_id, 403);
        $document = $documents->invoice($invoice);
        $audit->record('pdf_generated', $invoice, "Generated PDF for {$invoice->invoice_number}.");

        return $documents->response($document);
    }

    public function payment(Request $request, Payment $payment, OfficialDocumentService $documents, AuditLogService $audit): StreamedResponse
    {
        abort_unless($request->user()->canViewReports() || $request->user()->canManageFinance(), 403);
        $document = $documents->payment($payment);
        $audit->record('pdf_generated', $payment, "Generated PDF for {$payment->payment_number}.");

        return $documents->response($document);
    }

    public function requisition(Request $request, Requisition $requisition, OfficialDocumentService $documents, AuditLogService $audit): StreamedResponse
    {
        abort_unless($request->user()->canViewRequisitions() || $requisition->department_id === $request->user()->department_id, 403);
        $document = $documents->requisition($requisition);
        $audit->record('pdf_generated', $requisition, "Generated PDF for {$requisition->requisition_number}.");

        return $documents->response($document);
    }
}
