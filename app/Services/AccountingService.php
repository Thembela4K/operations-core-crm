<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PurchaseRecord;
use App\Models\Supplier;
use App\Models\User;

/**
 * Turns operational documents into ledger entries.
 *
 * Each document posts exactly once, keyed on (source_type, source_id).
 * If the chart of accounts is not configured yet, posting is a no-op so the
 * operational modules keep working during rollout.
 */
class AccountingService
{
    public function __construct(
        private readonly AccountingConfig $config,
        private readonly LedgerService $ledger,
    ) {}

    public function isEnabled(): bool
    {
        return $this->config->isConfigured();
    }

    /**
     * Sales invoice: DR receivables, CR income, CR output VAT.
     */
    public function postInvoice(Invoice $invoice, ?User $by = null): ?JournalEntry
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if ($existing = $this->ledger->alreadyPosted(Invoice::class, $invoice->id)) {
            return $existing;
        }

        $receivable = $this->config->receivable();
        $income = $this->config->sales();
        $vatAccount = $this->config->vatOutput();

        if (! $receivable || ! $income || ! $vatAccount) {
            return null;
        }

        $total = round((float) $invoice->total, 2);
        $vat = round((float) $invoice->vat_total, 2);
        $net = round($total - $vat, 2);

        if ($total <= 0) {
            return null;
        }

        $lines = [];

        $this->push($lines, $this->debit($receivable, $total, 'Invoice '.$invoice->invoice_number, Client::class, $invoice->client_id));
        $this->push($lines, $this->credit($income, $net, 'Invoice '.$invoice->invoice_number));
        $this->push($lines, $this->credit($vatAccount, $vat, 'Output VAT '.$invoice->invoice_number));

        return $this->postDocument($lines, $invoice, $invoice->issue_date ?? now(), 'Invoice '.$invoice->invoice_number, $by);
    }

    /**
     * Payment received: DR bank, CR receivables.
     */
    public function postPayment(Payment $payment, ?User $by = null): ?JournalEntry
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if ($existing = $this->ledger->alreadyPosted(Payment::class, $payment->id)) {
            return $existing;
        }

        $bank = $this->config->bank();
        $receivable = $this->config->receivable();

        $amount = round((float) $payment->amount, 2);

        if (! $bank || ! $receivable || $amount <= 0) {
            return null;
        }

        $invoice = $payment->invoice;

        $lines = [];
        $this->push($lines, $this->debit($bank, $amount, 'Payment '.$payment->payment_number));
        $this->push($lines, $this->credit(
            $receivable,
            $amount,
            'Payment '.$payment->payment_number,
            $invoice ? Client::class : null,
            $invoice?->client_id,
        ));

        return $this->postDocument($lines, $payment, $payment->payment_date ?? now(), 'Payment '.$payment->payment_number, $by);
    }

    /**
     * Expense: DR expense, DR input VAT, CR bank.
     */
    public function postExpense(Expense $expense, ?User $by = null): ?JournalEntry
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if ($existing = $this->ledger->alreadyPosted(Expense::class, $expense->id)) {
            return $existing;
        }

        $expenseAccount = $this->config->defaultExpense();
        $vatAccount = $this->config->vatInput();
        $bank = $this->config->bank();

        if (! $expenseAccount || ! $bank) {
            return null;
        }

        $total = round((float) $expense->total_amount, 2);
        $vat = round((float) $expense->vat_amount, 2);
        $net = round($total - $vat, 2);

        if ($total <= 0) {
            return null;
        }

        $lines = [];
        $this->push($lines, $this->debit($expenseAccount, $net, 'Expense '.$expense->expense_number));
        $this->push($lines, $vatAccount ? $this->debit($vatAccount, $vat, 'Input VAT '.$expense->expense_number) : null);
        $this->push($lines, $this->credit($bank, $total, 'Expense '.$expense->expense_number));

        return $this->postDocument($lines, $expense, $expense->expense_date ?? now(), 'Expense '.$expense->expense_number, $by);
    }

    /**
     * Purchase record: treated as an expense (decision A1). DR expense, CR bank.
     * No VAT line — purchase_records carries no VAT breakdown.
     */
    public function postPurchase(PurchaseRecord $purchase, ?User $by = null): ?JournalEntry
    {
        if (! $this->isEnabled()) {
            return null;
        }

        if ($existing = $this->ledger->alreadyPosted(PurchaseRecord::class, $purchase->id)) {
            return $existing;
        }

        $expenseAccount = $this->config->defaultExpense();
        $bank = $this->config->bank();

        $amount = round((float) $purchase->amount, 2);

        if (! $expenseAccount || ! $bank || $amount <= 0) {
            return null;
        }

        $lines = [];
        $this->push($lines, $this->debit($expenseAccount, $amount, 'Purchase '.$purchase->purchase_number));
        $this->push($lines, $this->credit($bank, $amount, 'Purchase '.$purchase->purchase_number));

        return $this->postDocument($lines, $purchase, $purchase->purchase_date ?? now(), 'Purchase '.$purchase->purchase_number, $by);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>  $meta
     */
    private function postDocument(array $lines, object $document, mixed $date, string $memo, ?User $by): ?JournalEntry
    {
        // A document that nets to a single line cannot balance; skip rather than post rubbish.
        if (count($lines) < 2) {
            return null;
        }

        return $this->ledger->post($lines, [
            'entry_date' => $date,
            'memo' => $memo,
            'source_type' => $document::class,
            'source_id' => $document->id,
            'posted_by' => $by?->id,
            'created_by' => $by?->id,
        ]);
    }

    /**
     * Only non-zero lines are posted; a zero line is meaningless in a ledger.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<string, mixed>|null  $line
     */
    private function push(array &$lines, ?array $line): void
    {
        if ($line !== null) {
            $lines[] = $line;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function debit(Account $account, float $amount, ?string $description = null, ?string $contactType = null, ?int $contactId = null): ?array
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            return null;
        }

        return [
            'account_id' => $account->id,
            'debit' => $amount,
            'credit' => 0,
            'description' => $description,
            'contact_type' => $contactType,
            'contact_id' => $contactId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function credit(Account $account, float $amount, ?string $description = null, ?string $contactType = null, ?int $contactId = null): ?array
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            return null;
        }

        return [
            'account_id' => $account->id,
            'debit' => 0,
            'credit' => $amount,
            'description' => $description,
            'contact_type' => $contactType,
            'contact_id' => $contactId,
        ];
    }
}
