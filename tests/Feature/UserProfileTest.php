<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User Information: employees maintain their own details; the business admin
 * and directors can see and maintain everyone's.
 */
class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_employee_can_open_and_save_their_own_information(): void
    {
        $user = $this->user('Employee', 'employee@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($user)->get(route('profile.information'))
            ->assertOk()
            ->assertSee('My Information');

        $this->actingAs($user)->put(route('profile.information.update'), [
            'phone' => '(+268) 7600 0000',
            'personal_email' => 'employee.personal@example.com',
            'city' => 'Mbabane',
            'region' => 'Hhohho',
            'bank_name' => 'Standard Bank',
            'bank_account_number' => '123456789',
            'bank_account_type' => 'Savings',
        ])->assertRedirect();

        $profile = $user->fresh()->profile;

        $this->assertSame('(+268) 7600 0000', $profile->phone);
        $this->assertSame('Mbabane', $profile->city);
        $this->assertSame('Standard Bank', $profile->bank_name);
    }

    public function test_an_employee_cannot_open_another_employees_information(): void
    {
        $employee = $this->user('Employee', 'employee@example.com', User::ROLE_DEPARTMENT_USER);
        $other = $this->user('Other', 'other@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($employee)
            ->get(route('user-information.show', $other))
            ->assertForbidden();

        $this->actingAs($employee)
            ->put(route('user-information.update', $other), ['phone' => '000'])
            ->assertForbidden();
    }

    public function test_a_business_admin_can_see_and_update_any_employees_information(): void
    {
        $admin = $this->user('Admin', 'admin.profile@example.com', User::ROLE_BUSINESS_ADMIN);
        $employee = $this->user('Employee', 'employee2@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($admin)->get(route('user-information.index'))
            ->assertOk()
            ->assertSee('Employee');

        $this->actingAs($admin)->get(route('user-information.show', $employee))
            ->assertOk()
            ->assertSee('Employee');

        $this->actingAs($admin)->put(route('user-information.update', $employee), [
            'phone' => '(+268) 1111 2222',
            'notes' => 'Verified against the ID copy.',
        ])->assertRedirect();

        $this->assertSame('(+268) 1111 2222', $employee->fresh()->profile->phone);
    }

    public function test_a_director_can_see_and_update_any_employees_information(): void
    {
        $director = $this->user('Director', 'director.profile@example.com', User::ROLE_DIRECTOR);
        $employee = $this->user('Employee', 'employee3@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($director)->get(route('user-information.index'))->assertOk();

        $this->actingAs($director)->put(route('user-information.update', $employee), [
            'bank_name' => 'Nedbank',
        ])->assertRedirect();

        $this->assertSame('Nedbank', $employee->fresh()->profile->bank_name);
    }

    public function test_a_regular_employee_cannot_open_the_staff_information_list(): void
    {
        $employee = $this->user('Employee', 'employee4@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($employee)->get(route('user-information.index'))->assertForbidden();
    }

    public function test_personal_email_must_be_valid(): void
    {
        $user = $this->user('Employee', 'employee5@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($user)
            ->put(route('profile.information.update'), ['personal_email' => 'not-an-email'])
            ->assertSessionHasErrors('personal_email');
    }

    private function user(string $name, string $email, string $role): User
    {
        $department = Department::query()->firstOrCreate(
            ['slug' => 'mis-department'],
            ['name' => 'MIS Department', 'is_active' => true],
        );

        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'department_id' => $department->id,
            'is_active' => true,
        ]);
    }
}
