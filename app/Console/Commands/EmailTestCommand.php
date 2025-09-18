<?php

namespace App\Console\Commands;

use App\Services\EmailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test 
                            {email : Email address to send test to}
                            {--type=invitation : Type of email to test (invitation, welcome)}
                            {--sync : Send synchronously instead of queued}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email configuration by sending a test email';

    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $type = $this->option('type');
        $sync = $this->option('sync');

        $this->info("Testing email configuration...");
        $this->info("Email: {$email}");
        $this->info("Type: {$type}");
        $this->info("Mode: " . ($sync ? 'Synchronous' : 'Queued'));

        try {
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error('Invalid email address');
                return 1;
            }

            // Test email configuration
            $result = $this->testEmailConfiguration($email, $type, $sync);

            if ($result['success']) {
                $this->info('✅ Test email sent successfully!');
                $this->info("Message: {$result['message']}");
                
                if (isset($result['tracking_id'])) {
                    $this->info("Tracking ID: {$result['tracking_id']}");
                }
            } else {
                $this->error('❌ Failed to send test email');
                $this->error("Error: {$result['message']}");
                return 1;
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Test failed: ' . $e->getMessage());
            Log::error('Email test failed', [
                'email' => $email,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return 1;
        }
    }

    /**
     * Test email configuration
     */
    private function testEmailConfiguration(string $email, string $type, bool $sync): array
    {
        try {
            if ($type === 'invitation') {
                return $this->testInvitationEmail($email, $sync);
            } elseif ($type === 'welcome') {
                return $this->testWelcomeEmail($email, $sync);
            } else {
                return $this->testSimpleEmail($email);
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test invitation email
     */
    private function testInvitationEmail(string $email, bool $sync): array
    {
        // Create a test invitation
        $invitation = new \App\Models\Invitation([
            'email' => $email,
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => 'user',
            'organization_id' => 1,
            'invited_by' => 1,
            'expires_at' => now()->addDays(7),
            'token' => 'test-token-' . uniqid(),
        ]);

        // Create test organization
        $organization = new \App\Models\Organization([
            'id' => 1,
            'name' => 'Test Organization',
            'slug' => 'test-org',
            'domain' => 'test.com',
            'description' => 'Test organization',
            'status' => 'active',
        ]);

        $invitation->setRelation('organization', $organization);

        if ($sync) {
            $result = $this->emailService->sendInvitationEmailSync($invitation);
        } else {
            $result = $this->emailService->sendInvitationEmail($invitation);
        }

        return [
            'success' => $result,
            'message' => $result ? 'Invitation email sent successfully' : 'Failed to send invitation email',
        ];
    }

    /**
     * Test welcome email
     */
    private function testWelcomeEmail(string $email, bool $sync): array
    {
        // Create test organization first
        $organization = new \App\Models\Organization([
            'id' => 1,
            'name' => 'Test Organization',
            'slug' => 'test-org',
            'domain' => 'test.com',
            'description' => 'Test organization',
            'status' => 'active',
        ]);

        // Create a test user
        $user = new \App\Models\User([
            'id' => 1,
            'name' => 'Test User',
            'email' => $email,
            'role' => 'user',
            'organization_id' => 1,
            'status' => 'active',
            'joined_at' => now(),
            'email_verified_at' => now(),
        ]);

        $user->setRelation('organization', $organization);

        if ($sync) {
            $result = $this->emailService->sendWelcomeEmailSync($user);
        } else {
            $result = $this->emailService->sendWelcomeEmail($user);
        }

        return [
            'success' => $result,
            'message' => $result ? 'Welcome email sent successfully' : 'Failed to send welcome email',
        ];
    }

    /**
     * Test simple email
     */
    private function testSimpleEmail(string $email): array
    {
        try {
            Mail::raw('This is a test email from ZenaManage. Your email configuration is working correctly!', function ($message) use ($email) {
                $message->to($email)
                        ->subject('ZenaManage Email Test')
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });

            return [
                'success' => true,
                'message' => 'Simple test email sent successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}