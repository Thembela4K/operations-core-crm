<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_add_a_project(): void
    {
        $department = $this->department('MIS Department');
        $employee = $this->user('Samkelo', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER, $department);

        $this->actingAs($employee)->post(route('projects.store'), [
            'title' => 'Develop Unipod deliverable 3 first draft',
            'status' => Project::STATUS_IN_PROGRESS,
            'progress_percent' => 60,
            'comment' => 'Draft submitted to Thembela for review.',
            'planned_date' => '2026-11-09',
            'by_whom' => 'Samkelo',
            'time_frame' => '1 Day',
        ])->assertRedirect();

        $project = Project::query()->firstOrFail();

        $this->assertSame($employee->id, $project->user_id);
        $this->assertSame($department->id, $project->department_id);
        $this->assertSame(60, $project->progress_percent);
        $this->assertSame(Project::STATUS_IN_PROGRESS, $project->status);
        $this->assertSame('2026-11-09', $project->planned_date->toDateString());
        $this->assertNull($project->completed_at);
    }

    public function test_employees_only_see_their_own_projects(): void
    {
        $one = $this->user('One User', 'one@example.com', User::ROLE_DEPARTMENT_USER);
        $two = $this->user('Two User', 'two@example.com', User::ROLE_DEPARTMENT_USER);

        $mine = Project::query()->create([
            'user_id' => $one->id, 'title' => 'One private project',
            'status' => Project::STATUS_IN_PROGRESS, 'progress_percent' => 10,
        ]);
        Project::query()->create([
            'user_id' => $two->id, 'title' => 'Two private project',
            'status' => Project::STATUS_IN_PROGRESS, 'progress_percent' => 10,
        ]);

        $this->actingAs($one)->get(route('projects.index'))
            ->assertOk()
            ->assertSee('One private project')
            ->assertDontSee('Two private project');

        $this->actingAs($two)->get(route('projects.show', $mine))->assertForbidden();
    }

    public function test_management_sees_projects_grouped_under_each_employee(): void
    {
        $director = $this->user('Director', 'director@example.com', User::ROLE_DIRECTOR);
        $one = $this->user('Samkelo Oneal', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER);
        $two = $this->user('Thembela Mthimkhulu', 'thembela@example.com', User::ROLE_DEPARTMENT_USER);

        Project::query()->create([
            'user_id' => $one->id, 'title' => 'Unipod deliverable 3',
            'status' => Project::STATUS_COMPLETED, 'progress_percent' => 100,
        ]);
        Project::query()->create([
            'user_id' => $two->id, 'title' => 'EWADE questionnaires',
            'status' => Project::STATUS_IN_PROGRESS, 'progress_percent' => 40,
        ]);

        $this->actingAs($director)->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Samkelo Oneal')
            ->assertSee('Thembela Mthimkhulu')
            ->assertSee('Unipod deliverable 3')
            ->assertSee('EWADE questionnaires');
    }

    public function test_completing_a_project_sets_full_progress_and_stamp(): void
    {
        $employee = $this->user('Samkelo', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($employee)->post(route('projects.store'), [
            'title' => 'Finish report',
            'status' => Project::STATUS_COMPLETED,
            'progress_percent' => 45,
        ])->assertRedirect();

        $project = Project::query()->firstOrFail();

        $this->assertSame(100, $project->progress_percent);
        $this->assertNotNull($project->completed_at);
    }

    public function test_reopening_a_project_clears_the_completion_stamp(): void
    {
        $employee = $this->user('Samkelo', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER);

        $project = Project::query()->create([
            'user_id' => $employee->id, 'title' => 'Reopen me',
            'status' => Project::STATUS_COMPLETED, 'progress_percent' => 100,
            'completed_at' => now(),
        ]);

        $this->actingAs($employee)->put(route('projects.update', $project), [
            'title' => 'Reopen me',
            'status' => Project::STATUS_IN_PROGRESS,
            'progress_percent' => 70,
        ])->assertRedirect();

        $project->refresh();

        $this->assertSame(Project::STATUS_IN_PROGRESS, $project->status);
        $this->assertSame(70, $project->progress_percent);
        $this->assertNull($project->completed_at);
    }

    public function test_employee_cannot_edit_another_employees_project(): void
    {
        $owner = $this->user('Owner', 'owner@example.com', User::ROLE_DEPARTMENT_USER);
        $other = $this->user('Other', 'other@example.com', User::ROLE_DEPARTMENT_USER);

        $project = Project::query()->create([
            'user_id' => $owner->id, 'title' => 'Owner project',
            'status' => Project::STATUS_IN_PROGRESS, 'progress_percent' => 10,
        ]);

        $this->actingAs($other)->get(route('projects.edit', $project))->assertForbidden();
        $this->actingAs($other)->put(route('projects.update', $project), [
            'title' => 'Hijacked',
            'status' => Project::STATUS_CANCELLED,
            'progress_percent' => 0,
        ])->assertForbidden();
    }

    public function test_super_admin_can_create_a_project_for_another_employee(): void
    {
        $admin = $this->user('Admin', 'admin@example.com', User::ROLE_BUSINESS_ADMIN);
        $employee = $this->user('Samkelo', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($admin)->post(route('projects.store'), [
            'user_id' => $employee->id,
            'title' => 'Assigned by admin',
            'status' => Project::STATUS_NOT_STARTED,
            'progress_percent' => 0,
        ])->assertRedirect();

        $this->assertSame($employee->id, Project::query()->firstOrFail()->user_id);
    }

    public function test_progress_must_be_between_zero_and_one_hundred(): void
    {
        $employee = $this->user('Samkelo', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($employee)->post(route('projects.store'), [
            'title' => 'Bad progress',
            'status' => Project::STATUS_IN_PROGRESS,
            'progress_percent' => 140,
        ])->assertSessionHasErrors('progress_percent');

        $this->assertDatabaseCount('projects', 0);
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
