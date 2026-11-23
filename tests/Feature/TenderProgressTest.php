<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\TenderProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenderProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_management_can_update_tender_status_and_progress(): void
    {
        $reception = $this->user('Reception', 'reception@example.com', User::ROLE_RECEPTION);
        $tender = $this->tender($reception);

        $this->actingAs($reception)
            ->post(route('tender-proposals.progress', $tender), [
                'progress_percent' => 65,
                'status' => 'In Progress',
            ])
            ->assertRedirect();

        $tender->refresh();

        $this->assertSame(65, $tender->progress_percent);
        $this->assertSame('In Progress', $tender->status);
    }

    public function test_assigned_department_user_can_update_progress_but_not_status(): void
    {
        $reception = $this->user('Reception', 'reception@example.com', User::ROLE_RECEPTION);
        $department = $this->department('MIS Department');
        $departmentUser = $this->user('MIS User', 'mis@example.com', User::ROLE_DEPARTMENT_USER, $department);

        $tender = $this->tender($reception);

        $tender->assignments()->create([
            'department_id' => $department->id,
            'assigned_user_id' => $departmentUser->id,
            'assignee_name' => $departmentUser->name,
            'assignee_email' => $departmentUser->email,
            'status' => 'Assigned',
            'workflow_status' => 'Assigned',
            'assigned_at' => now(),
            'created_by' => $reception->id,
        ]);

        $this->actingAs($departmentUser)
            ->post(route('tender-proposals.progress', $tender), ['progress_percent' => 40])
            ->assertRedirect();

        $this->assertSame(40, $tender->fresh()->progress_percent);

        // Status changes are reserved for management.
        $this->actingAs($departmentUser)
            ->post(route('tender-proposals.progress', $tender), [
                'progress_percent' => 90,
                'status' => 'Closed',
            ])
            ->assertForbidden();

        $this->assertSame('Draft', $tender->fresh()->status);
    }

    public function test_tender_edit_form_updates_priority_risk_budget_and_progress(): void
    {
        $reception = $this->user('Reception', 'reception@example.com', User::ROLE_RECEPTION);
        $tender = $this->tender($reception);

        $this->actingAs($reception)
            ->put(route('tender-proposals.update', $tender), [
                'tender_reference' => $tender->tender_reference,
                'title' => 'Updated tender title',
                'closing_date' => '2026-12-20',
                'brief' => 'Updated brief',
                'status' => 'In Progress',
                'priority' => 'High',
                'risk' => 'Low',
                'rating' => 4.5,
                'progress_percent' => 55,
                'budget' => 125000.50,
                'owner' => 'Thembela Mthimkhulu',
                'owner_email' => 'thembela@example.com',
                'received_date' => '2026-11-01',
            ])
            ->assertRedirect();

        $tender->refresh();

        $this->assertSame('Updated tender title', $tender->title);
        $this->assertSame('In Progress', $tender->status);
        $this->assertSame('High', $tender->priority);
        $this->assertSame('Low', $tender->risk);
        $this->assertSame(55, $tender->progress_percent);
        $this->assertSame('125000.50', $tender->budget);
        $this->assertSame('4.5', $tender->rating);
    }

    public function test_progress_is_validated_between_zero_and_one_hundred(): void
    {
        $reception = $this->user('Reception', 'reception@example.com', User::ROLE_RECEPTION);
        $tender = $this->tender($reception);

        $this->actingAs($reception)
            ->post(route('tender-proposals.progress', $tender), ['progress_percent' => 150])
            ->assertSessionHasErrors('progress_percent');

        $this->assertSame(0, $tender->fresh()->progress_percent);
    }

    private function tender(User $creator): TenderProposal
    {
        return TenderProposal::query()->create([
            'tender_reference' => 'TDR-TEST-001',
            'title' => 'Test tender',
            'owner' => $creator->name,
            'owner_email' => $creator->email,
            'status' => 'Draft',
            'priority' => 'Medium',
            'rating' => 0,
            'risk' => 'Medium',
            'progress_percent' => 0,
            'budget' => 0,
            'received_date' => now()->toDateString(),
            'closing_date' => now()->addMonth()->toDateString(),
            'brief' => 'Test brief',
            'created_by' => $creator->id,
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
