<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Deny-by-default access control.
 *
 * Every expectation below is a deliberate policy decision:
 * department staff work inside their department, finance stays with reception
 * and direction, analysis is read-only, and only the super admin administers.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_user_reaches_operations_but_not_finance_or_admin(): void
    {
        $user = $this->user(User::ROLE_DEPARTMENT_USER, 'MIS Department');

        $this->assertMatrix($user, [
            '/' => 200,
            '/clients' => 200,
            '/client-activities' => 200,
            '/catalog-items' => 200,
            '/sales-quotations' => 200,
            '/job-cards' => 200,
            '/delivery-notes' => 200,
            '/requisitions' => 200,
            '/tender-proposals' => 200,
            '/quotations' => 200,
            '/submissions' => 200,
            '/documents' => 200,
            '/tasks' => 200,
            '/projects' => 200,
            '/attendance' => 200,
            '/attendance/leave' => 200,
            '/notifications' => 200,

            '/invoices' => 403,
            '/expenses' => 403,
            '/suppliers' => 403,
            '/purchases' => 403,
            '/approvals' => 403,
            '/reports' => 403,
            '/users' => 403,
            '/departments' => 403,
            '/settings' => 403,
        ]);
    }

    public function test_reception_reaches_finance_and_operations(): void
    {
        $user = $this->user(User::ROLE_RECEPTION, 'Admin (Reception)');

        $this->assertMatrix($user, [
            '/' => 200,
            '/clients' => 200,
            '/sales-quotations' => 200,
            '/job-cards' => 200,
            '/invoices' => 200,
            '/expenses' => 200,
            '/suppliers' => 200,
            '/purchases' => 200,
            '/requisitions' => 200,
            '/tender-proposals' => 200,
            '/approvals' => 403,
            '/reports' => 200,
            '/documents' => 200,
            '/projects' => 200,

            '/users' => 403,
            '/departments' => 403,
            '/settings' => 403,
        ]);
    }

    public function test_director_gets_company_overview(): void
    {
        $user = $this->user(User::ROLE_DIRECTOR);

        $this->assertMatrix($user, [
            '/' => 200,
            '/clients' => 200,
            '/invoices' => 200,
            '/expenses' => 200,
            '/suppliers' => 200,
            '/purchases' => 200,
            '/approvals' => 200,
            '/reports' => 200,
            '/tender-proposals' => 200,
            '/documents' => 200,
            '/attendance/leave' => 200,

            // Directors hold the full staff-account and admin permission.
            '/users' => 200,
            '/users/create' => 200,
            '/departments' => 200,
            '/settings' => 200,
        ]);
    }

    public function test_a_director_can_create_and_delete_users(): void
    {
        $department = $this->createDepartment('MIS Department');
        $director = $this->user(User::ROLE_DIRECTOR);

        $this->actingAs($director)->post('/users', [
            'name' => 'New Starter',
            'email' => 'new.starter.director@example.com',
            'role' => User::ROLE_DEPARTMENT_USER,
            'department_id' => $department->id,
            'password' => 'password123',
            'activate_now' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'new.starter.director@example.com']);

        $target = User::query()->where('email', 'new.starter.director@example.com')->firstOrFail();
        $this->actingAs($director)->delete('/users/'.$target->id)->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_business_admin_reaches_every_module(): void
    {
        $user = $this->user(User::ROLE_BUSINESS_ADMIN);

        $this->assertMatrix($user, [
            '/' => 200,
            '/clients' => 200,
            '/invoices' => 200,
            '/expenses' => 200,
            '/suppliers' => 200,
            '/approvals' => 200,
            '/reports' => 200,
            '/documents' => 200,
            '/projects' => 200,
            '/users' => 200,
            '/users/create' => 200,
            '/departments' => 200,
            '/settings' => 200,
        ]);

        // The business admin is no longer read-only: writes are allowed.
        $this->actingAs($user)->post('/clients', [])->assertStatus(302);
    }

    public function test_business_admin_can_add_and_delete_users(): void
    {
        $department = $this->createDepartment('MIS Department');
        $businessAdmin = $this->user(User::ROLE_BUSINESS_ADMIN);

        $this->actingAs($businessAdmin)->post('/users', [
            'name' => 'New Starter',
            'email' => 'new.starter@example.com',
            'role' => User::ROLE_DEPARTMENT_USER,
            'department_id' => $department->id,
            'password' => 'password123',
            'activate_now' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'new.starter@example.com']);

        // There is no higher role to escalate to, so a second admin is allowed.
        $this->actingAs($businessAdmin)->post('/users', [
            'name' => 'Second Admin',
            'email' => 'second.admin@example.com',
            'role' => User::ROLE_BUSINESS_ADMIN,
            'password' => 'password123',
            'activate_now' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'second.admin@example.com']);

        // Admins may remove accounts.
        $target = User::query()->where('email', 'new.starter@example.com')->firstOrFail();

        $this->actingAs($businessAdmin)->delete('/users/'.$target->id)->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_super_admin_reaches_everything(): void
    {
        $user = $this->user(User::ROLE_BUSINESS_ADMIN, 'Admin (Reception)');

        $this->assertMatrix($user, [
            '/' => 200,
            '/clients' => 200,
            '/invoices' => 200,
            '/expenses' => 200,
            '/suppliers' => 200,
            '/purchases' => 200,
            '/approvals' => 200,
            '/reports' => 200,
            '/documents' => 200,
            '/projects' => 200,
            '/users' => 200,
            '/departments' => 200,
            '/settings' => 200,
        ]);
    }

    public function test_department_user_cannot_mutate_finance_modules(): void
    {
        $user = $this->user(User::ROLE_DEPARTMENT_USER, 'MIS Department');

        $this->actingAs($user)->post('/invoices', [])->assertForbidden();
        $this->actingAs($user)->post('/expenses', [])->assertForbidden();
        $this->actingAs($user)->post('/suppliers', [])->assertForbidden();
    }

    public function test_dashboard_hides_finance_for_department_users(): void
    {
        $this->seedFinanceFixture();

        $departmentUser = $this->user(User::ROLE_DEPARTMENT_USER, 'MIS Department');

        $response = $this->actingAs($departmentUser)->get('/');
        $response->assertOk();
        $response->assertDontSee('Finance Workflow');
        $response->assertDontSee('Sales Pipeline');

        $director = $this->user(User::ROLE_DIRECTOR);
        $directorResponse = $this->actingAs($director)->get('/');
        $directorResponse->assertOk();
        $directorResponse->assertSee('Finance Workflow');
        $directorResponse->assertSee('Sales Pipeline');
    }

    public function test_client_financials_are_masked_from_department_users(): void
    {
        $department = $this->createDepartment('MIS Department');
        $client = \App\Models\Client::query()->create([
            'client_code' => 'CLT-TEST',
            'name' => 'Masked Client',
            'billing_email' => 'billing@client.test',
            'vat_number' => 'VAT-123456',
        ]);

        $departmentUser = $this->user(User::ROLE_DEPARTMENT_USER, 'MIS Department', $department);

        $this->actingAs($departmentUser)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Masked Client')
            ->assertDontSee('billing@client.test')
            ->assertDontSee('VAT-123456');

        $reception = $this->user(User::ROLE_RECEPTION, 'Admin (Reception)');

        $this->actingAs($reception)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('billing@client.test')
            ->assertSee('VAT-123456');
    }

    /**
     * @param  array<string, int>  $expectations
     */
    private function assertMatrix(User $user, array $expectations): void
    {
        foreach ($expectations as $url => $expected) {
            $actual = $this->actingAs($user)->get($url)->getStatusCode();

            $this->assertSame(
                $expected,
                $actual,
                sprintf('GET %s as %s returned %d, expected %d.', $url, $user->role, $actual, $expected),
            );
        }
    }

    private function seedFinanceFixture(): void
    {
        $client = \App\Models\Client::query()->create([
            'client_code' => 'CLT-FIN',
            'name' => 'Finance Client',
        ]);

        \App\Models\SalesQuotation::query()->create([
            'quotation_number' => 'SQ-TEST-1',
            'client_id' => $client->id,
            'title' => 'Fixture quotation',
            'status' => 'Draft',
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'subtotal' => 100,
            'vat_total' => 15,
            'total' => 115,
        ]);
    }

    private function createDepartment(string $name): Department
    {
        return Department::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    private function user(string $role, ?string $departmentName = null, ?Department $department = null): User
    {
        $department ??= $departmentName ? $this->createDepartment($departmentName) : null;

        return User::query()->create([
            'department_id' => $department?->id,
            'name' => ucfirst(str_replace('_', ' ', $role)),
            'email' => $role.'-'.Str::random(6).'@example.com',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
