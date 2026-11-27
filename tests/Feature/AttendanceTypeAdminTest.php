<?php

namespace Tests\Feature;

use App\Models\AttendanceType;
use App\Models\User;
use Database\Seeders\AttendanceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Administrators maintain attendance types (report 5.5). Exactly one active
 * default must always exist.
 */
class AttendanceTypeAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AttendanceSeeder::class);
    }

    public function test_an_administrator_can_add_a_type(): void
    {
        $this->actingAs($this->admin())->post(route('attendance-types.store'), [
            'name' => 'Client Site',
            'description' => 'Working at a client location',
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_types', ['name' => 'Client Site', 'is_default' => false]);
    }

    public function test_setting_a_new_default_clears_the_previous_one(): void
    {
        $remote = AttendanceType::query()->where('name', 'Remote')->firstOrFail();

        $this->actingAs($this->admin())->patch(route('attendance-types.update', $remote), [
            'name' => 'Remote',
            'description' => $remote->description,
            'is_default' => 1,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame(1, AttendanceType::query()->where('is_default', true)->count());
        $this->assertSame('Remote', AttendanceType::query()->where('is_default', true)->value('name'));
    }

    public function test_deactivating_the_default_hands_the_role_to_another_active_type(): void
    {
        $office = AttendanceType::query()->where('name', 'Office')->firstOrFail();

        $this->actingAs($this->admin())->patch(route('attendance-types.update', $office), [
            'name' => 'Office',
            'description' => $office->description,
            'is_active' => 0,
        ])->assertRedirect();

        $default = AttendanceType::query()->where('is_default', true)->first();

        $this->assertNotNull($default, 'A default attendance type must always exist.');
        $this->assertTrue($default->is_active);
        $this->assertNotSame('Office', $default->name);
    }

    public function test_a_non_administrator_cannot_manage_types(): void
    {
        $user = User::query()->create([
            'name' => 'Officer',
            'email' => 'officer.att@example.com',
            'password' => 'password',
            'role' => User::ROLE_DEPARTMENT_USER,
            'level' => User::LEVEL_OFFICER,
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('attendance-types.index'))->assertForbidden();
    }

    private function admin(): User
    {
        return User::query()->firstOrCreate(
            ['email' => 'types.admin@example.com'],
            [
                'name' => 'Types Admin',
                'password' => 'password',
                'role' => User::ROLE_BUSINESS_ADMIN,
                'is_active' => true,
            ],
        );
    }
}
