<?php

namespace Tests\Feature;

use App\Models\CrmTask;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A departmental officer supervises their own department's week plan, so they
 * can close a colleague's task. A trainee cannot.
 */
class TaskStatusPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_officer_can_update_a_colleagues_task_in_their_department(): void
    {
        $department = $this->department('MIS Department');
        $owner = $this->user('Task Owner', 'owner@example.com', $department, User::LEVEL_TRAINEE);
        $officer = $this->user('Officer Person', 'officer@example.com', $department, User::LEVEL_OFFICER);
        $task = $this->task($owner, $department);

        $this->actingAs($officer)
            ->post(route('tasks.status', $task), ['status' => CrmTask::STATUS_DONE])
            ->assertRedirect();

        $this->assertSame(CrmTask::STATUS_DONE, $task->fresh()->status);
    }

    public function test_a_trainee_cannot_update_a_colleagues_task(): void
    {
        $department = $this->department('MIS Department');
        $owner = $this->user('Task Owner', 'owner@example.com', $department, User::LEVEL_OFFICER);
        $trainee = $this->user('Trainee Person', 'trainee@example.com', $department, User::LEVEL_TRAINEE);
        $task = $this->task($owner, $department);

        $this->actingAs($trainee)
            ->post(route('tasks.status', $task), ['status' => CrmTask::STATUS_DONE])
            ->assertForbidden();

        $this->assertSame(CrmTask::STATUS_IN_PROGRESS, $task->fresh()->status);
    }

    public function test_an_officer_cannot_update_another_departments_task(): void
    {
        $mis = $this->department('MIS Department');
        $finance = $this->department('Finance');
        $owner = $this->user('Finance Owner', 'finance.owner@example.com', $finance, User::LEVEL_TRAINEE);
        $officer = $this->user('MIS Officer', 'mis.officer@example.com', $mis, User::LEVEL_OFFICER);
        $task = $this->task($owner, $finance);

        $this->actingAs($officer)
            ->post(route('tasks.status', $task), ['status' => CrmTask::STATUS_DONE])
            ->assertForbidden();
    }

    public function test_an_assignee_can_always_update_their_own_task(): void
    {
        $department = $this->department('MIS Department');
        $trainee = $this->user('Trainee Person', 'trainee2@example.com', $department, User::LEVEL_TRAINEE);
        $task = $this->task($trainee, $department);

        $this->actingAs($trainee)
            ->post(route('tasks.status', $task), ['status' => CrmTask::STATUS_DONE])
            ->assertRedirect();

        $this->assertSame(CrmTask::STATUS_DONE, $task->fresh()->status);
    }

    private function task(User $owner, Department $department): CrmTask
    {
        return CrmTask::query()->create([
            'task_number' => 'TSK-PERM-'.uniqid(),
            'title' => 'Department work',
            'status' => CrmTask::STATUS_IN_PROGRESS,
            'priority' => 'Normal',
            'department_id' => $department->id,
            'assigned_to' => $owner->id,
            'created_by' => $owner->id,
            'due_date' => now()->addDays(3)->toDateString(),
        ]);
    }

    private function department(string $name): Department
    {
        return Department::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true],
        );
    }

    private function user(string $name, string $email, Department $department, string $level): User
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
