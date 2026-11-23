<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_activated_user_with_a_password(): void
    {
        $admin = $this->user('Admin', 'admin@example.com', User::ROLE_SUPER_ADMIN);
        $department = $this->department('MIS Department');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Samkelo Oneal',
            'username' => 'samkelo.oneal',
            'email' => 'samkellooneal0007@gmail.com',
            'job_title' => 'MIS Trainee',
            'employee_no' => 'DAT-014',
            'role' => User::ROLE_DEPARTMENT_USER,
            'department_id' => $department->id,
            'password' => 'Samkelo@2026',
            'activate_now' => '1',
        ])->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'samkellooneal0007@gmail.com')->firstOrFail();

        $this->assertSame('MIS Trainee', $user->job_title);
        $this->assertSame('DAT-014', $user->employee_no);
        $this->assertSame($department->id, $user->department_id);
        $this->assertSame(User::ROLE_DEPARTMENT_USER, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->invitation_accepted_at);
        $this->assertTrue(Hash::check('Samkelo@2026', $user->password));

        // No invitation email is needed on this path.
        $this->assertDatabaseCount('user_invitations', 0);

        // The credentials actually work.
        $this->assertTrue(auth()->attempt([
            'email' => 'samkellooneal0007@gmail.com',
            'password' => 'Samkelo@2026',
        ]));
    }

    public function test_default_path_creates_an_inactive_user_and_sends_an_invitation(): void
    {
        $admin = $this->user('Admin', 'admin@example.com', User::ROLE_SUPER_ADMIN);
        $department = $this->department('MIS Department');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Invited Person',
            'email' => 'invited@example.com',
            'role' => User::ROLE_DEPARTMENT_USER,
            'department_id' => $department->id,
        ])->assertRedirect(route('users.index'));

        $user = User::query()->where('email', 'invited@example.com')->firstOrFail();

        $this->assertFalse($user->is_active);
        $this->assertNull($user->invitation_accepted_at);
        $this->assertDatabaseHas('user_invitations', [
            'user_id' => $user->id,
            'status' => UserInvitation::STATUS_PENDING,
        ]);
    }

    public function test_activate_now_requires_a_password(): void
    {
        $admin = $this->user('Admin', 'admin@example.com', User::ROLE_SUPER_ADMIN);
        $department = $this->department('MIS Department');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'No Password',
            'email' => 'nopassword@example.com',
            'role' => User::ROLE_DEPARTMENT_USER,
            'department_id' => $department->id,
            'activate_now' => '1',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'nopassword@example.com']);
    }

    public function test_department_user_role_still_requires_a_department(): void
    {
        $admin = $this->user('Admin', 'admin@example.com', User::ROLE_SUPER_ADMIN);

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'No Department',
            'email' => 'nodept@example.com',
            'role' => User::ROLE_DEPARTMENT_USER,
            'password' => 'password123',
            'activate_now' => '1',
        ])->assertSessionHasErrors('department_id');
    }

    private function department(string $name): Department
    {
        return Department::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    private function user(string $name, string $email, string $role, ?Department $department = null): User
    {
        return User::query()->create([
            'department_id' => $department?->id,
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
