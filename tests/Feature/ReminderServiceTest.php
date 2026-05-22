<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Quotation;
use App\Models\Submission;
use App\Models\TenderProposal;
use App\Services\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_reminders_are_logged_once(): void
    {
        Mail::fake();

        $department = Department::query()->create([
            'name' => 'IT Department',
            'slug' => 'it-department',
            'email' => 'it@example.com',
            'is_active' => true,
        ]);
        $tender = TenderProposal::query()->create([
            'tender_reference' => 'TDR-900',
            'title' => 'Due Tender',
            'owner' => 'Owner',
            'owner_email' => 'owner@example.com',
            'status' => 'In Progress',
            'priority' => 'High',
            'rating' => 4,
            'risk' => 'Medium',
            'progress_percent' => 80,
            'budget' => 10000,
            'received_date' => now()->subDays(5)->toDateString(),
            'closing_date' => now()->addDays(5)->toDateString(),
        ]);
        $tender->assignments()->create([
            'department_id' => $department->id,
            'assignee_name' => 'IT Department',
            'assignee_email' => 'it@example.com',
            'status' => 'Assigned',
            'workflow_status' => 'Assigned',
            'assigned_at' => now(),
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $service = app(ReminderService::class);

        $this->assertSame(1, $service->sendDueReminders());
        $this->assertSame(0, $service->sendDueReminders());
        $this->assertDatabaseCount('reminder_logs', 1);
        $this->assertDatabaseCount('email_logs', 1);
    }

    public function test_reminders_skip_assignments_with_submissions_and_mark_quotations_overdue(): void
    {
        Mail::fake();

        $department = Department::query()->create([
            'name' => 'MIS Department',
            'slug' => 'mis-department',
            'email' => 'mis@example.com',
            'is_active' => true,
        ]);

        $respondedTender = TenderProposal::query()->create([
            'tender_reference' => 'TDR-901',
            'title' => 'Responded Tender',
            'owner' => 'Owner',
            'owner_email' => 'owner@example.com',
            'status' => 'In Progress',
            'priority' => 'High',
            'rating' => 4,
            'risk' => 'Medium',
            'progress_percent' => 80,
            'budget' => 10000,
            'received_date' => now()->subDays(5)->toDateString(),
            'closing_date' => now()->addDays(5)->toDateString(),
        ]);
        $assignment = $respondedTender->assignments()->create([
            'department_id' => $department->id,
            'assignee_name' => 'MIS Department',
            'assignee_email' => 'mis@example.com',
            'status' => 'Assigned',
            'workflow_status' => 'Assigned',
            'assigned_at' => now(),
            'due_date' => now()->addDays(5)->toDateString(),
        ]);
        $respondedTender->submissions()->create([
            'assignment_id' => $assignment->id,
            'department_id' => $department->id,
            'status' => Submission::STATUS_DRAFT,
            'submitted_at' => now(),
        ]);

        $overdueQuotation = Quotation::query()->create([
            'quotation_code' => 'QTN-901',
            'client' => 'Client',
            'opportunity' => 'Overdue Quotation',
            'owner' => 'Owner',
            'owner_email' => 'owner@example.com',
            'status' => 'Under Review',
            'priority' => 'Medium',
            'rating' => 0,
            'risk' => 'Medium',
            'win_probability_percent' => 0,
            'quoted_amount' => 0,
            'expected_cost' => 0,
            'issue_date' => now()->subDays(10)->toDateString(),
            'valid_until' => now()->subDay()->toDateString(),
        ]);
        $overdueQuotation->assignments()->create([
            'department_id' => $department->id,
            'assignee_name' => 'MIS Department',
            'assignee_email' => 'mis@example.com',
            'status' => 'Assigned',
            'workflow_status' => 'Assigned',
            'assigned_at' => now(),
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $service = app(ReminderService::class);

        $this->assertSame(0, $service->sendDueReminders());
        $this->assertDatabaseCount('reminder_logs', 0);
        $this->assertDatabaseHas('quotations', [
            'id' => $overdueQuotation->id,
            'status' => Quotation::STATUS_OVERDUE,
        ]);
    }
}
