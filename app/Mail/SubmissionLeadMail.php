<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Lead notification for an email Form Route. Sent synchronously inside the delivery job; every
 * value is escaped by the Blade template and the subject is already a sanitized single line.
 */
class SubmissionLeadMail extends Mailable
{
    /**
     * @param  array{form: string, site: string, site_url: string|null, submission: string, submitted_at: string, fields: list<array{label: string, value: string}>, context: list<array{label: string, value: string}>}  $lead
     */
    public function __construct(
        public string $subjectLine,
        public array $lead,
        public ?string $replyToAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
            replyTo: $this->replyToAddress === null ? [] : [new Address($this->replyToAddress)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.submission-lead');
    }
}
