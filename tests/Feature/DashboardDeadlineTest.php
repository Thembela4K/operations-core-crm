<?php

namespace Tests\Feature;

use App\Models\CrmTask;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Week Plan entries carry due dates, but they are not Assignment records.
 * They must still appear in Deadline Pressure, Upcoming Deadlines and
 * Department Workload.
 */
class DashboardDeadlineTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_task_due_tomorrow_appears_in_upcoming_deadlines(): void
    {
        $department = $this->department('MIS Department');
        $user = $this->user('Samkelo Oneal', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER, $department);

        $this->task($user, $department, 'New Intern', CrmTask::STATUS_IN_PROGRESS, now()->addDay()->toDateString());

        $response = $this->actingAs($user)->get('/');

        $response->assertOk()
            ->assertSee('New Intern')
            ->assertSee('Week Plan Entry')
            ->assertSee('TSK-TEST-1');
    }

    public function test_an_overdue_task_counts_in_deadline_pressure(): void
    {
        $department = $this->department('MIS Department');
        $user = $this->user('Samkelo Oneal', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER, $department);

        $this->task($user, $department, 'Overdue internship paperwork', CrmTask::STATUS_IN_PROGRESS, now()->subDays(3)->toDateString());

        $response = $this->actingAs($user)->get('/');

        $response->assertOk()
            ->assertSee('Overdue internship paperwork')
            ->assertSee('3d overdue');
    }

    public function test_closed_tasks_are_excluded_from_deadlines(): void
    {
        $department = $this->department('MIS Department');
        $user = $this->user('Samkelo Oneal', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER, $department);

        $this->task($user, $department, 'Finished task alpha', CrmTask::STATUS_DONE, now()->addDay()->toDateString());
        $this->task($user, $department, 'Cancelled task beta', CrmTask::STATUS_CANCELLED, now()->addDay()->toDateString());

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertDontSee('Finished task alpha')
            ->assertDontSee('Cancelled task beta');
    }

    public function test_open_tasks_add_to_department_workload(): void
    {
        $department = $this->department('MIS Department');
        $user = $this->user('Samkelo Oneal', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER, $department);

        $this->task($user, $department, 'Workload task one', CrmTask::STATUS_IN_PROGRESS, now()->addDays(2)->toDateString());
        $this->task($user, $department, 'Workload task two', CrmTask::STATUS_TO_DO, now()->addDays(4)->toDateString());

        $response = $this->actingAs($user)->get('/');

        // The workload panel is labelled per department and counts open work.
        $response->assertOk()->assertSee('MIS Department');
    }

    public function test_a_task_outside_the_window_is_not_listed(): void
    {
        $department = $this->department('MIS Department');
        $user = $this->user('Samkelo Oneal', 'samkelo@example.com', User::ROLE_DEPARTMENT_USER, $department);

        $this->task($user, $department, 'Distant future task', CrmTask::STATUS_IN_PROGRESS, now()->addDays(40)->toDateString());

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertDontSee('Distant future task');
    }

    private function task(User $assignee, Department $department, string $title, string $status, string $dueDate): CrmTask
    {
        static $counter = 0;
        $counter++;

        return CrmTask::query()->create([
            'task_number' => 'TSK-TEST-'.$counter,
            'title' => $title,
            'status' => $status,
            'priority' => 'High',
            'department_id' => $department->id,
            'assigned_to' => $assignee->id,
            'created_by' => $assignee->id,
            'due_date' => $dueDate,
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
