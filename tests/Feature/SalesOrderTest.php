<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\JobCard;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sales Orders (report 2.4): an accepted quotation becomes one order, and that
 * order may be fulfilled by several job cards.
 */
class SalesOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_accepted_quotation_becomes_a_sales_order(): void
    {
        $admin = $this->admin();
        $quotation = $this->acceptedQuotation();

        $this->actingAs($admin)->post(route('sales-orders.store'), [
            'sales_quotation_id' => $quotation->id,
            'client_id' => $quotation->client_id,
            'title' => 'Network rollout',
            'status' => SalesOrder::STATUS_CONFIRMED,
            'client_po_reference' => 'PO-CLIENT-88',
            'value' => 11500,
            'order_date' => now()->toDateString(),
            'delivery_date' => now()->addDays(20)->toDateString(),
        ])->assertRedirect();

        $order = SalesOrder::query()->firstOrFail();

        $this->assertSame($quotation->id, $order->sales_quotation_id);
        $this->assertSame('PO-CLIENT-88', $order->client_po_reference);
        $this->assertStringStartsWith('SO-', $order->order_number);
        $this->assertSame(11500.00, (float) $order->value);
    }

    public function test_a_quotation_cannot_produce_two_sales_orders(): void
    {
        $admin = $this->admin();
        $quotation = $this->acceptedQuotation();

        $payload = [
            'sales_quotation_id' => $quotation->id,
            'client_id' => $quotation->client_id,
            'title' => 'First order',
            'status' => SalesOrder::STATUS_CONFIRMED,
            'value' => 1000,
        ];

        $this->actingAs($admin)->post(route('sales-orders.store'), $payload)->assertRedirect();

        $this->actingAs($admin)
            ->post(route('sales-orders.store'), $payload + ['title' => 'Duplicate order'])
            ->assertStatus(422);

        $this->assertSame(1, SalesOrder::query()->count());
    }

    public function test_a_sales_order_can_be_fulfilled_by_several_job_cards(): void
    {
        $admin = $this->admin();
        $quotation = $this->acceptedQuotation();

        $this->actingAs($admin)->post(route('sales-orders.store'), [
            'sales_quotation_id' => $quotation->id,
            'client_id' => $quotation->client_id,
            'title' => 'Staged delivery order',
            'status' => SalesOrder::STATUS_CONFIRMED,
            'value' => 9000,
        ])->assertRedirect();

        $order = SalesOrder::query()->firstOrFail();

        foreach (['Phase one', 'Phase two'] as $title) {
            $this->actingAs($admin)->post(route('job-cards.store'), [
                'sales_quotation_id' => $quotation->id,
                'sales_order_id' => $order->id,
                'title' => $title,
                'start_date' => now()->toDateString(),
                'due_date' => now()->addDays(10)->toDateString(),
            ])->assertRedirect();
        }

        $this->assertSame(2, JobCard::query()->count());
        $this->assertSame(2, $order->fresh()->jobCards()->count());
    }

    public function test_a_closed_sales_order_rejects_new_job_cards(): void
    {
        $admin = $this->admin();
        $quotation = $this->acceptedQuotation();

        $this->actingAs($admin)->post(route('sales-orders.store'), [
            'sales_quotation_id' => $quotation->id,
            'client_id' => $quotation->client_id,
            'title' => 'Closing order',
            'status' => SalesOrder::STATUS_CLOSED,
            'value' => 500,
        ])->assertRedirect();

        $order = SalesOrder::query()->firstOrFail();

        $this->actingAs($admin)->post(route('job-cards.store'), [
            'sales_quotation_id' => $quotation->id,
            'sales_order_id' => $order->id,
            'title' => 'Should not exist',
        ])->assertRedirect(route('sales-orders.show', $order));

        $this->assertSame(0, JobCard::query()->count());
    }

    public function test_the_order_page_lists_its_job_cards(): void
    {
        $admin = $this->admin();
        $quotation = $this->acceptedQuotation();

        $this->actingAs($admin)->post(route('sales-orders.store'), [
            'sales_quotation_id' => $quotation->id,
            'client_id' => $quotation->client_id,
            'title' => 'Visible order',
            'status' => SalesOrder::STATUS_CONFIRMED,
            'value' => 1200,
        ]);

        $order = SalesOrder::query()->firstOrFail();

        $this->actingAs($admin)->post(route('job-cards.store'), [
            'sales_quotation_id' => $quotation->id,
            'sales_order_id' => $order->id,
            'title' => 'Installation phase',
        ]);

        $jobCard = JobCard::query()->firstOrFail();

        $this->actingAs($admin)->get(route('sales-orders.show', $order))
            ->assertOk()
            ->assertSee('Visible order')
            ->assertSee($jobCard->job_card_number);
    }

    public function test_a_standalone_order_can_exist_without_a_quotation(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)->post(route('sales-orders.store'), [
            'client_id' => $client->id,
            'title' => 'Direct award',
            'status' => SalesOrder::STATUS_DRAFT,
            'value' => 750,
        ])->assertRedirect();

        $order = SalesOrder::query()->firstOrFail();

        $this->assertNull($order->sales_quotation_id);
        $this->assertSame($client->id, $order->client_id);
    }

    private function admin(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'so.admin@example.com'],
            [
                'name' => 'Sales Order Admin',
                'password' => 'password',
                'role' => User::ROLE_BUSINESS_ADMIN,
                'is_active' => true,
            ],
        );
    }

    private function client(): Client
    {
        return Client::query()->firstOrCreate(
            ['client_code' => 'CLT-SO'],
            ['name' => 'Sales Order Client', 'is_active' => true],
        );
    }

    private function acceptedQuotation(): SalesQuotation
    {
        return SalesQuotation::query()->create([
            'client_id' => $this->client()->id,
            'quotation_number' => 'SQ-TEST-'.uniqid(),
            'title' => 'Quoted opportunity',
            'status' => SalesQuotation::STATUS_ACCEPTED,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subtotal' => 10000,
            'discount_total' => 0,
            'vat_total' => 1500,
            'total' => 11500,
        ]);
    }
}
