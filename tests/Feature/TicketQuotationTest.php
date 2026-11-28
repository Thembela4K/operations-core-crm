<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\Ticket;
use App\Models\TicketQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The IT Officer prices a ticket and sends the quotation to the directors as a
 * requisition.
 */
class TicketQuotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_it_officer_can_price_a_ticket(): void
    {
        $it = $this->department('IT Department', true);
        $officer = $this->user('IT Officer', 'it.officer@example.com', $it);
        $ticket = $this->ticket($officer);
        $supplier = Supplier::query()->create([
            'supplier_code' => 'SUP-IT',
            'name' => 'Cable Co',
            'is_active' => true,
        ]);

        $this->actingAs($officer)->post(route('tickets.quotations.store', $ticket), [
            'supplier_id' => $supplier->id,
            'reference' => 'Q-123',
            'valid_until' => now()->addDays(14)->toDateString(),
            'notes' => 'Includes installation.',
            'items' => [
                ['description' => 'Network switch', 'quantity' => 2, 'unit_price' => 1500],
                ['description' => 'Cabling', 'quantity' => 5, 'unit_price' => 100],
            ],
        ])->assertRedirect();

        $quotation = TicketQuotation::query()->firstOrFail();

        $this->assertSame($supplier->id, $quotation->supplier_id);
        $this->assertSame(3500.00, (float) $quotation->total);
        $this->assertCount(2, $quotation->items()->get());
        $this->assertFalse($quotation->isSent());
    }

    public function test_sending_the_quotation_creates_a_requisition_for_the_directors(): void
    {
        Mail::fake();

        $it = $this->department('IT Department', true);
        $officer = $this->user('IT Officer', 'it.officer@example.com', $it);
        $requester = $this->user('Requester', 'requester.it@example.com', $this->department('Finance', false));
        $ticket = $this->ticket($requester);

        $this->actingAs($officer)->post(route('tickets.quotations.store', $ticket), [
            'supplier_name' => 'Cable Co',
            'items' => [
                ['description' => 'Replacement printer', 'quantity' => 1, 'unit_price' => 4500],
            ],
        ]);

        $quotation = TicketQuotation::query()->firstOrFail();

        $this->actingAs($officer)
            ->post(route('tickets.quotations.send', [$ticket, $quotation]))
            ->assertRedirect();

        $quotation = $quotation->fresh();
        $requisition = Requisition::query()->firstOrFail();

        $this->assertTrue($quotation->isSent());
        $this->assertSame($requisition->id, $quotation->requisition_id);

        $this->assertSame($ticket->id, $requisition->source_ticket_id);
        $this->assertSame('Directors', $requisition->addressed_to);
        $this->assertSame(Requisition::STATUS_SUBMITTED, $requisition->status);
        $this->assertSame(4500.00, (float) $requisition->estimated_total);
        $this->assertSame(1, $requisition->items()->count());
        $this->assertSame('Cable Co', $requisition->items()->first()->source);

        // The ticket moves on and records the transition.
        $this->assertSame(Ticket::STATUS_AWAITING_APPROVAL, $ticket->fresh()->status);
        $this->assertSame(
            1,
            $ticket->statusHistory()->where('to_status', Ticket::STATUS_AWAITING_APPROVAL)->count(),
        );
    }

    public function test_a_quotation_cannot_be_sent_twice(): void
    {
        Mail::fake();

        $it = $this->department('IT Department', true);
        $officer = $this->user('IT Officer', 'it.officer@example.com', $it);
        $ticket = $this->ticket($officer);

        $this->actingAs($officer)->post(route('tickets.quotations.store', $ticket), [
            'supplier_name' => 'Cable Co',
            'items' => [['description' => 'Cable', 'quantity' => 1, 'unit_price' => 50]],
        ]);

        $quotation = TicketQuotation::query()->firstOrFail();

        $this->actingAs($officer)->post(route('tickets.quotations.send', [$ticket, $quotation]))->assertRedirect();
        $this->actingAs($officer)->post(route('tickets.quotations.send', [$ticket, $quotation]))->assertRedirect();

        $this->assertSame(1, Requisition::query()->count());
    }

    public function test_a_requester_cannot_price_a_ticket(): void
    {
        $it = $this->department('IT Department', true);
        $requester = $this->user('Requester', 'requester.it@example.com', $this->department('Finance', false));
        $ticket = $this->ticket($requester);

        $this->actingAs($requester)->post(route('tickets.quotations.store', $ticket), [
            'supplier_name' => 'Cable Co',
            'items' => [['description' => 'Cable', 'quantity' => 1, 'unit_price' => 50]],
        ])->assertForbidden();
    }

    public function test_delayed_and_rejected_statuses_are_available(): void
    {
        $it = $this->department('IT Department', true);
        $officer = $this->user('IT Officer', 'it.officer@example.com', $it);
        $ticket = $this->ticket($officer);

        $this->actingAs($officer)
            ->post(route('tickets.status', $ticket), ['status' => Ticket::STATUS_DELAYED, 'reason' => 'Waiting on supplier'])
            ->assertRedirect();

        $this->assertSame(Ticket::STATUS_DELAYED, $ticket->fresh()->status);

        $this->actingAs($officer)
            ->post(route('tickets.status', $ticket), ['status' => Ticket::STATUS_REJECTED, 'reason' => 'Out of scope'])
            ->assertRedirect();

        $this->assertSame(Ticket::STATUS_REJECTED, $ticket->fresh()->status);
    }

    private function ticket(User $requester): Ticket
    {
        return Ticket::query()->create([
            'ticket_number' => 'TKT-'.strtoupper(Str::random(6)),
            'requester_id' => $requester->id,
            'department_id' => $requester->department_id,
            'created_by' => $requester->id,
            'title' => 'Printer replacement',
            'description' => 'The finance printer is dead.',
            'priority' => Ticket::PRIORITY_NORMAL,
            'status' => Ticket::STATUS_SUBMITTED,
        ]);
    }

    private function department(string $name, bool $isIt): Department
    {
        return Department::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true, 'is_it_department' => $isIt],
        );
    }

    private function user(string $name, string $email, Department $department): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => User::ROLE_DEPARTMENT_USER,
            'level' => User::LEVEL_OFFICER,
            'department_id' => $department->id,
            'is_active' => true,
        ]);
    }
}
