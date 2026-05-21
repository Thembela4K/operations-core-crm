<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\TenderProposal;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AssignmentEmailService
{
    public function send(Assignment $assignment): string
    {
        $assignment->loadMissing(['assignable', 'department']);
        $assignable = $assignment->assignable;
        $reference = $assignable->tender_reference ?? $assignable->quotation_code;
        $title = $assignable->title ?? $assignable->opportunity;
        $dueLabel = $assignment->due_date ? 'Assignment Due Date' : ($assignable instanceof TenderProposal ? 'Closing Date' : 'Valid Until');
        $dueDate = $assignment->due_date ?: ($assignable instanceof TenderProposal ? $assignable->closing_date : $assignable->valid_until);
        $portalUrl = $assignable instanceof TenderProposal
            ? route('tender-proposals.show', $assignable)
            : route('quotations.show', $assignable);
        $importantDates = $assignable instanceof TenderProposal
            ? $assignable->importantDates()->get()->map(fn ($date): string => "{$date->label}: {$date->due_date->toDateString()}")->implode(PHP_EOL)
            : '';
        $subject = "Assignment: {$reference} routed to {$assignment->department->name}";

        $bodyLines = [
            'Work assignment',
            '',
            "Reference: {$reference}",
            "Title: {$title}",
            "Assigned department: {$assignment->department->name}",
            "Assigned to: {$assignment->assignee_name}",
            "Status: {$assignable->status}",
            "Priority: {$assignable->priority}",
            "{$dueLabel}: {$dueDate->toDateString()}",
            $assignment->instructions ? "Instructions: {$assignment->instructions}" : null,
            $importantDates ? 'Important dates:'.PHP_EOL.$importantDates : null,
            "Documents available in portal: {$assignable->documents()->count()}",
            "Portal link: {$portalUrl}",
            '',
            'Please review and action this assignment in the portal.',
        ];

        $body = implode(PHP_EOL, array_filter($bodyLines, fn ($line): bool => $line !== null));

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
