<?php

namespace Tests\Feature;

use App\Models\TenderProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Report 3.2: an ESPPRA listing is a *source*, and the notice must still be
 * classified by its actual procurement type.
 */
class TenderClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_notice_records_both_its_source_and_its_procurement_type(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('tender-proposals.store'), [
            'tender_reference' => 'ESPPRA/2026/001',
            'title' => 'Supply of network equipment',
            'procurement_type' => 'Request for Quotation',
            'notice_source' => 'ESPPRA',
            'closing_date' => now()->addDays(14)->toDateString(),
            'tender_document' => \Illuminate\Http\UploadedFile::fake()->create('tender.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $tender = TenderProposal::query()->firstOrFail();

        $this->assertSame('Request for Quotation', $tender->procurement_type);
        $this->assertSame('ESPPRA', $tender->notice_source);
    }

    public function test_an_unknown_procurement_type_is_rejected(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $this->actingAs($this->admin())->post(route('tender-proposals.store'), [
            'tender_reference' => 'ESPPRA/2026/002',
            'title' => 'Invalid classification',
            'procurement_type' => 'ESPPRA',
            'closing_date' => now()->addDays(14)->toDateString(),
            'tender_document' => \Illuminate\Http\UploadedFile::fake()->create('tender.pdf', 120, 'application/pdf'),
        ])->assertSessionHasErrors('procurement_type');

        $this->assertSame(0, TenderProposal::query()->count());
    }

    private function admin(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'tender.admin@example.com'],
            [
                'name' => 'Tender Admin',
                'password' => 'password',
                'role' => User::ROLE_BUSINESS_ADMIN,
                'is_active' => true,
            ],
        );
    }
}
