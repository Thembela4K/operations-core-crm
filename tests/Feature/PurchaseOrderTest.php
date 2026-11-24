<?php

namespace Tests\Feature;

use App\Models\PurchaseRecord;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Purchase orders mirror the QuickBooks PO form and carry real line items.
 */
class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_po_form_renders_quickbooks_fields(): void
    {
        $this->actingAs($this->admin())->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('Vendor')
            ->assertSee('P.O. Number')
            ->assertSee('Ship To')
            ->assertSee('Expected Date')
            ->assertSee('Order Total');
    }

    public function test_a_purchase_order_stores_line_items_and_totals_them(): void
    {
        $supplier = $this->supplier();

        $response = $this->actingAs($this->admin())->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'title' => 'Office network refresh',
            'status' => PurchaseRecord::STATUS_ORDERED,
            'purchase_date' => now()->toDateString(),
            'terms' => 'Net 30',
            'expected_date' => now()->addDays(30)->toDateString(),
            'ship_to' => 'Head Office',
            'notes' => 'Deliver before month end.',
            'items' => [
                ['description' => 'Network switch', 'quantity' => 2, 'unit_price' => 3500],
                ['description' => 'Installation labour', 'quantity' => 1, 'unit_price' => 1200],
            ],
        ]);

        $purchase = PurchaseRecord::query()->firstOrFail();

        $response->assertRedirect(route('purchases.show', $purchase));

        $this->assertCount(2, $purchase->items);
        $this->assertSame('Net 30', $purchase->terms);
        $this->assertSame('Head Office', $purchase->ship_to);

        // Amount is derived from the lines, not typed in.
        $this->assertSame(8200.00, (float) $purchase->amount);
        $this->assertSame(7000.00, (float) $purchase->items->first()->line_total);
    }

    public function test_editing_replaces_items_and_recalculates_the_total(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('purchases.store'), [
            'supplier_id' => $this->supplier()->id,
            'title' => 'Original order',
            'status' => PurchaseRecord::STATUS_PLANNED,
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Original line', 'quantity' => 1, 'unit_price' => 500],
            ],
        ]);

        $purchase = PurchaseRecord::query()->firstOrFail();
        $this->assertSame(500.00, (float) $purchase->amount);

        $this->actingAs($admin)->put(route('purchases.update', $purchase), [
            'title' => 'Revised order',
            'status' => PurchaseRecord::STATUS_PLANNED,
            'purchase_date' => now()->toDateString(),
            'items' => [
                ['description' => 'Replacement line A', 'quantity' => 3, 'unit_price' => 200],
                ['description' => 'Replacement line B', 'quantity' => 1, 'unit_price' => 100],
            ],
        ]);

        $purchase = $purchase->fresh();

        $this->assertCount(2, $purchase->items);
        $this->assertSame(700.00, (float) $purchase->amount);
        $this->assertSame('Replacement line A', $purchase->items->first()->description);
    }

    public function test_the_printed_po_uses_the_quickbooks_layout(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('purchases.store'), [
            'supplier_id' => $this->supplier()->id,
            'title' => 'Printed order',
            'status' => PurchaseRecord::STATUS_ORDERED,
            'purchase_date' => now()->toDateString(),
            'terms' => 'Net 15',
            'ship_to' => 'Warehouse 7',
            'items' => [
                ['description' => 'Printed line item', 'quantity' => 2, 'unit_price' => 150],
            ],
        ]);

        $purchase = PurchaseRecord::query()->firstOrFail();

        $this->actingAs($admin)->get(route('purchases.print', $purchase))
            ->assertOk()
            ->assertSee('Purchase Order')
            ->assertSee('Vendor')
            ->assertSee('Ship To')
            ->assertSee('P.O. Number')
            ->assertSee('Expected')
            ->assertSee('Printed line item')
            ->assertSee('Warehouse 7')
            ->assertSee('Net 15');
    }

    private function admin(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'po.admin@example.com'],
            [
                'name' => 'PO Admin',
                'password' => 'password',
                'role' => User::ROLE_BUSINESS_ADMIN,
                'is_active' => true,
            ],
        );
    }

    private function supplier(): Supplier
    {
        return Supplier::query()->firstOrCreate(
            ['supplier_code' => 'SUP-PO'],
            ['name' => 'PO Vendor', 'is_active' => true],
        );
    }
}
