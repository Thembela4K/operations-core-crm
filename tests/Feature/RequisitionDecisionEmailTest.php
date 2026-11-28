<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\EmailLog;
use App\Models\Requisition;
use App\Models\User;
use App\Services\RequisitionEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Decision emails (approved / rejected / funds released) must reach the
 * approvers as well as the requester, otherwise the business admin never sees
 * that a requisition was accepted.
 */
class RequisitionDecisionEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_decision_emails_the_requester_and_the_approvers(): void
    {
        Mail::fake();

        $department = $this->department();
        $requester = $this->user('Requester', 'requester@example.com', User::ROLE_DEPARTMENT_USER, $department);
        $director = $this->user('Director', 'director@example.com', User::ROLE_DIRECTOR);
        $admin = $this->user('Business Admin', 'business.admin@example.com', User::ROLE_BUSINESS_ADMIN);

        $requisition = $this->requisition($requester, $department);

        app(RequisitionEmailService::class)->notifyDecision($requisition, 'Approved', 'Approved and ready for release.');

        $recipients = EmailLog::query()->pluck('recipient')->all();

        $this->assertContains($requester->email, $recipients);
        $this->assertContains($director->email, $recipients);
        $this->assertContains($admin->email, $recipients);
    }

    public function test_approving_a_requisition_emails_the_business_admin(): void
    {
        Mail::fake();

        $department = $this->department();
        $requester = $this->user('Requester', 'requester2@example.com', User::ROLE_DEPARTMENT_USER, $department);
        $director = $this->user('Director', 'director2@example.com', User::ROLE_DIRECTOR);
        $admin = $this->user('Business Admin', 'business.admin2@example.com', User::ROLE_BUSINESS_ADMIN);

        $requisition = $this->requisition($requester, $department);
        $requisition->update(['status' => Requisition::STATUS_SUBMITTED]);

        $this->actingAs($director)
            ->post(route('requisitions.approve', $requisition), ['decision_notes' => 'Approved.'])
            ->assertRedirect();

        $recipients = EmailLog::query()->pluck('recipient')->all();

        $this->assertContains($admin->email, $recipients, 'The business admin should be copied on an approval.');
        $this->assertContains($requester->email, $recipients);
    }

    private function department(): Department
    {
        return Department::query()->create([
            'name' => 'MIS Department',
            'slug' => 'mis-department',
            'is_active' => true,
        ]);
    }

    private function user(string $name, string $email, string $role, ?Department $department = null): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'department_id' => $department?->id,
            'is_active' => true,
        ]);
    }

    private function requisition(User $requester, Department $department): Requisition
    {
        return Requisition::query()->create([
            'department_id' => $department->id,
            'requisition_number' => 'REQ-MAIL-'.uniqid(),
            'addressed_to' => 'Directors',
            'title' => 'Printer replacement',
            'category' => 'Operational',
            'priority' => 'Medium',
            'status' => Requisition::STATUS_IN_REVIEW,
            'requested_by' => $requester->id,
        ]);
    }
}
