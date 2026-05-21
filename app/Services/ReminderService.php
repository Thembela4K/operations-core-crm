<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\TenderProposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ReminderService
{
    public const DAYS_BEFORE = 3;

    public function dueItems(): Collection
    {
        return $this->upcomingItems()->filter(fn (array $item): bool => (int) $item['days_left'] === self::DAYS_BEFORE)->values();
    }

    public function upcomingItems(): Collection
    {
        $items = collect();

        TenderProposal::query()
            ->whereNotIn('status', ['Closed', 'Cancelled'])
            ->with('latestAssignment.department')
            ->get()
            ->each(function (TenderProposal $tenderProposal) use ($items): void {
                $assignment = $tenderProposal->latestAssignment;
                $dueOn = $assignment?->due_date ?: $tenderProposal->closing_date;
                if ($dueOn->greaterThan(now()->addDays(self::DAYS_BEFORE))) {
                    return;
                }

                $items->push([
                    'type' => 'Tender Proposal',
                    'model' => $tenderProposal,
                    'reference' => $tenderProposal->tender_reference,
                    'title' => $tenderProposal->title,
                    'owner' => $assignment?->assignee_name ?: $tenderProposal->owner,
                    'owner_email' => $assignment?->assignee_email ?: $tenderProposal->owner_email,
                    'status' => $tenderProposal->status,
                    'priority' => $tenderProposal->priority,
                    'due_label' => $assignment?->due_date ? 'Assignment Due Date' : 'Closing Date',
                    'due_on' => $dueOn,
                    'days_left' => (int) now()->startOfDay()->diffInDays($dueOn, false),
                ]);
            });

        Quotation::query()
            ->whereNotIn('status', ['Accepted', 'Rejected', 'Expired'])
            ->with('latestAssignment.department')
            ->get()
            ->each(function (Quotation $quotation) use ($items): void {
                $assignment = $quotation->latestAssignment;
                $dueOn = $assignment?->due_date ?: $quotation->valid_until;
                if ($dueOn->greaterThan(now()->addDays(self::DAYS_BEFORE))) {
                    return;
                }

                $items->push([
                    'type' => 'Quotation',
                    'model' => $quotation,
                    'reference' => $quotation->quotation_code,
                    'title' => $quotation->opportunity,
                    'owner' => $assignment?->assignee_name ?: $quotation->owner,
                    'owner_email' => $assignment?->assignee_email ?: $quotation->owner_email,
                    'status' => $quotation->status,
                    'priority' => $quotation->priority,
                    'due_label' => $assignment?->due_date ? 'Assignment Due Date' : 'Valid Until',
                    'due_on' => $dueOn,
                    'days_left' => (int) now()->startOfDay()->diffInDays($dueOn, false),
                ]);
            });

        return $items->sortBy('due_on')->values();
    }

    public function sendDueReminders(): int
    {
        $sent = 0;

        foreach ($this->dueItems() as $item) {
            if ($this->sendReminder($item)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function sendReminder(array $item): bool
    {
        $model = $item['model'];
        $existing = $model->reminderLogs()
            ->whereDate('due_on', $item['due_on']->toDateString())
            ->where('days_before', self::DAYS_BEFORE)
            ->first();

        if ($existing) {
            return false;
        }

        $subject = "Reminder: {$item['type']} {$item['reference']} is due in {$item['days_left']} days";
        $body = implode(PHP_EOL, [
            "{$item['type']} reminder",
            '',
            "Reference: {$item['reference']}",
            "Title: {$item['title']}",
            "Recipient: {$item['owner']}",
            "Status: {$item['status']}",
            "Priority: {$item['priority']}",
            "{$item['due_label']}: {$item['due_on']->toDateString()}",
            '',
            'This reminder was generated 3 days before the deadline.',
        ]);

        try {
            if (! $item['owner_email']) {
                throw new \RuntimeException('Owner email is missing.');
            }

            Mail::raw($body, function ($message) use ($item, $subject): void {
                $message->to($item['owner_email'])
                    ->subject($subject);
            });

            $status = 'Sent';
            $message = '';
        } catch (Throwable $exception) {
            $status = 'Failed';
            $message = $exception->getMessage();
        }

        $model->reminderLogs()->create([
            'due_on' => $item['due_on'],
            'days_before' => self::DAYS_BEFORE,
            'recipient' => $item['owner_email'],
            'status' => $status,
            'message' => $message,
            'sent_at' => now(),
        ]);

        $model->emailLogs()->create([
            'category' => 'reminder',
            'recipient' => $item['owner_email'] ?: '',
            'subject' => $subject,
            'status' => $status,
            'message' => $message,
            'sent_at' => now(),
        ]);

        return $status === 'Sent';
    }
}
