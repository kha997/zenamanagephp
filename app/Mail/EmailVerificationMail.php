<?php declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email Verification Mail
 * 
 * Sends email verification link to new users.
 */
class EmailVerificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify Your Email Address - ZenaManage',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verification',
            with: [
                'user' => $this->user,
                'verificationUrl' => $this->getVerificationUrl(),
            ]
        );
    }

    /**
     * Get the verification URL
     */
    private function getVerificationUrl(): string
    {
        return route('verification.verify', [
            'id' => $this->user->id,
            'hash' => sha1($this->user->getEmailForVerification()),
        ]);
    }
}
