<?php declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes
    public $tries = 3;
    public $backoff = [30, 120, 300]; // 30sec, 2min, 5min

    public $to;
    public $subject;
    public $template;
    public $data;
    public $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $to,
        string $subject,
        string $template,
        array $data = [],
        string $tenantId = null
    ) {
        $this->to = $to;
        $this->subject = $subject;
        $this->template = $template;
        $this->data = $data;
        $this->tenantId = $tenantId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Sending email', [
                'to' => $this->to,
                'subject' => $this->subject,
                'template' => $this->template,
                'tenant_id' => $this->tenantId,
                'attempt' => $this->attempts()
            ]);

            // Send email using Laravel Mail
            Mail::send($this->template, $this->data, function ($message) {
                $message->to($this->to)
                       ->subject($this->subject);
            });

            Log::info('Email sent successfully', [
                'to' => $this->to,
                'subject' => $this->subject
            ]);

        } catch (\Exception $e) {
            Log::error('Email sending failed', [
                'to' => $this->to,
                'subject' => $this->subject,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Email job failed permanently', [
            'to' => $this->to,
            'subject' => $this->subject,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Could store failed email in database for manual retry
        \DB::table('failed_emails')->insert([
            'to' => $this->to,
            'subject' => $this->subject,
            'template' => $this->template,
            'data' => json_encode($this->data),
            'error_message' => $exception->getMessage(),
            'tenant_id' => $this->tenantId,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
