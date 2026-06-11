<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AuditLogService;
use App\Services\FinanceCalculatorService;
use App\Services\FinanceNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice, FinanceNumberService $numbers, FinanceCalculatorService $calculator, AuditLogService $audit): RedirectResponse
    {
        if (! $request->user()->canManageFinance()) {
            abort(403);
        }

        if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true)) {
            abort(403);
        }

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(Payment::METHODS)],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['payment_number'] = $numbers->paymentNumber();
        $data['recorded_by'] = $request->user()->id;

        $payment = $calculator->recordPayment($invoice, $data);
        $audit->record('payment_recorded', $payment, "Payment {$payment->payment_number} recorded for invoice {$invoice->invoice_number}.");

        return redirect()->route('invoices.show', $invoice)->with('success', 'Payment recorded.');
    }
}
