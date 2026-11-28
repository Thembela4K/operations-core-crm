<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\CrmTask;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Assignments: self-assignment, meetings with a date and time, workflow,
 * reassignment, history, and Week Plan integration.
 */
class AssignmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_assign_work_to_themselves(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-28 09:00:00'));

        $user = $this->user('Samkelo', 'samkelo.assign@example.com');

        $this->actingAs($user)->post(route('assignments.store'), [
            'kind' => 'work',
            'title' => 'Draft the tender response',
            'assign_to_me' => 1,
            'due_date' => '2026-12-02',
        ])->assertRedirect();

        $assignment = Assignment::query()->firstOrFail();

        $this->assertSame($user->id, $assignment->assigned_user_id);
        $this->assertSame($user->id, $assignment->department_id === null ? $user->id : $user->id);
        $this->assertSame('Assigned', $assignment->workflow_status);

        // It becomes a Week Plan task for the same person.
        $task = CrmTask::query()->where('taskable_type', Assignment::class)->where('taskable_id', $assignment->id)->firstOrFail();
        $this->assertSame($user->id, $task->assigned_to);
        $this->assertSame('Draft the tender response', $task->title);
        $this->assertSame('2026-12-02', $task->due_date->toDateString());

        Carbon::setTestNow();
    }

    public function test_a_meeting_is_created_with_a_date_and_time(): void
    {
        $organiser = $this->user('Director', 'director.mtg@example.com', User::ROLE_DIRECTOR);
        $attendee = $this->user('Officer', 'officer.mtg@example.com');

        $this->actingAs($organiser)->post(route('assignments.store'), [
            'kind' => 'meeting',
            'title' => 'Client kick-off',
            'assigned_user_id' => $attendee->id,
            'starts_at' => '2026-12-01 09:30',
            'ends_at' => '2026-12-01 10:30',
            'location' => 'Boardroom',
        ])->assertRedirect();

        $assignment = Assignment::query()->firstOrFail();

        $this->assertTrue($assignment->isMeeting());
        $this->assertSame('2026-12-01 09:30', $assignment->starts_at->format('Y-m-d H:i'));
        $this->assertSame('Boardroom', $assignment->location);
    }

    public function test_a_meeting_appears_on_the_dashboard_on_the_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-01 08:00:00'));

        $attendee = $this->user('Officer', 'officer.today@example.com');

        Assignment::query()->create([
            'title' => 'Standup',
            'kind' => Assignment::KIND_MEETING,
            'department_id' => $attendee->department_id,
            'assigned_user_id' => $attendee->id,
            'assignee_name' => $attendee->name,
            'assignee_email' => $attendee->email,
            'status' => 'Assigned',
            'workflow_status' => 'Assigned',
            'starts_at' => '2026-12-01 09:30',
            'created_by' => $attendee->id,
        ]);

        $this->actingAs($attendee)->get(route('dashboard'))
            ->assertOk()
            ->assertSee("Today's Meetings", false)
            ->assertSee('Standup')
            ->assertSee('09:30');

        Carbon::setTestNow();
    }

    public function test_the_assignee_moves_the_assignment_through_its_workflow(): void
    {
        $user = $this->user('Officer', 'officer.flow@example.com');
        $assignment = $this->assignment($user);

        $this->actingAs($user)
            ->post(route('assignments.transition', $assignment), ['action' => 'accept'])
            ->assertRedirect();
        $this->assertSame('Accepted', $assignment->fresh()->workflow_status);
        $this->assertNotNull($assignment->fresh()->accepted_at);

        $this->actingAs($user)->post(route('assignments.transition', $assignment), ['action' => 'start']);
        $this->assertSame('In Progress', $assignment->fresh()->workflow_status);

        $this->actingAs($user)->post(route('assignments.transition', $assignment), ['action' => 'complete']);
        $this->assertSame('Completed', $assignment->fresh()->workflow_status);
        $this->assertNotNull($assignment->fresh()->completed_at);

        // The Week Plan task follows along and ends up done.
        $this->assertSame(
            CrmTask::STATUS_DONE,
            CrmTask::query()->where('taskable_type', Assignment::class)->where('taskable_id', $assignment->id)->firstOrFail()->status,
        );

        // Every step is kept: three transitions after creation.
        $this->assertSame(3, $assignment->events()->count());
    }

    public function test_an_assignment_can_be_reassigned_with_history(): void
    {
        $manager = $this->user('Admin', 'admin.reassign@example.com', User::ROLE_BUSINESS_ADMIN);
        $first = $this->user('First', 'first.reassign@example.com');
        $second = $this->user('Second', 'second.reassign@example.com');

        $assignment = $this->assignment($first);

        Assignment::query()->whereKey($assignment->id)->update(['assignee_name' => 'Something']);

        $this->actingAs($manager)
            ->post(route('assignments.reassign', $assignment), [
                'assigned_user_id' => $second->id,
                'notes' => 'Handing over while First is on leave.',
            ])->assertRedirect();

        $assignment->refresh();

        $this->assertSame($second->id, $assignment->assigned_user_id);
        $this->assertSame('Assigned', $assignment->workflow_status);

        $task = CrmTask::query()->where('taskable_type', Assignment::class)->where('taskable_id', $assignment->id)->firstOrFail();
        $this->assertSame($second->id, $task->assigned_to);

        $this->assertSame(1, $assignment->events()->where('action', 'reassigned')->count());
    }

    public function test_another_employee_cannot_open_someone_elses_assignment(): void
    {
        $owner = $this->user('Owner', 'owner.assign@example.com');
        $stranger = $this->user('Stranger', 'stranger.assign@example.com');

        $assignment = $this->assignment($owner);

        $this->actingAs($stranger)->get(route('assignments.show', $assignment))->assertForbidden();
    }

    private function assignment(User $user): Assignment
    {
        return Assignment::query()->create([
            'title' => 'Prepare documents',
            'kind' => Assignment::KIND_WORK,
            'department_id' => $user->department_id,
            'assigned_user_id' => $user->id,
            'assignee_name' => $user->name,
            'assignee_email' => $user->email,
            'status' => 'Assigned',
            'workflow_status' => 'Assigned',
            'due_date' => now()->addDays(3)->toDateString(),
            'created_by' => $user->id,
        ]);
    }

    private function department(): Department
    {
        return Department::query()->firstOrCreate(
            ['slug' => Str::slug('MIS Department')],
            ['name' => 'MIS Department', 'is_active' => true],
        );
    }

    private function user(string $name, string $email, string $role = User::ROLE_DEPARTMENT_USER): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'level' => User::LEVEL_OFFICER,
            'department_id' => $this->department()->id,
            'is_active' => true,
        ]);
    }
}
