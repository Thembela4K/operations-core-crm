<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Quotation;
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

        Project::query()
            ->where('status', '!=', 'Completed')
            ->whereDate('deadline', '<=', now()->addDays(self::DAYS_BEFORE)->toDateString())
            ->with('latestAssignment.department')
            ->get()
            ->each(function (Project $project) use ($items): void {
                $items->push([
                    'type' => 'Project',
                    'model' => $project,
                    'reference' => $project->project_code,
                    'title' => $project->name,
                    'owner' => $project->owner,
                    'owner_email' => $project->owner_email,
                    'status' => $project->status,
                    'priority' => $project->priority,
                    'due_label' => 'Deadline',
                    'due_on' => $project->deadline,
                    'days_left' => (int) now()->startOfDay()->diffInDays($project->deadline, false),
                ]);
            });

        Quotation::query()
            ->whereNotIn('status', ['Accepted', 'Rejected', 'Expired'])
            ->whereDate('valid_until', '<=', now()->addDays(self::DAYS_BEFORE)->toDateString())
            ->with('latestAssignment.department')
            ->get()
            ->each(function (Quotation $quotation) use ($items): void {
                $items->push([
                    'type' => 'Quotation',
                    'model' => $quotation,
                    'reference' => $quotation->quotation_code,
                    'title' => $quotation->opportunity,
                    'owner' => $quotation->owner,
                    'owner_email' => $quotation->owner_email,
                    'status' => $quotation->status,
                    'priority' => $quotation->priority,
                    'due_label' => 'Valid Until',
                    'due_on' => $quotation->valid_until,
                    'days_left' => (int) now()->startOfDay()->diffInDays($quotation->valid_until, false),
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
            "Owner: {$item['owner']}",
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
