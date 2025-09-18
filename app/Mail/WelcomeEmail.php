<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $organizationName;
    public $roleDisplayName;
    public $dashboardUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        $this->organizationName = $user->organization->name ?? 'Our Organization';
        $this->roleDisplayName = $this->getRoleDisplayName($user->role ?? 'user');
        $this->dashboardUrl = config('app.url') . '/dashboard';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Welcome to {$this->organizationName}!",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: [
                'user' => $this->user,
                'organizationName' => $this->organizationName,
                'roleDisplayName' => $this->roleDisplayName,
                'dashboardUrl' => $this->dashboardUrl,
            ],
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
     * Get role display name
     */
    private function getRoleDisplayName(string $role): string
    {
        $roleNames = [
            'super_admin' => 'Super Administrator',
            'admin' => 'Administrator',
            'project_manager' => 'Project Manager',
            'designer' => 'Designer',
            'site_engineer' => 'Site Engineer',
            'qc_engineer' => 'QC Engineer',
            'user' => 'User',
        ];

        return $roleNames[$role] ?? ucfirst($role);
    }
}