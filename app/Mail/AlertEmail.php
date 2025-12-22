<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\User;

class AlertEmail extends Mailable
{
    use Queueable;

    public $user;
    public $alertType;
    public $alertTitle;
    public $alertMessage;
    public $alertUrl;
    public $priority;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $alertType, string $alertTitle, string $alertMessage, string $alertUrl = null, string $priority = 'normal')
    {
        $this->user = $user;
        $this->alertType = $alertType;
        $this->alertTitle = $alertTitle;
        $this->alertMessage = $alertMessage;
        $this->alertUrl = $alertUrl ?? config('app.url') . '/app/alerts';
        $this->priority = $priority;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->priority}] {$this->alertTitle}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.alert',
            with: [
                'user' => $this->user,
                'alertType' => $this->alertType,
                'alertTitle' => $this->alertTitle,
                'alertMessage' => $this->alertMessage,
                'alertUrl' => $this->alertUrl,
                'priority' => ucfirst($this->priority),
                'alertedAt' => now()->format('F d, Y \a\t g:i A'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
