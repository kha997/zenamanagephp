<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Jobs\SendWelcomeEmailJob;
use App\Jobs\CleanupJob;
use App\Jobs\BackupJob;
use App\Mail\WelcomeEmail;
use App\Mail\PasswordResetEmail;
use App\Mail\AlertEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;

class BackgroundJobsUnitTest extends TestCase
{
    /** @test */
    public function jobs_have_proper_timeout_and_retry_settings()
    {
        $user = new User();
        $user->id = 1;
        $user->email = 'test@example.com';
        $user->name = 'Test User';
        
        $welcomeJob = new SendWelcomeEmailJob($user);
        $this->assertEquals(60, $welcomeJob->timeout);
        $this->assertEquals(3, $welcomeJob->tries);
        
        $cleanupJob = new CleanupJob();
        $this->assertEquals(300, $cleanupJob->timeout);
        $this->assertEquals(1, $cleanupJob->tries);
        
        $backupJob = new BackupJob('full');
        $this->assertEquals(1800, $backupJob->timeout);
        $this->assertEquals(1, $backupJob->tries);
    }

    /** @test */
    public function jobs_have_proper_queue_assignments()
    {
        $user = new User();
        $user->id = 1;
        $user->email = 'test@example.com';
        $user->name = 'Test User';
        
        $welcomeJob = new SendWelcomeEmailJob($user);
        $this->assertEquals('emails-welcome', $welcomeJob->onQueue);
        
        $cleanupJob = new CleanupJob();
        $this->assertEquals('cleanup', $cleanupJob->onQueue);
        
        $backupJob = new BackupJob('full');
        $this->assertEquals('backup', $backupJob->onQueue);
    }

    /** @test */
    public function mail_classes_can_be_instantiated()
    {
        $user = new User();
        $user->id = 1;
        $user->email = 'test@example.com';
        $user->name = 'Test User';
        
        $welcomeEmail = new WelcomeEmail($user);
        $this->assertInstanceOf(WelcomeEmail::class, $welcomeEmail);
        
        $passwordResetEmail = new PasswordResetEmail($user, 'test-token');
        $this->assertInstanceOf(PasswordResetEmail::class, $passwordResetEmail);
        
        $alertEmail = new AlertEmail($user, 'system', 'Test Alert', 'This is a test alert');
        $this->assertInstanceOf(AlertEmail::class, $alertEmail);
    }

    /** @test */
    public function jobs_can_be_dispatched()
    {
        Queue::fake();
        
        $user = new User();
        $user->id = 1;
        $user->email = 'test@example.com';
        $user->name = 'Test User';
        
        SendWelcomeEmailJob::dispatch($user);
        
        Queue::assertPushed(SendWelcomeEmailJob::class);
    }

    /** @test */
    public function mail_can_be_sent()
    {
        Mail::fake();
        
        $user = new User();
        $user->id = 1;
        $user->email = 'test@example.com';
        $user->name = 'Test User';
        
        Mail::to($user->email)->send(new WelcomeEmail($user));
        
        Mail::assertSent(WelcomeEmail::class);
    }
}
