<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentText;
use App\Models\Submission;
use App\Models\TenderProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_cannot_use_assistant(): void
    {
        $this->postJson(route('assistant.message'), [
            'message' => 'show overdue tender proposals',
        ])->assertUnauthorized();
    }

    public function test_assistant_greeting_is_scoped_to_crm_help(): void
    {
        $user = $this->user('Thembela Mthimkhulu', User::ROLE_DEPARTMENT_USER);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'hi',
        ])->assertOk();

        $response->assertJsonPath('action', null);
        $this->assertStringContainsString('Hi Thembela', $response->json('reply'));
        $this->assertStringContainsString('tenders', $response->json('reply'));
    }

    public function test_assistant_opens_last_month_submitted_tender_documents(): void
    {
        Carbon::setTestNow('2026-06-11 10:00:00');
        $user = $this->user('Admin User', User::ROLE_SUPER_ADMIN);
        $department = $this->department('MIS Department');
        $tender = $this->tender();
        $submission = $this->submission($tender, $department);
        $document = $this->document($submission, 'Technical Proposal May.pdf', '2026-05-12 09:00:00');

        DocumentText::query()->create([
            'document_id' => $document->id,
            'status' => DocumentText::STATUS_INDEXED,
            'content' => 'Submitted tender technical proposal for May.',
            'char_count' => 45,
            'extracted_at' => now(),
        ]);

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'show me last month submitted tender documents',
        ])->assertOk();

        $url = $response->json('action.url');
        $this->assertStringContainsString('/documents?', $url);
        $this->assertStringContainsString('module=submission', $url);
        $this->assertStringContainsString('linked_type=tender_proposal', $url);
        $this->assertStringContainsString('date_from=2026-05-01', $url);
        $this->assertStringContainsString('date_to=2026-05-31', $url);
        $this->assertStringContainsString('I found 1 documents', $response->json('reply'));
    }

    public function test_assistant_document_count_respects_department_access(): void
    {
        Carbon::setTestNow('2026-06-11 10:00:00');
        $mis = $this->department('MIS Department');
        $gis = $this->department('GIS Department');
        $user = $this->user('MIS User', User::ROLE_DEPARTMENT_USER, $mis);

        $this->document($this->submission($this->tender('TDR-001'), $mis), 'MIS Proposal.pdf', '2026-05-10 08:00:00');
        $this->document($this->submission($this->tender('TDR-002'), $gis), 'GIS Proposal.pdf', '2026-05-11 08:00:00');

        $response = $this->actingAs($user)->postJson(route('assistant.message'), [
            'message' => 'show last month submitted tender documents',
        ])->assertOk();

        $this->assertStringContainsString('I found 1 documents', $response->json('reply'));
    }

    private function department(string $name): Department
    {
        return Department::query()->create([
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
        ]);
    }

    private function user(string $name, string $role, ?Department $department = null): User
    {
        return User::query()->create([
            'department_id' => $department?->id,
            'name' => $name,
            'username' => Str::slug($name, '.'),
            'email' => Str::slug($name, '.').'@example.com',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function tender(string $reference = 'TDR-TEST'): TenderProposal
    {
        return TenderProposal::query()->create([
            'tender_reference' => $reference,
            'title' => 'Tender Test',
            'owner' => 'Admin',
            'status' => 'Draft',
            'priority' => 'Medium',
            'rating' => 0,
            'risk' => 'Medium',
            'progress_percent' => 0,
            'budget' => 0,
            'received_date' => now()->toDateString(),
            'closing_date' => now()->addDays(20)->toDateString(),
        ]);
    }

    private function submission(TenderProposal $tender, Department $department): Submission
    {
        return $tender->submissions()->create([
            'department_id' => $department->id,
            'status' => Submission::STATUS_FINISHED,
            'submitted_at' => now(),
        ]);
    }

    private function document(Submission $submission, string $name, string $createdAt): Document
    {
        $document = $submission->documents()->create([
            'category' => Document::CATEGORY_TECHNICAL_PROPOSAL,
            'original_name' => $name,
            'stored_name' => $name,
            'path' => 'documents/testing/'.$name,
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $document->forceFill([
            'created_at' => Carbon::parse($createdAt),
            'updated_at' => Carbon::parse($createdAt),
        ])->save();

        return $document;
    }
}
