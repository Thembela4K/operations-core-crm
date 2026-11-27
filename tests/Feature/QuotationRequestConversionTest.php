<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Quotation;
use App\Models\SalesQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Report 2.1: a client request converts into a Sales Quotation and keeps the
 * originating request reference.
 */
class QuotationRequestConversionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_request_converts_into_a_draft_sales_quotation(): void
    {
        $admin = $this->admin();
        $client = Client::query()->create(['client_code' => 'CLT-REQ', 'name' => 'Request Client', 'is_active' => true]);

        $request = Quotation::query()->create([
            'quotation_code' => 'QR-2026-001',
            'client' => 'Request Client',
            'opportunity' => 'Supply of ten laptops',
            'owner' => 'Convert Admin',
            'owner_email' => 'convert.admin@example.com',
            'status' => Quotation::STATUSES[0],
            'priority' => 'Medium',
            'risk' => 'Low',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('quotations.convert', $request))
            ->assertRedirect();

        $salesQuotation = SalesQuotation::query()->firstOrFail();

        $this->assertSame($request->id, $salesQuotation->source_quotation_request_id);
        $this->assertSame('Supply of ten laptops', $salesQuotation->title);
        $this->assertSame(SalesQuotation::STATUS_DRAFT, $salesQuotation->status);
        $this->assertSame($client->id, $salesQuotation->client_id);
        $this->assertStringStartsWith('QUO-', $salesQuotation->quotation_number);
    }

    public function test_one_request_may_produce_several_quotation_versions(): void
    {
        $admin = $this->admin();
        Client::query()->create(['client_code' => 'CLT-VER', 'name' => 'Unmatched Client', 'is_active' => true]);

        $request = Quotation::query()->create([
            'quotation_code' => 'QR-2026-002',
            'client' => 'Unmatched Client',
            'opportunity' => 'Versions',
            'owner' => 'Convert Admin',
            'owner_email' => 'convert.admin@example.com',
            'status' => Quotation::STATUSES[0],
            'priority' => 'Medium',
            'risk' => 'Low',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('quotations.convert', $request))->assertRedirect();
        $this->actingAs($admin)->post(route('quotations.convert', $request))->assertRedirect();

        $this->assertSame(2, SalesQuotation::query()->where('source_quotation_request_id', $request->id)->count());
    }

    private function admin(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'convert.admin@example.com'],
            [
                'name' => 'Convert Admin',
                'password' => 'password',
                'role' => User::ROLE_BUSINESS_ADMIN,
                'is_active' => true,
            ],
        );
    }
}
