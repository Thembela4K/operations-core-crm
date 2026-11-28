<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The IT queue carries the four actions (requisition, resolve, delay, rejected)
 * and the issuer can follow their ticket's status.
 */
class TicketQueueActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_queue_offers_four_actions_to_the_it_officer(): void
    {
        $it = $this->department('IT Department', true);
        $officer = $this->user('IT Officer', 'it.queue@example.com', $it);
        $requester = $this->user('Requester', 'req.queue@example.com', $this->department('Finance', false));

        $this->ticket($requester, 'Broken laptop');

        $page = $this->actingAs($officer)->get(route('tickets.index'));
        $page->assertOk()
            ->assertSee('Requisition')
            ->assertSee('Resolve')
            ->assertSee('Delay')
            ->assertSee('Rejected');
    }

    public function test_a_requester_does_not_get_queue_actions_but_sees_the_status(): void
    {
        $it = $this->department('IT Department', true);
        $requester = $this->user('Requester', 'req.queue2@example.com', $this->department('Finance', false));

        $ticket = $this->ticket($requester, 'Printer offline');
        $ticket->update(['status' => Ticket::STATUS_DELAYED]);

        $page = $this->actingAs($requester)->get(route('tickets.index'));
        $page->assertOk()
            ->assertSee('Printer offline')
            ->assertSee(Ticket::STATUS_DELAYED)
            ->assertDontSee('Requisition');
    }

    public function test_a_director_only_sees_their_own_tickets_and_can_raise_one(): void
    {
        $it = $this->department('IT Department', true);
        $director = $this->user('Director', 'director.queue@example.com', $it, User::ROLE_DIRECTOR);
        $employee = $this->user('Employee', 'employee.queue@example.com', $this->department('Finance', false));

        $this->ticket($employee, 'Someone else issue');
        $this->ticket($director, 'My own issue');

        $page = $this->actingAs($director)->get(route('tickets.index'));
        $page->assertOk()
            ->assertSee('My own issue')
            ->assertDontSee('Someone else issue')
            ->assertDontSee('Requisition');

        // A director may raise their own ticket to IT.
        $this->actingAs($director)->post(route('tickets.store'), [
            'title' => 'Boardroom screen replacement',
            'description' => 'The screen flickers.',
            'priority' => Ticket::PRIORITY_NORMAL,
        ])->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'title' => 'Boardroom screen replacement',
            'requester_id' => $director->id,
        ]);
    }

    public function test_the_quick_actions_change_the_status(): void
    {
        $it = $this->department('IT Department', true);
        $officer = $this->user('IT Officer', 'it.queue3@example.com', $it);
        $requester = $this->user('Requester', 'req.queue3@example.com', $this->department('Finance', false));

        $ticket = $this->ticket($requester, 'Screen flicker');

        $this->actingAs($officer)
            ->post(route('tickets.status', $ticket), ['status' => Ticket::STATUS_DELAYED])
            ->assertRedirect();
        $this->assertSame(Ticket::STATUS_DELAYED, $ticket->fresh()->status);

        $this->actingAs($officer)
            ->post(route('tickets.status', $ticket), ['status' => Ticket::STATUS_REJECTED])
            ->assertRedirect();
        $this->assertSame(Ticket::STATUS_REJECTED, $ticket->fresh()->status);

        $this->actingAs($officer)
            ->post(route('tickets.status', $ticket), ['status' => Ticket::STATUS_RESOLVED])
            ->assertRedirect();
        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->fresh()->status);
    }

    public function test_the_requester_sees_their_ticket_progress_including_requisitions(): void
    {
        $it = $this->department('IT Department', true);
        $requester = $this->user('Requester', 'req.queue4@example.com', $this->department('Finance', false));
        $ticket = $this->ticket($requester, 'Need a monitor');

        $ticket->requisitions()->create([
            'department_id' => $ticket->department_id,
            'requisition_number' => 'REQ-QUEUE-1',
            'addressed_to' => 'Directors',
            'title' => 'IT procurement: Need a monitor',
            'category' => 'Operational',
            'priority' => 'Medium',
            'status' => 'Submitted',
            'requested_by' => $requester->id,
            'estimated_total' => 2500,
        ]);

        $this->actingAs($requester)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('Progress')
            ->assertSee('REQ-QUEUE-1')
            ->assertSee('Submitted');
    }

    private function ticket(User $requester, string $title): Ticket
    {
        return Ticket::query()->create([
            'ticket_number' => 'TKT-'.strtoupper(Str::random(6)),
            'requester_id' => $requester->id,
            'department_id' => $requester->department_id,
            'created_by' => $requester->id,
            'title' => $title,
            'description' => 'Reported issue',
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

    private function user(string $name, string $email, Department $department, string $role = User::ROLE_DEPARTMENT_USER): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'level' => User::LEVEL_OFFICER,
            'department_id' => $department->id,
            'is_active' => true,
        ]);
    }
}
