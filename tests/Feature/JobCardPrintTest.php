<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\JobCard;
use App\Models\SalesQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The printed job card follows the company's existing paper layout: customer
 * and bank details, diagnosis, parts used, work done with totals, signature
 * and the administrative footer.
 */
class JobCardPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_job_card_prints_the_company_layout(): void
    {
        $department = Department::query()->create([
            'name' => 'MIS Department',
            'slug' => 'mis-department',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'name' => 'Sibusiso Baartjies',
            'email' => 'printer@example.com',
            'password' => 'password',
            'role' => User::ROLE_BUSINESS_ADMIN,
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        $client = Client::query()->create([
            'client_code' => 'CLT-MOL',
            'name' => 'Ministry of Labour and Social Security',
            'address' => 'P.O. Box 198',
            'city' => 'Mbabane',
            'phone' => '+268 2404 0000',
        ]);

        $quotation = SalesQuotation::query()->create([
            'client_id' => $client->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'quotation_number' => 'SQ-'.strtoupper(Str::random(6)),
            'title' => 'ONLINE SDS TEST SYSTEM',
            'status' => SalesQuotation::STATUS_ACCEPTED,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subtotal' => 626086.96,
            'vat_total' => 93913.04,
            'total' => 720000.00,
        ]);

        $jobCard = JobCard::query()->create([
            'sales_quotation_id' => $quotation->id,
            'client_id' => $client->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'job_card_number' => 'JC-'.strtoupper(Str::random(6)),
            'title' => 'Online SDS Test System',
            'status' => JobCard::STATUS_DRAFT,
            'scope' => 'Development, delivery and installation of the online SDS Test System.',
            'delivery_required' => false,
        ]);

        $page = $this->actingAs($user)->get(route('job-cards.print', $jobCard));

        $page->assertOk()
            ->assertSee('JOB CARD')
            ->assertSee('Ministry of Labour and Social Security')
            ->assertSee('Standard Bank Swaziland')
            ->assertSee('9110003207628')
            ->assertSee('SBICSZMX')
            ->assertSee('DIAGNOSIS')
            ->assertSee('PARTS USED')
            ->assertSee('WORK DONE')
            ->assertSee('626,086.96')
            ->assertSee('93,913.04')
            ->assertSee('720,000.00')
            ->assertSee('COMPLETION ACKNOWLEDGEMENT')
            ->assertSee('PREPARED BY');
    }

    public function test_a_stranger_cannot_print_someone_elses_job_card(): void
    {
        $department = Department::query()->create([
            'name' => 'GIS Department',
            'slug' => 'gis-department',
            'is_active' => true,
        ]);

        $owner = User::query()->create([
            'name' => 'Owner',
            'email' => 'owner.print@example.com',
            'password' => 'password',
            'role' => User::ROLE_BUSINESS_ADMIN,
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        $stranger = User::query()->create([
            'name' => 'Stranger',
            'email' => 'stranger.print@example.com',
            'password' => 'password',
            'role' => User::ROLE_DEPARTMENT_USER,
            'level' => User::LEVEL_OFFICER,
            'department_id' => Department::query()->create([
                'name' => 'Finance Department',
                'slug' => 'finance-department',
                'is_active' => true,
            ])->id,
            'is_active' => true,
        ]);

        $client = Client::query()->create(['client_code' => 'CLT-X', 'name' => 'Client X']);

        $quotation = SalesQuotation::query()->create([
            'client_id' => $client->id,
            'department_id' => $department->id,
            'created_by' => $owner->id,
            'quotation_number' => 'SQ-'.strtoupper(Str::random(6)),
            'title' => 'Scope',
            'status' => SalesQuotation::STATUS_ACCEPTED,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'subtotal' => 100,
            'vat_total' => 15,
            'total' => 115,
        ]);

        $jobCard = JobCard::query()->create([
            'sales_quotation_id' => $quotation->id,
            'client_id' => $client->id,
            'department_id' => $department->id,
            'created_by' => $owner->id,
            'job_card_number' => 'JC-'.strtoupper(Str::random(6)),
            'title' => 'Scope',
            'status' => JobCard::STATUS_DRAFT,
            'delivery_required' => false,
        ]);

        $this->actingAs($stranger)->get(route('job-cards.print', $jobCard))->assertForbidden();
    }
}
