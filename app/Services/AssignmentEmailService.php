<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Project;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AssignmentEmailService
{
    public function send(Assignment $assignment): string
    {
        $assignment->loadMissing(['assignable', 'department']);
        $assignable = $assignment->assignable;
        $reference = $assignable->project_code ?? $assignable->quotation_code;
        $title = $assignable->name ?? $assignable->opportunity;
        $dueLabel = $assignable instanceof Project ? 'Deadline' : 'Valid Until';
        $dueDate = $assignable instanceof Project ? $assignable->deadline : $assignable->valid_until;
        $subject = "Assignment: {$reference} routed to {$assignment->department->name}";

        $body = implode(PHP_EOL, [
            'Work assignment',
            '',
            "Reference: {$reference}",
            "Title: {$title}",
            "Assigned department: {$assignment->department->name}",
            "Assigned to: {$assignment->assignee_name}",
            "Tracking owner: {$assignable->owner}",
            "Status: {$assignable->status}",
            "Priority: {$assignable->priority}",
            "{$dueLabel}: {$dueDate->toDateString()}",
            "Attached documents in app: {$assignable->documents()->count()}",
            '',
            'Please review and action this assignment.',
        ]);

        try {
            Mail::raw($body, function ($message) use ($assignment, $subject): void {
                $message->to($assignment->assignee_email)
                    ->subject($subject);
            });

            $status = 'Assignment Email Sent';
            $message = '';
        } catch (Throwable $exception) {
            $status = 'Assignment Email Failed';
            $message = $exception->getMessage();
        }

        $assignable->emailLogs()->create([
            'category' => 'assignment',
            'recipient' => $assignment->assignee_email,
            'subject' => $subject,
            'status' => $status === 'Assignment Email Sent' ? 'Sent' : 'Failed',
            'message' => $message,
            'sent_at' => now(),
        ]);

        $assignment->update(['status' => $status]);

        return $status;
    }
}
