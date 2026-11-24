<?php

namespace Tests\Feature;

use App\Models\CrmTask;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Departmental officers add trainees and interns to their own department, and
 * week-plan deadlines appear on the reminders page.
 */
class TeamAndLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_officer_can_add_a_trainee_to_their_own_department(): void
    {
        $department = $this->department('MIS Department');
        $officer = $this->user('Officer Person', 'officer@example.com', $department, User::LEVEL_OFFICER);

        $this->actingAs($officer)->get(route('team.create'))->assertOk();

        $this->actingAs($officer)->post(route('team.store'), [
            'name' => 'New Trainee',
            'email' => 'trainee@example.com',
            'role' => User::ROLE_DEPARTMENT_USER,
            'level' => User::LEVEL_TRAINEE,
            'department_id' => $department->id,
            'password' => 'password123',
            'activate_now' => '1',
        ])->assertRedirect(route('team.create'));

        $this->assertDatabaseHas('users', [
            'email' => 'trainee@example.com',
            'role' => User::ROLE_DEPARTMENT_USER,
            'level' => User::LEVEL_TRAINEE,
            'department_id' => $department->id,
        ]);
    }

    public function test_an_officer_cannot_create_an_officer_or_a_head(): void
    {
        $department = $this->department('MIS Department');
        $officer = $this->user('Officer Person', 'officer@example.com', $department, User::LEVEL_OFFICER);

        $this->actingAs($officer)->post(route('team.store'), [
            'name' => 'Sneaky Officer',
            'email' => 'sneaky@example.com',
            'role' => User::ROLE_DEPARTMENT_USER,
            'level' => User::LEVEL_OFFICER,
            'department_id' => $department->id,
            'password' => 'password123',
            'activate_now' => '1',
        ])->assertSessionHasErrors('level');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_an_officer_cannot_create_a_user_outside_their_department(): void
    {
        $mis = $this->department('MIS Department');
        $finance = $this->department('Finance');
        $officer = $this->user('Officer Person', 'officer@example.com', $mis, User::LEVEL_OFFICER);

        $this->actingAs($officer)->post(route('team.store'), [
            'name' => 'Cross Department',
            'email' => 'cross@example.com',
            'role' => User::ROLE_DEPARTMENT_USER,
            'level' => User::LEVEL_INTERN,
            'department_id' => $finance->id,
            'password' => 'password123',
            'activate_now' => '1',
        ])->assertRedirect();

        // The department submitted is ignored; the officer's own wins.
        $this->assertDatabaseHas('users', [
            'email' => 'cross@example.com',
            'department_id' => $mis->id,
        ]);
    }

    public function test_a_trainee_cannot_add_users(): void
    {
        $department = $this->department('MIS Department');
        $trainee = $this->user('Trainee Person', 'trainee2@example.com', $department, User::LEVEL_TRAINEE);

        $this->actingAs($trainee)->get(route('team.create'))->assertForbidden();
    }

    public function test_a_department_head_can_also_add_team_members(): void
    {
        $department = $this->department('MIS Department');
        $head = $this->user('Head Person', 'head@example.com', $department, User::LEVEL_HEAD);

        $this->actingAs($head)->get(route('team.create'))->assertOk();
    }

    public function test_the_reminders_page_lists_week_plan_deadlines(): void
    {
        $department = $this->department('MIS Department');
        $user = $this->user('Deadline Owner', 'deadline@example.com', $department, User::LEVEL_OFFICER);

        CrmTask::query()->create([
            'task_number' => 'TSK-REMIND-1',
            'title' => 'Intern induction pack',
            'status' => CrmTask::STATUS_IN_PROGRESS,
            'priority' => 'High',
            'department_id' => $department->id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $this->actingAs($user)->get(route('reminders.index'))
            ->assertOk()
            ->assertSee('TSK-REMIND-1')
            ->assertSee('Intern induction pack')
            ->assertSee('Week Plan Entry');
    }

    private function department(string $name): Department
    {
        return Department::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true],
        );
    }

    private function user(string $name, string $email, Department $department, ?string $level = null): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'role' => User::ROLE_DEPARTMENT_USER,
                'level' => $level,
                'department_id' => $department->id,
                'is_active' => true,
            ],
        );
    }
}
