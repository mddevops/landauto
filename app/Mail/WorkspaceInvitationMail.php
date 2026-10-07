<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sent synchronously right after the invitation is created or resent: the one-time URL carries
 * the raw token, which must never be queued or stored.
 */
class WorkspaceInvitationMail extends Mailable
{
    public function __construct(
        public string $workspaceName,
        public ?string $inviterName,
        public string $roleLabel,
        public string $expiresAt,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Приглашение в пространство «'.$this->workspaceName.'» в Landflow');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.workspace-invitation');
    }
}
