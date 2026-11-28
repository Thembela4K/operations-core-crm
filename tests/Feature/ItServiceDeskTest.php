<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Requisition;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * IT Service Desk (report section 4): any employee raises a ticket, IT triages
 * it, and a ticket can seed a requisition.
 */
class ItServiceDeskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\TicketCategorySeeder::class);
    }

    public function test_any_employee_can_raise_a_ticket(): void
    {
        $department = $this->department('Finance');
        $employee = $this->user('Finance Clerk', 'clerk@example.com', $department);

        $response = $this->actingAs($employee)->post(route('tickets.store'), [
            'title' => 'Printer will not print',
            'description' => 'The finance printer shows offline.',
            'priority' => Ticket::PRIORITY_NORMAL,
        ]);

        $ticket = Ticket::query()->firstOrFail();

        $response->assertRedirect(route('tickets.show', $ticket));

        $this->assertStringStartsWith('TKT-', $ticket->ticket_number);
        $this->assertSame($employee->id, $ticket->requester_id);
        $this->assertSame($department->id, $ticket->department_id);
        $this->assertSame(Ticket::STATUS_SUBMITTED, $ticket->status);

        // The first transition is recorded.
        $this->assertSame(1, $ticket->statusHistory()->count());
    }

    public function test_a_requester_sees_only_their_own_tickets(): void
    {
        $department = $this->department('Finance');
        $mine = $this->user('Mine', 'mine@example.com', $department);
        $other = $this->user('Other', 'other@example.com', $department);

        $this->ticket($mine, 'My laptop is slow');
        $this->ticket($other, 'Someone else issue');

        $this->actingAs($mine)->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('My laptop is slow')
            ->assertDontSee('Someone else issue');
    }

    public function test_a_requester_cannot_change_the_status(): void
    {
        $department = $this->department('Finance');
        $employee = $this->user('Finance Clerk', 'clerk@example.com', $department);
        $ticket = $this->ticket($employee, 'Cannot log in');

        $this->actingAs($employee)
            ->post(route('tickets.status', $ticket), ['status' => Ticket::STATUS_RESOLVED])
            ->assertForbidden();

        $this->assertSame(Ticket::STATUS_SUBMITTED, $ticket->fresh()->status);
    }

    public function test_the_it_officer_can_triage_and_resolve(): void
    {
        $it = $this->department('IT Department');
        $officer = $this->user('IT Officer', 'it.officer@example.com', $it);
        $employee = $this->user('Finance Clerk', 'clerk@example.com', $this->department('Finance'));
        $ticket = $this->ticket($employee, 'Email not syncing');

        // The officer sees the whole queue.
        $this->actingAs($officer)->get(route('tickets.index'))
            ->assertOk()
            ->assertSee('Email not syncing');

        $this->actingAs($officer)->post(route('tickets.assign', $ticket), [
            'assigned_to' => $officer->id,
        ])->assertRedirect();

        $this->assertSame($officer->id, $ticket->fresh()->assigned_to);
        $this->assertSame(Ticket::STATUS_ASSIGNED, $ticket->fresh()->status);

        $this->actingAs($officer)->post(route('tickets.status', $ticket), [
            'status' => Ticket::STATUS_RESOLVED,
            'resolution_note' => 'Rebuilt the mail profile.',
        ])->assertRedirect();

        $ticket = $ticket->fresh();

        $this->assertSame(Ticket::STATUS_RESOLVED, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertSame(2, $ticket->statusHistory()->count());
    }

    public function test_an_internal_note_is_marked_and_hidden_from_the_requester(): void
    {
        $it = $this->department('IT Department');
        $officer = $this->user('IT Officer', 'it.officer@example.com', $it);
        $employee = $this->user('Finance Clerk', 'clerk@example.com', $this->department('Finance'));
        $ticket = $this->ticket($employee, 'Screen flickering');

        $this->actingAs($officer)->post(route('tickets.comments.store', $ticket), [
            'body' => 'Known batch of faulty panels.',
            'is_internal' => 1,
        ])->assertRedirect();

        $comment = TicketComment::query()->firstOrFail();
        $this->assertTrue($comment->is_internal);

        // A requester cannot post an internal note; it is downgraded to public.
        $this->actingAs($employee)->post(route('tickets.comments.store', $ticket), [
            'body' => 'Any news?',
            'is_internal' => 1,
        ])->assertRedirect();

        $requesterComment = TicketComment::query()->where('user_id', $employee->id)->firstOrFail();
        $this->assertFalse($requesterComment->is_internal);
    }

    public function test_a_ticket_can_seed_a_requisition(): void
    {
        $it = $this->department('IT Department');
        $officer = $this->user('IT Officer', 'it.officer@example.com', $it);
        $ticket = $this->ticket($this->user('Finance Clerk', 'clerk@example.com', $this->department('Finance')), 'Need a replacement printer');

        $this->actingAs($officer)
            ->post(route('tickets.requisition', $ticket))
            ->assertRedirect(route('requisitions.create', ['source_ticket_id' => $ticket->id]));

        $this->actingAs($officer)
            ->get(route('requisitions.create', ['source_ticket_id' => $ticket->id]))
            ->assertOk()
            ->assertSee($ticket->ticket_number)
            ->assertSee('Need a replacement printer')
            ->assertSee('source_ticket_id', false);
    }

    public function test_a_requisition_keeps_its_source_ticket(): void
    {
        $ticket = $this->ticket($this->user('Finance Clerk', 'clerk@example.com', $this->department('Finance')), 'Laptop battery');

        $requisition = Requisition::query()->create([
            'source_ticket_id' => $ticket->id,
            'requisition_number' => 'REQ-TEST-1',
            'addressed_to' => 'Directors',
            'title' => 'Replacement battery',
            'category' => 'Operational',
            'priority' => 'Medium',
            'status' => Requisition::STATUS_DRAFT,
            'requested_by' => $ticket->requester_id,
        ]);

        $this->assertSame($ticket->id, $requisition->source_ticket_id);
        $this->assertSame(1, $ticket->requisitions()->count());
    }

    public function test_a_requester_can_attach_a_file_to_their_ticket(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $employee = $this->user('Finance Clerk', 'clerk@example.com', $this->department('Finance'));
        $ticket = $this->ticket($employee, 'Screen shows an error');

        $this->actingAs($employee)->post(route('tickets.attachments.store', $ticket), [
            'document' => \Illuminate\Http\UploadedFile::fake()->image('screenshot.png', 800, 600),
            'title' => 'Error screenshot',
        ])->assertRedirect();

        $this->assertSame(1, $ticket->documents()->count());
        $this->assertSame($ticket->id, $ticket->documents()->first()->documentable_id);
    }

    public function test_a_mis_officer_is_not_the_it_officer(): void
    {
        $mis = $this->department('MIS Department');
        $misOfficer = $this->user('MIS Officer', 'mis.officer@example.com', $mis, User::LEVEL_OFFICER);
        $employee = $this->user('Finance Clerk', 'clerk@example.com', $this->department('Finance'));
        $ticket = $this->ticket($employee, 'Someone else issue');

        $this->assertFalse($misOfficer->fresh()->canTriageItTickets());

        $this->actingAs($misOfficer)
            ->post(route('tickets.status', $ticket), ['status' => Ticket::STATUS_RESOLVED])
            ->assertForbidden();
    }

    public function test_a_director_receives_requisitions_and_does_not_triage_tickets(): void
    {
        $director = User::query()->create([
            'name' => 'Director',
            'email' => 'director.tickets@example.com',
            'password' => 'password',
            'role' => User::ROLE_DIRECTOR,
            'is_active' => true,
        ]);

        $ticket = $this->ticket($this->user('Finance Clerk', 'clerk@example.com', $this->department('Finance')), 'Needs triage');

        $this->assertFalse($director->canTriageItTickets());

        $this->actingAs($director)
            ->post(route('tickets.status', $ticket), ['status' => Ticket::STATUS_RESOLVED])
            ->assertForbidden();
    }

    public function test_a_department_user_in_the_it_department_triages_without_needing_a_level(): void
    {
        $it = $this->department('IT Department');
        $itOfficer = $this->user('IT Person', 'it.person@example.com', $it);

        $this->assertTrue($itOfficer->fresh()->canTriageItTickets());
    }

    public function test_ticket_attachments_are_carried_onto_the_requisition(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        $it = $this->department('IT Department');
        $officer = $this->user('IT Officer', 'it.officer@example.com', $it);
        $ticket = $this->ticket($this->user('Finance Clerk', 'clerk@example.com', $this->department('Finance')), 'Replacement printer');

        $this->actingAs($officer)->post(route('tickets.attachments.store', $ticket), [
            'document' => \Illuminate\Http\UploadedFile::fake()->create('quote.pdf', 40, 'application/pdf'),
        ])->assertRedirect();

        $this->actingAs($officer)->post(route('requisitions.store'), [
            'source_ticket_id' => $ticket->id,
            'addressed_to' => 'Directors',
            'title' => 'Replace finance printer',
            'category' => 'Operational',
            'priority' => 'Medium',
            'action' => 'submit',
            'items' => [
                ['description' => 'Laser printer', 'payment_type' => 'Bank', 'quantity' => 1, 'estimated_unit_cost' => 4500],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $requisition = Requisition::query()->firstOrFail();

        $this->assertSame($ticket->id, $requisition->source_ticket_id);
        $this->assertSame(1, $requisition->documents()->count());
        $this->assertSame('quote.pdf', $requisition->documents()->first()->original_name);
    }

    private function ticket(User $requester, string $title): Ticket
    {
        return Ticket::query()->create([
            'ticket_number' => 'TKT-'.strtoupper(Str::random(8)),
            'requester_id' => $requester->id,
            'department_id' => $requester->department_id,
            'ticket_category_id' => TicketCategory::query()->value('id'),
            'created_by' => $requester->id,
            'title' => $title,
            'description' => 'Reported issue',
            'priority' => Ticket::PRIORITY_NORMAL,
            'status' => Ticket::STATUS_SUBMITTED,
        ]);
    }

    private function department(string $name): Department
    {
        return Department::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            [
                'name' => $name,
                'is_active' => true,
                // Triage is tied to the IT Department record, not to a name.
                'is_it_department' => $name === 'IT Department',
            ],
        );
    }

    private function user(string $name, string $email, Department $department, ?string $level = null): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'role' => User::ROLE_DEPARTMENT_USER,
                'level' => $level,
                'department_id' => $department->id,
                'is_active' => true,
            ],
        );
    }
}
