<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DeliveryNote;
use App\Models\JobCard;
use App\Models\SalesQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Report acceptance criterion: "A Job Card can have multiple Delivery Notes,
 * including partial deliveries."
 */
class PartialDeliveryAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_job_card_can_have_several_delivery_notes(): void
    {
        $admin = $this->admin();
        $jobCard = $this->jobCard($admin);

        foreach (['First partial delivery', 'Second partial delivery'] as $index => $note) {
            $this->actingAs($admin)->post(route('delivery-notes.store'), [
                'job_card_id' => $jobCard->id,
                'delivery_date' => now()->addDays($index)->toDateString(),
                'recipient_name' => 'Site Receiver',
                'notes' => $note,
            ])->assertRedirect();
        }

        $this->assertSame(2, DeliveryNote::query()->count());
        $this->assertSame(2, $jobCard->deliveryNotes()->count());
    }

    public function test_delivery_notes_can_be_marked_delivered_independently(): void
    {
        $admin = $this->admin();
        $jobCard = $this->jobCard($admin);

        $this->actingAs($admin)->post(route('delivery-notes.store'), [
            'job_card_id' => $jobCard->id,
            'delivery_date' => now()->toDateString(),
            'recipient_name' => 'Site Receiver',
        ])->assertRedirect();

        $note = DeliveryNote::query()->firstOrFail();

        $this->assertSame(DeliveryNote::STATUS_DRAFT, $note->status);

        $this->actingAs($admin)
            ->post(route('delivery-notes.delivered', $note))
            ->assertRedirect();

        $this->assertNotNull($note->fresh()->delivered_at);
    }

    private function jobCard(User $admin): JobCard
    {
        $client = Client::query()->firstOrCreate(
            ['client_code' => 'CLT-DEL'],
            ['name' => 'Delivery Client', 'is_active' => true],
        );

        $quotation = SalesQuotation::query()->create([
            'client_id' => $client->id,
            'quotation_number' => 'QUO-DEL-1',
            'title' => 'Delivery work',
            'status' => SalesQuotation::STATUS_ACCEPTED,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subtotal' => 1000,
            'discount_total' => 0,
            'vat_total' => 150,
            'total' => 1150,
        ]);

        return JobCard::query()->create([
            'sales_quotation_id' => $quotation->id,
            'client_id' => $client->id,
            'job_card_number' => 'JOB-DEL-1',
            'title' => 'Install and deliver',
            'status' => JobCard::STATUS_IN_PROGRESS,
            'created_by' => $admin->id,
        ]);
    }

    private function admin(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'delivery.admin@example.com'],
            [
                'name' => 'Delivery Admin',
                'password' => 'password',
                'role' => User::ROLE_BUSINESS_ADMIN,
                'is_active' => true,
            ],
        );
    }
}
