<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeadlineReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $item
     */
    public function __construct(public array $item) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->item['subject'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.deadline-reminder',
            text: 'emails.text.deadline-reminder',
        );
    }
}
