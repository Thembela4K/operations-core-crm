<?php

namespace Tests\Feature;

use App\Models\CrmTask;
use App\Models\Department;
use App\Models\User;
use App\Models\WeekPlanReport;
use App\Models\WeekPlanReportContributor;
use App\Models\WeekPlanReportTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Weekly Report & Plan: last week's progress, this week's plan with carry-overs,
 * and contributors (employees or outsourced people).
 */
class WeekPlanReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_employee_can_write_and_submit_a_weekly_report(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00')); // Wednesday

        $user = $this->user('Report Owner', 'owner@example.com', User::ROLE_DEPARTMENT_USER);
        $colleague = $this->user('Colleague', 'colleague@example.com', User::ROLE_DEPARTMENT_USER);

        $this->actingAs($user)->post(route('week-reports.save'), [
            'week_start' => '2026-11-23',
            'status' => 'Submitted',
            'last_week_progress' => 'Mostly done, waiting on the client.',
            'this_week_plan' => 'Finish the remaining install.',
            'last_week' => [
                ['title' => 'Install network', 'progress_percent' => 80, 'notes' => 'Two switches left'],
            ],
            'this_week' => [
                ['title' => 'Install network', 'progress_percent' => 20, 'carried_over' => 1, 'notes' => 'Carry over'],
                ['title' => 'Write handover', 'progress_percent' => 0],
            ],
            'contributors' => [
                ['user_id' => $colleague->id, 'contribution' => 'Cabling'],
                ['name' => 'Outside Contractor', 'organisation' => 'Cable Co', 'contribution' => 'Terminations'],
            ],
        ])->assertRedirect();

        $report = WeekPlanReport::query()->firstOrFail();

        $this->assertSame(WeekPlanReport::STATUS_SUBMITTED, $report->status);
        $this->assertNotNull($report->submitted_at);
        $this->assertSame(1, $report->tasks()->where('section', 'last_week')->count());
        $this->assertSame(2, $report->tasks()->where('section', 'this_week')->count());
        $this->assertSame(2, $report->contributors()->count());

        $carried = WeekPlanReportTask::query()->where('section', 'this_week')->where('carried_over', true)->firstOrFail();
        $this->assertSame('Install network', $carried->title);

        $employeeContributor = WeekPlanReportContributor::query()->whereNotNull('user_id')->firstOrFail();
        $this->assertSame($colleague->id, $employeeContributor->user_id);

        $outsourced = WeekPlanReportContributor::query()->whereNull('user_id')->firstOrFail();
        $this->assertSame('Outside Contractor', $outsourced->name);
        $this->assertSame('Cable Co', $outsourced->organisation);

        Carbon::setTestNow();
    }

    public function test_unfinished_last_week_tasks_are_carried_into_this_weeks_rows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00'));

        $user = $this->user('Report Owner', 'owner2@example.com', User::ROLE_DEPARTMENT_USER);

        $unfinished = $this->task($user, 'Unfinished job', CrmTask::STATUS_IN_PROGRESS, '2026-11-18');
        $finished = $this->task($user, 'Finished job', CrmTask::STATUS_DONE, '2026-11-19');

        $page = $this->actingAs($user)->get(route('week-reports.edit'));
        $page->assertOk();

        $thisWeekRows = $page->viewData('thisWeekRows');

        $titles = $thisWeekRows->pluck('title')->all();
        $this->assertContains('Unfinished job', $titles);
        $this->assertNotContains('Finished job', $titles);

        $carried = $thisWeekRows->firstWhere('title', 'Unfinished job');
        $this->assertTrue($carried['carried_over']);

        Carbon::setTestNow();
    }

    public function test_an_employee_cannot_read_another_employees_report(): void
    {
        $owner = $this->user('Owner', 'owner3@example.com', User::ROLE_DEPARTMENT_USER);
        $other = $this->user('Other', 'other3@example.com', User::ROLE_DEPARTMENT_USER);

        $report = $this->report($owner);

        $this->actingAs($other)->get(route('week-reports.show', $report))->assertForbidden();
    }

    public function test_a_manager_can_read_an_employees_report(): void
    {
        $owner = $this->user('Owner', 'owner4@example.com', User::ROLE_DEPARTMENT_USER);
        $admin = $this->user('Admin', 'admin.week@example.com', User::ROLE_BUSINESS_ADMIN);

        $report = $this->report($owner);

        $this->actingAs($admin)->get(route('week-reports.show', $report))
            ->assertOk()
            ->assertSee('Owner');
    }

    private function report(User $owner): WeekPlanReport
    {
        return WeekPlanReport::query()->create([
            'user_id' => $owner->id,
            'department_id' => $owner->department_id,
            'week_start' => '2026-11-16',
            'week_end' => '2026-11-22',
            'last_week_progress' => 'Progress notes',
            'this_week_plan' => 'Plan notes',
            'status' => WeekPlanReport::STATUS_DRAFT,
        ]);
    }

    private function task(User $user, string $title, string $status, string $dueDate): CrmTask
    {
        $task = CrmTask::query()->create([
            'task_number' => 'TSK-'.strtoupper(Str::random(6)),
            'title' => $title,
            'status' => $status,
            'priority' => 'High',
            'department_id' => $user->department_id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'due_date' => $dueDate,
        ]);

        // The task belongs to the week it was raised in, not to "now".
        $raised = Carbon::parse($dueDate)->setTime(9, 0);
        $task->forceFill(['created_at' => $raised, 'updated_at' => $raised])->save();

        return $task;
    }

    private function user(string $name, string $email, string $role): User
    {
        $department = Department::query()->firstOrCreate(
            ['slug' => Str::slug('MIS Department')],
            ['name' => 'MIS Department', 'is_active' => true],
        );

        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'level' => User::LEVEL_OFFICER,
            'department_id' => $department->id,
            'is_active' => true,
        ]);
    }
}
