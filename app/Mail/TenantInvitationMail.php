<?php declare(strict_types=1);

namespace App\Mail;

use App\Models\TenantInvitation;
use App\Support\TenantInvitationUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * TenantInvitationMail
 * 
 * Mailable for sending tenant invitation emails.
 */
class TenantInvitationMail extends Mailable
{
    use Queueable;

    public TenantInvitation $invitation;
    public string $tenantName;
    public string $roleLabel;
    public string $inviterName;
    public string $inviteUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        TenantInvitation $invitation,
        string $tenantName,
        string $roleLabel,
        string $inviterName
    ) {
        $this->invitation = $invitation;
        $this->tenantName = $tenantName;
        $this->roleLabel = $roleLabel;
        $this->inviterName = $inviterName;
        $this->inviteUrl = TenantInvitationUrl::buildUrl($invitation->token);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->tenantName} on Zenamanage",
            replyTo: config('mail.from.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant.invitation',
            with: [
                'invitation' => $this->invitation,
                'tenantName' => $this->tenantName,
                'roleLabel' => $this->roleLabel,
                'inviterName' => $this->inviterName,
                'inviteUrl' => $this->inviteUrl,
                'expiresAt' => $this->invitation->expires_at?->format('F d, Y \a\t g:i A') ?? 'N/A',
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

