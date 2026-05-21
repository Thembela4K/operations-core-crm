<?php

namespace Tests\Feature;

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

        TenderProposal::query()->create([
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
        ]);

        $service = app(ReminderService::class);

        $this->assertSame(1, $service->sendDueReminders());
        $this->assertSame(0, $service->sendDueReminders());
        $this->assertDatabaseCount('reminder_logs', 1);
        $this->assertDatabaseCount('email_logs', 1);
    }
}
