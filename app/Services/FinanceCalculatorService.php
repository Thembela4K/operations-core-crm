<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\SalesQuotation;
use App\Models\SalesQuotationItem;
use Illuminate\Support\Facades\DB;

class FinanceCalculatorService
{
    public function vatRatePercent(): float
    {
        return (float) (AppSetting::valueFor('vat_rate') ?: 15);
    }

    public function vatRate(): float
    {
        return $this->vatRatePercent() / 100;
    }

    public function syncSalesQuotationItems(SalesQuotation $quotation, array $items): void
    {
        DB::transaction(function () use ($quotation, $items): void {
            $quotation->items()->delete();

            foreach ($items as $position => $item) {
                $quotation->items()->create($this->lineAttributes($item, $position + 1));
            }

            $this->refreshSalesQuotationTotals($quotation);
        });
    }

    public function syncInvoiceItems(Invoice $invoice, array $items): void
    {
        DB::transaction(function () use ($invoice, $items): void {
            $invoice->items()->delete();

            foreach ($items as $position => $item) {
                $invoice->items()->create($this->lineAttributes($item, $position + 1));
            }

            $this->refreshInvoiceTotals($invoice);
        });
    }

    public function refreshSalesQuotationTotals(SalesQuotation $quotation): void
    {
        $items = $quotation->items()->get();

        $quotation->update([
            'subtotal' => $items->sum(fn (SalesQuotationItem $item): float => (float) $item->line_subtotal),
            'discount_total' => $items->sum(fn (SalesQuotationItem $item): float => (float) $item->discount_amount),
            'vat_total' => $items->sum(fn (SalesQuotationItem $item): float => (float) $item->vat_amount),
            'total' => $items->sum(fn (SalesQuotationItem $item): float => (float) $item->line_total),
        ]);
    }

    public function refreshInvoiceTotals(Invoice $invoice): void
    {
        $items = $invoice->items()->get();
        $amountPaid = $invoice->payments()->sum('amount');
        $total = round($items->sum(fn (InvoiceItem $item): float => (float) $item->line_total), 2);
        $balance = max(0, round($total - (float) $amountPaid, 2));
        $status = $this->invoiceStatusForBalance($invoice, $total, $amountPaid, $balance);

        $invoice->update([
            'subtotal' => $items->sum(fn (InvoiceItem $item): float => (float) $item->line_subtotal),
            'discount_total' => $items->sum(fn (InvoiceItem $item): float => (float) $item->discount_amount),
            'vat_total' => $items->sum(fn (InvoiceItem $item): float => (float) $item->vat_amount),
            'total' => $total,
            'amount_paid' => $amountPaid,
            'balance_due' => $balance,
            'status' => $status,
            'paid_at' => $balance <= 0 && $total > 0 ? now() : null,
        ]);
    }

    public function recordPayment(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data): Payment {
            $payment = $invoice->payments()->create($data);
            $this->refreshInvoiceTotals($invoice);

            return $payment;
        });
    }

    public function quotationItemsForInvoice(SalesQuotation $quotation): array
    {
        return $quotation->items->map(fn (SalesQuotationItem $item): array => [
            'catalog_item_id' => $item->catalog_item_id,
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
            'discount_amount' => $item->discount_amount,
            'taxable' => $item->taxable,
        ])->all();
    }

    private function lineAttributes(array $item, int $position): array
    {
        $quantity = max(0, (float) ($item['quantity'] ?? 0));
        $unitPrice = max(0, (float) ($item['unit_price'] ?? 0));
        $discount = max(0, (float) ($item['discount_amount'] ?? 0));
        $taxable = (bool) ($item['taxable'] ?? false);
        $gross = round($quantity * $unitPrice, 2);
        $net = max(0, round($gross - $discount, 2));
        $vat = $taxable ? round($net * $this->vatRate(), 2) : 0.0;

        return [
            'catalog_item_id' => $item['catalog_item_id'] ?? null,
            'position' => $position,
            'description' => $item['description'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount_amount' => min($discount, $gross),
            'taxable' => $taxable,
            'line_subtotal' => $net,
            'vat_amount' => $vat,
            'line_total' => round($net + $vat, 2),
        ];
    }

    private function invoiceStatusForBalance(Invoice $invoice, float $total, float $amountPaid, float $balance): string
    {
        if ($invoice->status === Invoice::STATUS_CANCELLED || $invoice->status === Invoice::STATUS_DRAFT) {
            return $invoice->status;
        }

        if ($balance <= 0 && $total > 0) {
            return Invoice::STATUS_PAID;
        }

        if ($amountPaid > 0) {
            return Invoice::STATUS_PARTIALLY_PAID;
        }

        if ($invoice->due_date && $invoice->due_date->isPast()) {
            return Invoice::STATUS_OVERDUE;
        }

        return $invoice->status ?: Invoice::STATUS_ISSUED;
    }
}
