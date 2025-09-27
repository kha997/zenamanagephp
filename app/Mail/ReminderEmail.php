<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\User;

class ReminderEmail extends Mailable
{
    use Queueable;

    public $user;
    public $reminderType;
    public $reminderTitle;
    public $reminderMessage;
    public $reminderUrl;
    public $dueDate;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $reminderType, string $reminderTitle, string $reminderMessage, string $reminderUrl = null, $dueDate = null)
    {
        $this->user = $user;
        $this->reminderType = $reminderType;
        $this->reminderTitle = $reminderTitle;
        $this->reminderMessage = $reminderMessage;
        $this->reminderUrl = $reminderUrl ?? config('app.url') . '/app/dashboard';
        $this->dueDate = $dueDate;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder: {$this->reminderTitle}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reminder',
            with: [
                'user' => $this->user,
                'reminderType' => $this->reminderType,
                'reminderTitle' => $this->reminderTitle,
                'reminderMessage' => $this->reminderMessage,
                'reminderUrl' => $this->reminderUrl,
                'dueDate' => $this->dueDate?->format('F d, Y \a\t g:i A'),
                'remindedAt' => now()->format('F d, Y \a\t g:i A'),
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
