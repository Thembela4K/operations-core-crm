<?php

namespace App\Mail;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssignmentNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $importantDates
     * @param  array<int, array{name: string, category: string, download_url: string}>  $documents
     */
    public function __construct(
        public Assignment $assignment,
        public string $recordType,
        public string $reference,
        public string $title,
        public string $status,
        public string $priority,
        public string $dueLabel,
        public string $dueDate,
        public string $portalUrl,
        public array $importantDates,
        public int $documentCount,
        public array $documents,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Assignment: {$this->reference} routed to {$this->assignment->department->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.assignment-notification',
            text: 'emails.text.assignment-notification',
        );
    }
}
