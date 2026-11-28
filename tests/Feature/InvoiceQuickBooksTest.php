<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invoices are now created directly, QuickBooks style, instead of only from
 * job cards.
 */
class InvoiceQuickBooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_create_form_renders_quickbooks_fields(): void
    {
        $this->actingAs($this->admin())->get(route('invoices.create'))
            ->assertOk()
            ->assertSee('Customer:Job')
            ->assertSee('P.O. No.')
            ->assertSee('Bill To')
            ->assertSee('Ship To')
            ->assertSee('Customer Message')
            ->assertSee('Net 30');
    }

    public function test_an_invoice_can_be_created_directly_with_quickbooks_fields(): void
    {
        $client = $this->client();

        $response = $this->actingAs($this->admin())->post(route('invoices.store'), [
            'client_id' => $client->id,
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'terms' => 'Net 30',
            'po_number' => 'PO-7788',
            'ship_to' => 'Warehouse 4',
            'customer_message' => 'Thanks for your business.',
            'items' => [
                ['description' => 'Network installation', 'quantity' => 2, 'unit_price' => 500, 'taxable' => 1],
            ],
        ]);

        $invoice = Invoice::query()->firstOrFail();

        $response->assertRedirect(route('invoices.show', $invoice));

        $this->assertSame('PO-7788', $invoice->po_number);
        $this->assertSame('Net 30', $invoice->terms);
        $this->assertSame('Warehouse 4', $invoice->ship_to);
        $this->assertSame('Thanks for your business.', $invoice->customer_message);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);

        // Totals come from the shared calculator, not the form.
        $this->assertSame(1000.00, (float) $invoice->subtotal);
        $this->assertSame(150.00, (float) $invoice->vat_total);
        $this->assertSame(1150.00, (float) $invoice->total);
        $this->assertCount(1, $invoice->items);
    }

    public function test_an_issued_invoice_posts_to_the_ledger(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $this->actingAs($this->admin())->post(route('invoices.store'), [
            'client_id' => $this->client()->id,
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'taxable' => 1],
            ],
        ]);

        $invoice = Invoice::query()->firstOrFail();

        $this->assertNotNull($invoice->issued_at);

        $entry = JournalEntry::query()->where('source_type', Invoice::class)->where('source_id', $invoice->id)->first();

        $this->assertNotNull($entry, 'Issued invoice did not post to the ledger.');
        $this->assertTrue($entry->isBalanced());
    }

    public function test_a_draft_invoice_does_not_post_to_the_ledger(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $this->actingAs($this->admin())->post(route('invoices.store'), [
            'client_id' => $this->client()->id,
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'items' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 1000, 'taxable' => 1],
            ],
        ]);

        $this->assertSame(0, JournalEntry::query()->count());
    }

    public function test_the_printed_invoice_uses_the_quickbooks_layout(): void
    {
        $this->actingAs($this->admin())->post(route('invoices.store'), [
            'client_id' => $this->client()->id,
            'status' => Invoice::STATUS_DRAFT,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'po_number' => 'PO-PRINT-1',
            'customer_message' => 'Printed message here',
            'items' => [
                ['description' => 'Printed line', 'quantity' => 1, 'unit_price' => 100, 'taxable' => 1],
            ],
        ]);

        $invoice = Invoice::query()->firstOrFail();

        $this->actingAs($this->admin())->get(route('invoices.print', $invoice))
            ->assertOk()
            ->assertSee('Customer:Job')
            ->assertSee('Bill To')
            ->assertSee('Ship To')
            ->assertSee('P.O. No.')
            ->assertSee('Customer Message')
            ->assertSee('PO-PRINT-1')
            ->assertSee('Printed message here');
    }

    private function admin(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'invoice.admin@example.com'],
            [
                'name' => 'Invoice Admin',
                'password' => 'password',
                'role' => User::ROLE_BUSINESS_ADMIN,
                'is_active' => true,
            ],
        );
    }

    private function client(): Client
    {
        return Client::query()->firstOrCreate(
            ['client_code' => 'CLT-QB'],
            ['name' => 'QB Client', 'is_active' => true],
        );
    }
}
