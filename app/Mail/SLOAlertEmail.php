<?php declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * SLO Alert Email
 * 
 * PR: SLO/SLA nội bộ
 * 
 * Email notification for SLO violations
 */
class SLOAlertEmail extends Mailable
{
    use Queueable, SerializesModels;

    public array $violation;

    /**
     * Create a new message instance.
     */
    public function __construct(array $violation)
    {
        $this->violation = $violation;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $severity = strtoupper($this->violation['severity']);
        $category = ucfirst($this->violation['category']);
        $metric = $this->violation['metric'];

        return new Envelope(
            subject: "[{$severity}] SLO Violation: {$category}/{$metric}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.slo-alert',
            with: [
                'violation' => $this->violation,
                'severity' => ucfirst($this->violation['severity']),
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

