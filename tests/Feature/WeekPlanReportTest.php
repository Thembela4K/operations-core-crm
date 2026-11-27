<?php

namespace Tests\Feature;

use App\Models\CrmTask;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Week plan: duration in days and a weekly report (defaults to last week).
 */
class WeekPlanReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_week_plan_entry_stores_its_duration(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Prepare induction pack',
            'status' => CrmTask::STATUS_TO_DO,
            'priority' => 'High',
            'duration_days' => 4,
        ])->assertRedirect();

        $task = CrmTask::query()->firstOrFail();

        $this->assertSame(4, $task->duration_days);
    }

    public function test_duration_must_be_a_sensible_number_of_days(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('tasks.store'), [
            'title' => 'Impossible task',
            'status' => CrmTask::STATUS_TO_DO,
            'priority' => 'High',
            'duration_days' => 0,
        ])->assertSessionHasErrors('duration_days');
    }

    public function test_the_weekly_report_defaults_to_last_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00:00')); // Wednesday

        $user = $this->user();

        // Last week (Mon 16 – Sun 22 Nov).
        $lastWeek = $this->task($user, 'Last week task', Carbon::parse('2026-11-17 09:00:00'));
        // This week.
        $thisWeek = $this->task($user, 'This week task', Carbon::parse('2026-11-25 09:00:00'));

        $response = $this->actingAs($user)->get(route('tasks.report'));

        $response->assertOk()
            ->assertSee('Last week task')
            ->assertDontSee('This week task');

        $this->assertSame(1, CrmTask::query()->whereKey($lastWeek->id)->count());
        $this->assertSame(1, CrmTask::query()->whereKey($thisWeek->id)->count());

        Carbon::setTestNow();
    }

    public function test_a_specific_week_can_be_reported_on(): void
    {
        $user = $this->user();

        $this->task($user, 'Week of 9 November', Carbon::parse('2026-11-10 09:00:00'));
        $this->task($user, 'Week of 16 November', Carbon::parse('2026-11-17 09:00:00'));

        $this->actingAs($user)
            ->get(route('tasks.report', ['week' => '2026-11-11']))
            ->assertOk()
            ->assertSee('Week of 9 November')
            ->assertDontSee('Week of 16 November');
    }

    public function test_the_report_counts_entries_completed_in_the_week(): void
    {
        $user = $this->user();

        $task = $this->task($user, 'Finished in week', Carbon::parse('2026-10-01 09:00:00'));
        $task->update([
            'status' => CrmTask::STATUS_DONE,
            'completed_at' => Carbon::parse('2026-11-18 15:00:00'),
        ]);

        $this->actingAs($user)
            ->get(route('tasks.report', ['week' => '2026-11-18']))
            ->assertOk()
            ->assertSee('Finished in week');
    }

    public function test_a_business_admin_does_not_approve_or_release_funds(): void
    {
        $admin = User::query()->create([
            'name' => 'Business Admin',
            'email' => 'ba.perms@example.com',
            'password' => 'password',
            'role' => User::ROLE_BUSINESS_ADMIN,
            'is_active' => true,
        ]);

        $this->assertFalse($admin->canApproveFinance());
        $this->assertFalse($admin->canApproveRequisitions());
        $this->assertFalse($admin->canReleaseRequisitionFunds());
    }

    public function test_a_director_approves_and_releases_funds(): void
    {
        $director = User::query()->create([
            'name' => 'Director',
            'email' => 'director.perms@example.com',
            'password' => 'password',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        $this->assertTrue($director->canApproveFinance());
        $this->assertTrue($director->canApproveRequisitions());
        $this->assertTrue($director->canReleaseRequisitionFunds());
    }

    public function test_a_business_admin_cannot_approve_a_requisition_over_http(): void
    {
        $admin = User::query()->create([
            'name' => 'Business Admin',
            'email' => 'ba.http@example.com',
            'password' => 'password',
            'role' => User::ROLE_BUSINESS_ADMIN,
            'is_active' => true,
        ]);

        $department = Department::query()->create([
            'name' => 'MIS Department',
            'slug' => Str::slug('MIS Department'),
            'is_active' => true,
        ]);

        $requisition = \App\Models\Requisition::query()->create([
            'department_id' => $department->id,
            'requisition_number' => 'REQ-PERM-1',
            'addressed_to' => 'Directors',
            'title' => 'New laptops',
            'category' => 'Operational',
            'priority' => 'Medium',
            'status' => \App\Models\Requisition::STATUS_SUBMITTED,
            'requested_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('requisitions.approve', $requisition))
            ->assertForbidden();
    }

    private function task(User $user, string $title, Carbon $createdAt): CrmTask
    {
        $task = CrmTask::query()->create([
            'task_number' => 'TSK-'.strtoupper(Str::random(6)),
            'title' => $title,
            'status' => CrmTask::STATUS_IN_PROGRESS,
            'priority' => 'High',
            'department_id' => $user->department_id,
            'assigned_to' => $user->id,
            'created_by' => $user->id,
            'due_date' => $createdAt->copy()->addDays(3)->toDateString(),
        ]);

        $task->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        return $task;
    }

    private function user(): User
    {
        $department = Department::query()->firstOrCreate(
            ['slug' => Str::slug('MIS Department')],
            ['name' => 'MIS Department', 'is_active' => true],
        );

        return User::query()->create([
            'name' => 'Week Plan Owner',
            'email' => 'week.owner@example.com',
            'password' => 'password',
            'role' => User::ROLE_DEPARTMENT_USER,
            'level' => User::LEVEL_OFFICER,
            'department_id' => $department->id,
            'is_active' => true,
        ]);
    }
}
