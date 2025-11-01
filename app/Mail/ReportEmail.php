<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use App\Models\User;

class ReportEmail extends Mailable
{
    use Queueable;

    public $user;
    public $reportName;
    public $reportType;
    public $filePath;
    public $fileName;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $reportName, string $reportType, string $filePath, string $fileName)
    {
        $this->user = $user;
        $this->reportName = $reportName;
        $this->reportType = $reportType;
        $this->filePath = $filePath;
        $this->fileName = $fileName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Report Generated: {$this->reportName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.report',
            with: [
                'user' => $this->user,
                'reportName' => $this->reportName,
                'reportType' => $this->reportType,
                'fileName' => $this->fileName,
                'generatedAt' => now()->format('F d, Y \a\t g:i A'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath(storage_path("app/{$this->filePath}"))
                ->as($this->fileName)
                ->withMime($this->getMimeType()),
        ];
    }

    /**
     * Get MIME type for the file
     */
    protected function getMimeType(): string
    {
        $extension = pathinfo($this->fileName, PATHINFO_EXTENSION);
        
        return match (strtolower($extension)) {
            'pdf' => 'application/pdf',
            'xlsx', 'xls' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'json' => 'application/json',
            default => 'application/octet-stream'
        };
    }
}
