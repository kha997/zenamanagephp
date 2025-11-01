<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\User;
use App\Models\Notification;

class NotificationEmail extends Mailable
{
    use Queueable;

    public $user;
    public $notification;
    public $notificationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Notification $notification)
    {
        $this->user = $user;
        $this->notification = $notification;
        $this->notificationUrl = $notification->link_url ?? config('app.url') . '/app/notifications';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->title,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'user' => $this->user,
                'notification' => $this->notification,
                'notificationUrl' => $this->notificationUrl,
                'priority' => ucfirst($this->notification->priority),
                'type' => ucfirst($this->notification->type),
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
