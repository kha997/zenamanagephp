<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        
        // Task Events
        \App\Events\TaskCreated::class => [
            \App\Listeners\SendTaskNotification::class,
        ],
        \App\Events\TaskCompleted::class => [
            \App\Listeners\UpdateProjectProgress::class,
        ],
        \App\Events\TaskUpdated::class => [
            \App\Listeners\LogTaskAudit::class,
        ],
        
        // Component Events
        \App\Events\ComponentCreated::class => [
            // Add listeners as needed
        ],
        
        // Change Request Events
        \App\Events\ChangeRequested::class => [
            // Add listeners as needed
        ],
        
        // Document Events
        \App\Events\DocumentUploaded::class => [
            // Add listeners as needed
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     *
     * @return bool
     */
    public function shouldDiscoverEvents()
    {
        return false;
    }
}