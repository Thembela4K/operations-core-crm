<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\PurchaseRecord;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\LedgerService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class AccountingLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_the_chart_of_accounts_is_seeded_with_balanced_normal_sides(): void
    {
        $this->assertSame('debit', Account::query()->where('code', '1100')->firstOrFail()->normal_balance);
        $this->assertSame('credit', Account::query()->where('code', '2100')->firstOrFail()->normal_balance);
        $this->assertSame(Account::TYPE_INCOME, Account::query()->where('code', '4000')->firstOrFail()->type);
    }

    public function test_a_balanced_entry_is_posted(): void
    {
        $entry = app(LedgerService::class)->post([
            ['account_id' => $this->account('1100')->id, 'debit' => 1150.00],
            ['account_id' => $this->account('4000')->id, 'credit' => 1000.00],
            ['account_id' => $this->account('2100')->id, 'credit' => 150.00],
        ]);

        $this->assertTrue($entry->isBalanced());
        $this->assertSame(1150.00, $entry->totalDebit());
        $this->assertSame(1150.00, $entry->totalCredit());
        $this->assertCount(3, $entry->lines);
        $this->assertStringStartsWith('JE-', $entry->entry_number);
    }

    public function test_an_unbalanced_entry_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LedgerService::class)->post([
            ['account_id' => $this->account('1100')->id, 'debit' => 1150.00],
            ['account_id' => $this->account('4000')->id, 'credit' => 1000.00],
        ]);
    }

    public function test_a_line_cannot_be_both_debit_and_credit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(LedgerService::class)->post([
            ['account_id' => $this->account('1100')->id, 'debit' => 100.00, 'credit' => 100.00],
            ['account_id' => $this->account('4000')->id, 'credit' => 100.00],
        ]);
    }

    public function test_invoice_posts_receivable_income_and_output_vat(): void
    {
        $invoice = $this->invoice(['subtotal' => 1000, 'vat_total' => 150, 'total' => 1150]);

        $entry = app(AccountingService::class)->postInvoice($invoice);

        $this->assertNotNull($entry);
        $this->assertSame(1150.00, $this->lineAmount($entry, '1100', 'debit'));
        $this->assertSame(1000.00, $this->lineAmount($entry, '4000', 'credit'));
        $this->assertSame(150.00, $this->lineAmount($entry, '2100', 'credit'));
        $this->assertTrue($entry->isBalanced());
    }

    public function test_posting_the_same_invoice_twice_does_not_duplicate_the_entry(): void
    {
        $invoice = $this->invoice(['subtotal' => 500, 'vat_total' => 75, 'total' => 575]);
        $accounting = app(AccountingService::class);

        $first = $accounting->postInvoice($invoice);
        $second = $accounting->postInvoice($invoice);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, JournalEntry::query()->count());
        $this->assertSame(3, JournalEntry::query()->firstOrFail()->lines()->count());
    }

    public function test_payment_posts_bank_against_receivables(): void
    {
        $invoice = $this->invoice(['subtotal' => 1000, 'vat_total' => 150, 'total' => 1150]);

        $payment = Payment::query()->create([
            'invoice_id' => $invoice->id,
            'payment_number' => 'REC-TEST-1',
            'payment_date' => now()->toDateString(),
            'amount' => 400,
            'method' => 'Bank',
        ]);

        $entry = app(AccountingService::class)->postPayment($payment);

        $this->assertSame(400.00, $this->lineAmount($entry, '1000', 'debit'));
        $this->assertSame(400.00, $this->lineAmount($entry, '1100', 'credit'));
    }

    public function test_expense_posts_net_vat_and_bank(): void
    {
        $expense = Expense::query()->create([
            'expense_number' => 'EXP-TEST-1',
            'category' => 'Office',
            'payee' => 'Stationery Shop',
            'expense_date' => now()->toDateString(),
            'amount' => 200,
            'vat_amount' => 30,
            'total_amount' => 230,
            'status' => 'Recorded',
        ]);

        $entry = app(AccountingService::class)->postExpense($expense);

        $this->assertSame(200.00, $this->lineAmount($entry, '6500', 'debit'));
        $this->assertSame(30.00, $this->lineAmount($entry, '1200', 'debit'));
        $this->assertSame(230.00, $this->lineAmount($entry, '1000', 'credit'));
        $this->assertTrue($entry->isBalanced());
    }

    public function test_purchase_record_posts_as_an_expense_with_no_vat(): void
    {
        $supplier = Supplier::query()->create([
            'supplier_code' => 'SUP-TEST-1',
            'name' => 'Test Supplier',
        ]);

        $purchase = PurchaseRecord::query()->create([
            'supplier_id' => $supplier->id,
            'purchase_number' => 'PUR-TEST-1',
            'title' => 'Consumables',
            'status' => 'Planned',
            'purchase_date' => now()->toDateString(),
            'amount' => 750,
        ]);

        $entry = app(AccountingService::class)->postPurchase($purchase);

        $this->assertSame(750.00, $this->lineAmount($entry, '6500', 'debit'));
        $this->assertSame(750.00, $this->lineAmount($entry, '1000', 'credit'));
        $this->assertSame(2, $entry->lines()->count());
    }

    public function test_reversing_creates_a_mirror_entry_and_marks_the_original(): void
    {
        $invoice = $this->invoice(['subtotal' => 1000, 'vat_total' => 150, 'total' => 1150]);
        $ledger = app(LedgerService::class);

        $original = app(AccountingService::class)->postInvoice($invoice);
        $reversal = $ledger->reverse($original, 'Invoice cancelled');

        $this->assertSame(JournalEntry::STATUS_REVERSED, $original->fresh()->status);
        $this->assertSame($reversal->id, $original->fresh()->reversed_by_id);
        $this->assertTrue($reversal->isBalanced());

        // Mirror image: the debits and credits swap sides.
        $this->assertSame(1150.00, $this->lineAmount($reversal, '1100', 'credit'));
        $this->assertSame(1000.00, $this->lineAmount($reversal, '4000', 'debit'));
    }

    public function test_posting_is_skipped_when_no_chart_is_configured(): void
    {
        JournalEntry::query()->forceDelete();
        Account::query()->forceDelete();

        $invoice = $this->invoice(['subtotal' => 100, 'vat_total' => 15, 'total' => 115]);

        $this->assertNull(app(AccountingService::class)->postInvoice($invoice));
        $this->assertSame(0, JournalEntry::query()->count());
    }

    private function account(string $code): Account
    {
        return Account::query()->where('code', $code)->firstOrFail();
    }

    private function lineAmount(JournalEntry $entry, string $accountCode, string $side): float
    {
        $accountId = $this->account($accountCode)->id;

        $line = $entry->lines->firstWhere('account_id', $accountId);

        $this->assertNotNull($line, "No line for account {$accountCode}.");

        return round((float) $line->{$side}, 2);
    }

    /**
     * @param  array<string, float>  $amounts
     */
    private function invoice(array $amounts): Invoice
    {
        $client = Client::query()->firstOrCreate(
            ['client_code' => 'CLT-TEST-1'],
            ['name' => 'Ledger Test Client'],
        );

        return Invoice::query()->create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-TEST-'.uniqid(),
            'status' => 'Issued',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => $amounts['subtotal'],
            'vat_total' => $amounts['vat_total'],
            'total' => $amounts['total'],
        ]);
    }
}
