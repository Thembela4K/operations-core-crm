<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequisitionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_submits_fund_requisition_director_approves_and_funds_are_released(): void
    {
        Mail::fake();

        $department = $this->department('MIS Department');
        $departmentUser = $this->user('MIS User', 'mis@example.com', User::ROLE_DEPARTMENT_USER, $department);
        $director = $this->user('Director', 'director@example.com', User::ROLE_DIRECTOR);
        $reception = $this->user('Reception', 'reception@example.com', User::ROLE_RECEPTION);

        $this->actingAs($departmentUser)->post(route('requisitions.store'), [
            'action' => 'submit',
            'addressed_to' => 'Directors',
            'requisition_number' => 'REQ-2026-0001',
            'department_id' => $department->id,
            'title' => 'Tender document purchase funds',
            'category' => 'Tender Support',
            'priority' => 'High',
            'needed_by' => now()->addDays(2)->toDateString(),
            'purpose' => 'Funds required for tender document purchase and binding.',
            'items' => [
                [
                    'description' => 'Tender document purchase',
                    'payment_type' => 'Bank',
                    'quantity' => 1,
                    'estimated_unit_cost' => 500,
                    'source' => 'Supplier bank account details',
                ],
                [
                    'description' => 'Tender binding',
                    'payment_type' => 'Cash',
                    'quantity' => 9,
                    'estimated_unit_cost' => 35,
                    'source' => 'Cash supplier',
                ],
            ],
        ])->assertRedirect();

        $requisition = Requisition::query()->firstOrFail();
        $this->assertSame(Requisition::STATUS_SUBMITTED, $requisition->status);
        $this->assertSame('815.00', $requisition->estimated_total);
        $this->assertSame('500.00', $requisition->bank_total);
        $this->assertSame('315.00', $requisition->cash_total);
        $this->assertDatabaseHas('email_logs', [
            'emailable_type' => Requisition::class,
            'emailable_id' => $requisition->id,
            'recipient' => $director->email,
            'status' => 'Sent',
        ]);

        $this->actingAs($director)->post(route('requisitions.approve', $requisition), [
            'decision_notes' => 'Approved.',
        ])->assertRedirect();

        $requisition->refresh();
        $this->assertSame(Requisition::STATUS_APPROVED, $requisition->status);
        $this->assertSame($director->id, $requisition->approved_by);

        $this->actingAs($reception)->post(route('requisitions.release-funds', $requisition), [
            'release_notes' => 'Cash prepared and bank transfer queued.',
        ])->assertRedirect();

        $requisition->refresh();
        $this->assertSame(Requisition::STATUS_FUNDS_RELEASED, $requisition->status);
        $this->assertSame($reception->id, $requisition->released_by);
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
