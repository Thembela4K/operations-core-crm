<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Client;
use App\Models\CrmNotification;
use App\Models\CrmTask;
use App\Models\Department;
use App\Models\Requisition;
use App\Models\SalesQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompleteCrmCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_username(): void
    {
        $user = $this->user('MIS Staff', 'mis.staff@example.com', User::ROLE_DEPARTMENT_USER, username: 'mis.staff');

        $this->post(route('login.store'), [
            'login' => 'mis.staff',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_task_assignment_creates_notification_and_can_be_completed(): void
    {
        $department = $this->department('MIS Department');
        $creator = $this->user('Reception', 'reception@example.com', User::ROLE_RECEPTION);
        $assignee = $this->user('MIS User', 'mis@example.com', User::ROLE_DEPARTMENT_USER, $department, 'mis.user');

        $this->actingAs($creator)->post(route('tasks.store'), [
            'task_number' => 'TSK-2026-0001',
            'title' => 'Prepare client proposal',
            'status' => CrmTask::STATUS_TO_DO,
            'priority' => 'High',
            'department_id' => $department->id,
            'assigned_to' => $assignee->id,
            'due_date' => now()->addDay()->toDateString(),
        ])->assertRedirect();

        $task = CrmTask::query()->firstOrFail();
        $this->assertDatabaseHas('crm_notifications', [
            'user_id' => $assignee->id,
            'type' => 'task_assignment',
        ]);

        $this->actingAs($assignee)->post(route('tasks.status', $task), [
            'status' => CrmTask::STATUS_DONE,
        ])->assertRedirect();

        $this->assertSame(CrmTask::STATUS_DONE, $task->fresh()->status);
    }

    public function test_attendance_clock_in_clock_out_and_admin_correction(): void
    {
        $department = $this->department('IT Department');
        $staff = $this->user('IT Staff', 'it@example.com', User::ROLE_DEPARTMENT_USER, $department, 'it.staff');
        $admin = $this->user('Admin', 'admin@example.com', User::ROLE_RECEPTION);

        $this->actingAs($staff)->post(route('attendance.clock-in'))->assertRedirect();
        $this->actingAs($staff)->post(route('attendance.clock-out'))->assertRedirect();

        $record = AttendanceRecord::query()->firstOrFail();
        $this->assertSame(AttendanceRecord::STATUS_COMPLETED, $record->status);

        $this->actingAs($admin)->patch(route('attendance.correct', $record), [
            'in_time' => now()->setTime(8, 0)->format('Y-m-d\TH:i'),
            'out_time' => now()->setTime(17, 0)->format('Y-m-d\TH:i'),
            'correction_note' => 'Corrected from register.',
        ])->assertRedirect();

        $this->assertSame(540, $record->fresh()->total_minutes);
    }

    public function test_approval_center_lists_pending_sales_quotations_and_requisitions(): void
    {
        $director = $this->user('Director', 'director@example.com', User::ROLE_DIRECTOR);
        $department = $this->department('GIS Department');
        $client = Client::query()->create(['client_code' => 'CLT-2026-0001', 'name' => 'Client', 'is_active' => true]);
        SalesQuotation::query()->create([
            'client_id' => $client->id,
            'department_id' => $department->id,
            'quotation_number' => 'QUO-2026-0001',
            'title' => 'Submitted quote',
            'status' => SalesQuotation::STATUS_SUBMITTED,
            'issue_date' => now(),
            'valid_until' => now()->addDays(30),
            'submitted_at' => now(),
        ]);
        Requisition::query()->create([
            'department_id' => $department->id,
            'requisition_number' => 'REQ-2026-0001',
            'addressed_to' => 'Directors',
            'title' => 'Funds request',
            'category' => 'Operational',
            'priority' => 'Medium',
            'status' => Requisition::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->actingAs($director)
            ->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('Submitted quote')
            ->assertSee('Funds request');
    }

    private function department(string $name): Department
    {
        return Department::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    private function user(string $name, string $email, string $role, ?Department $department = null, ?string $username = null): User
    {
        return User::query()->create([
            'department_id' => $department?->id,
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }
}
