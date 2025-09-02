<?php declare(strict_types=1);

namespace Src\Foundation\Services;

use Src\Foundation\Helpers\AuthHelper;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Service class for handling event dispatching with safe actor ID resolution
 * 
 * @package Src\Foundation\Services
 */
class EventBus
{
    /**
     * Dispatch an event with safe actor ID handling
     *
     * @param object $event The event object to dispatch
     * @return void
     */
    public function dispatch(object $event): void
    {
        // Safely determine actor ID
        $actorId = $this->resolveActorId();
        
        // Set actor_id if the event object supports it
        if (property_exists($event, 'actor_id')) {
            $event->actor_id = $actorId;
        }
        
        // Dispatch the event
        Event::dispatch($event);
    }
    
    /**
     * Safely resolve the current actor ID
     *
     * @return string|int The actor ID or 'system' as fallback
     */
    private function resolveActorId()
    {
        try {
            // Check if user is authenticated using Auth facade
            if (AuthHelper::check()) {
                return AuthHelper::idOrSystem();
            }
        } catch (\Throwable $e) {
            // Log the error for debugging in non-production environments
            if (config('app.debug')) {
                Log::warning('EventBus: Unable to resolve actor ID', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        
        // Fallback to 'system' for test environments or when auth is unavailable
        return 'system';
    }
    
    /**
     * Dispatch multiple events with safe actor ID handling
     *
     * @param array $events Array of event objects
     * @return void
     */
    public function dispatchMultiple(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatch($event);
        }
    }
}