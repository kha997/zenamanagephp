<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use App\Models\User;

class SystemEmail extends Mailable
{
    use Queueable;

    public $user;
    public $systemType;
    public $systemTitle;
    public $systemMessage;
    public $systemUrl;
    public $systemData;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $systemType, string $systemTitle, string $systemMessage, string $systemUrl = null, array $systemData = [])
    {
        $this->user = $user;
        $this->systemType = $systemType;
        $this->systemTitle = $systemTitle;
        $this->systemMessage = $systemMessage;
        $this->systemUrl = $systemUrl ?? config('app.url') . '/app/dashboard';
        $this->systemData = $systemData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[System] {$this->systemTitle}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.system',
            with: [
                'user' => $this->user,
                'systemType' => $this->systemType,
                'systemTitle' => $this->systemTitle,
                'systemMessage' => $this->systemMessage,
                'systemUrl' => $this->systemUrl,
                'systemData' => $this->systemData,
                'sentAt' => now()->format('F d, Y \a\t g:i A'),
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
