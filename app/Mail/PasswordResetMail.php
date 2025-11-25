<?php declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Password Reset Mail
 * 
 * Sends password reset link to users who request password reset.
 */
class PasswordResetMail extends Mailable
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
            subject: 'Reset Your Password - ZenaManage',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                'user' => $this->user,
                'resetUrl' => $this->getResetUrl(),
                'expiryHours' => 1, // Token expires in 1 hour
            ]
        );
    }

    /**
     * Get the password reset URL
     */
    private function getResetUrl(): string
    {
        // Frontend route for password reset
        // Use React frontend URL if active, otherwise use app URL
        $frontendConfig = config('frontend.systems.react', []);
        $frontendUrl = $frontendConfig['enabled'] 
            ? ($frontendConfig['base_url'] ?? config('app.url'))
            : config('app.url');
        
        return rtrim($frontendUrl, '/') . '/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $this->user->email,
        ]);
    }
}

