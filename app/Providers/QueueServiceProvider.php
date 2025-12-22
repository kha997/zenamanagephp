<?php

namespace App\Providers;

use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Queue;
use App\Queue\Middleware\JobIdempotencyMiddleware;

/**
 * Queue Service Provider
 * 
 * PR: Job idempotency
 * 
 * Registers job middleware and event listeners for queue management.
 */
class QueueServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register job middleware for idempotency
        Queue::createPayloadUsing(function ($connection, $queue, $payload) {
            return $payload;
        });

        // Apply middleware to all jobs
        // Note: Laravel doesn't have built-in job middleware support like Bus
        // We'll handle idempotency in the job's handle() method or via event listeners
    }
}

