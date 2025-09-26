<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvitationEmail extends Mailable implements ShouldQueue
{

    public $invitation;
    public $acceptUrl;
    public $organizationName;
    public $inviterName;

    /**
     * Create a new message instance.
     */
    public function __construct(Invitation $invitation)
    {
        $this->invitation = $invitation;
        $this->acceptUrl = config('app.url') . "/invitations/accept/{$invitation->token}";
        $this->organizationName = $invitation->organization->name ?? 'Our Organization';
        $this->inviterName = $invitation->inviter->name ?? 'Administrator';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You're invited to join {$this->organizationName}",
            replyTo: config('mail.from.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
            with: [
                'invitation' => $this->invitation,
                'acceptUrl' => $this->acceptUrl,
                'organizationName' => $this->organizationName,
                'inviterName' => $this->inviterName,
                'projectName' => $this->invitation->project->name ?? null,
                'roleDisplayName' => $this->getRoleDisplayName(),
                'expiresAt' => $this->invitation->expires_at->format('F d, Y \a\t g:i A'),
                'daysUntilExpiry' => $this->invitation->expires_at->diffInDays(now()),
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

    /**
     * Get display name for the role
     */
    private function getRoleDisplayName(): string
    {
        $roleMap = [
            'super_admin' => 'Super Administrator',
            'admin' => 'Administrator',
            'project_manager' => 'Project Manager',
            'designer' => 'Designer',
            'site_engineer' => 'Site Engineer',
            'qc_engineer' => 'QC Engineer',
            'procurement' => 'Procurement Specialist',
            'finance' => 'Finance Specialist',
            'client' => 'Client',
            'user' => 'User',
        ];

        return $roleMap[$this->invitation->role] ?? ucfirst(str_replace('_', ' ', $this->invitation->role));
    }
}