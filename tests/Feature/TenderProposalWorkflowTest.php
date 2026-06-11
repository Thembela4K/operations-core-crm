<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Submission;
use App\Models\TenderProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenderProposalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_reception_can_create_tender_proposal_with_important_dates(): void
    {
        Storage::fake('local');

        $reception = User::query()->create([
            'name' => 'Reception',
            'email' => 'reception@example.com',
            'password' => 'password',
            'role' => User::ROLE_RECEPTION,
            'is_active' => true,
        ]);

        $this->actingAs($reception)->post(route('tender-proposals.store'), [
            'tender_reference' => 'TDR-500',
            'title' => 'New Tender Proposal',
            'closing_date' => now()->addMonth()->toDateString(),
            'brief' => 'Tender brief.',
            'tender_document' => UploadedFile::fake()->create('tender.pdf', 120, 'application/pdf'),
            'important_dates' => [
                ['label' => 'Site Visit', 'due_date' => now()->addWeek()->toDateString(), 'notes' => 'Bring credentials.'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('tender_proposals', [
            'tender_reference' => 'TDR-500',
            'title' => 'New Tender Proposal',
        ]);
        $this->assertDatabaseHas('important_dates', [
            'label' => 'Site Visit',
        ]);
        $this->assertDatabaseHas('documents', [
            'documentable_type' => TenderProposal::class,
            'category' => 'original_tender',
            'original_name' => 'tender.pdf',
        ]);
    }

    public function test_department_user_can_submit_assigned_tender_response(): void
    {
        $department = Department::query()->create([
            'name' => 'IT Department',
            'slug' => Str::slug('IT Department'),
            'is_active' => true,
        ]);
        $user = User::query()->create([
            'department_id' => $department->id,
            'name' => 'IT User',
            'email' => 'it@example.com',
            'password' => 'password',
            'role' => User::ROLE_DEPARTMENT_USER,
            'is_active' => true,
        ]);
        $tender = TenderProposal::query()->create($this->tenderData());
        $assignment = $tender->assignments()->create([
            'department_id' => $department->id,
            'assignee_name' => $user->name,
            'assignee_email' => $user->email,
            'status' => 'Assigned',
            'workflow_status' => 'Assigned',
            'assigned_at' => now(),
        ]);

        $this->actingAs($user)->post(route('submissions.store'), [
            'module' => 'tender_proposal',
            'record_id' => $tender->id,
            'assignment_id' => $assignment->id,
            'status' => Submission::STATUS_DRAFT,
            'notes' => 'Technical draft uploaded separately.',
        ])->assertRedirect();

        $this->assertDatabaseHas('submissions', [
            'submittable_type' => TenderProposal::class,
            'submittable_id' => $tender->id,
            'department_id' => $department->id,
            'status' => Submission::STATUS_DRAFT,
        ]);
        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'workflow_status' => 'Draft Submitted',
        ]);
    }

    private function tenderData(): array
    {
        return [
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
            'closing_date' => now()->addDays(3)->toDateString(),
        ];
    }
}
