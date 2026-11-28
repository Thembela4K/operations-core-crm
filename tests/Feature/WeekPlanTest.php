<?php

namespace Tests\Feature;

use App\Models\CrmTask;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The Tasks module is presented as the Week Plan. Its linked-record list must
 * offer the projects the employee is actually working on, and assigners must
 * see every live project so they can allocate work.
 */
class WeekPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_only_sees_their_own_projects_in_progress(): void
    {
        $department = $this->department('MIS Department');
        $employee = $this->user('Samkelo', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER, $department);
        $colleague = $this->user('Colleague', 'colleague@example.com', User::ROLE_DEPARTMENT_USER, $department);

        $mine = $this->project($employee, 'Unipod deliverable three', Project::STATUS_IN_PROGRESS);
        $theirs = $this->project($colleague, 'Colleague private project', Project::STATUS_IN_PROGRESS);
        $finished = $this->project($employee, 'Finished project alpha', Project::STATUS_COMPLETED);

        $response = $this->actingAs($employee)->get(route('tasks.create'));

        $response->assertOk()
            ->assertSee('Week Plan')
            ->assertSee('Projects in Progress')
            ->assertSee($mine->title)
            ->assertDontSee($theirs->title)
            ->assertDontSee($finished->title);
    }

    public function test_assigner_sees_every_project_in_progress(): void
    {
        $department = $this->department('MIS Department');
        $employee = $this->user('Samkelo', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER, $department);
        $reception = $this->user('Reception', 'reception@example.com', User::ROLE_RECEPTION, $department);

        $mine = $this->project($employee, 'Employee project one', Project::STATUS_IN_PROGRESS);
        $other = $this->project($employee, 'Employee project two', Project::STATUS_IN_PROGRESS);
        $finished = $this->project($employee, 'Closed project beta', Project::STATUS_COMPLETED);

        $this->actingAs($reception)->get(route('tasks.create'))
            ->assertOk()
            ->assertSee($mine->title)
            ->assertSee($other->title)
            ->assertDontSee($finished->title);
    }

    public function test_an_entry_can_be_linked_to_a_project(): void
    {
        $department = $this->department('MIS Department');
        $employee = $this->user('Samkelo', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER, $department);
        $project = $this->project($employee, 'Linked week plan project', Project::STATUS_IN_PROGRESS);

        $this->actingAs($employee)->post(route('tasks.store'), [
            'title' => 'Draft the assessment',
            'status' => 'To Do',
            'priority' => 'Medium',
            'assigned_to' => $employee->id,
            'related_record' => 'project:'.$project->id,
        ])->assertRedirect();

        $task = CrmTask::query()->firstOrFail();

        $this->assertSame(Project::class, $task->taskable_type);
        $this->assertSame($project->id, $task->taskable_id);
    }

    private function project(User $owner, string $title, string $status): Project
    {
        return Project::query()->create([
            'user_id' => $owner->id,
            'department_id' => $owner->department_id,
            'title' => $title,
            'status' => $status,
            'progress_percent' => $status === Project::STATUS_COMPLETED ? 100 : 40,
        ]);
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
