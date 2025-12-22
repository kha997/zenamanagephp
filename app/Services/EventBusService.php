<?php

namespace App\Services;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class EventBusService
{
    /**
     * Dispatch an event with automatic listener execution
     */
    public function dispatch(string $eventClass, ...$args): void
    {
        try {
            $event = new $eventClass(...$args);
            Event::dispatch($event);
            
            Log::info("Event dispatched successfully", [
                'event_class' => $eventClass,
                'event_data' => $this->extractEventData($event),
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to dispatch event", [
                'event_class' => $eventClass,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Dispatch an event asynchronously
     */
    public function dispatchAsync(string $eventClass, ...$args): void
    {
        try {
            $event = new $eventClass(...$args);

            Queue::push(function () use ($event) {
                Event::dispatch($event);
            });
            
            Log::info("Event dispatched asynchronously", [
                'event_class' => $eventClass,
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to dispatch event asynchronously", [
                'event_class' => $eventClass,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Dispatch multiple events in batch
     */
    public function dispatchBatch(array $events): void
    {
        foreach ($events as $eventData) {
            $eventClass = $eventData['event'];
            $args = $eventData['args'] ?? [];
            
            try {
                $this->dispatch($eventClass, ...$args);
            } catch (\Exception $e) {
                Log::error("Failed to dispatch event in batch", [
                    'event_class' => $eventClass,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other events even if one fails
            }
        }
    }

    /**
     * Get event statistics
     */
    public function getEventStatistics(string $tenantId, int $days = 30): array
    {
        // This would typically query an event log table
        // For now, return basic statistics
        return [
            'total_events' => 0,
            'events_by_type' => [],
            'events_by_day' => [],
            'failed_events' => 0,
        ];
    }

    /**
     * Extract event data for logging
     */
    private function extractEventData($event): array
    {
        $data = [];
        
        // Use reflection to get event properties
        $reflection = new \ReflectionClass($event);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        
        foreach ($properties as $property) {
            $name = $property->getName();
            $value = $property->getValue($event);
            
            // Convert objects to arrays for logging
            if (is_object($value)) {
                if (method_exists($value, 'toArray')) {
                    $data[$name] = $value->toArray();
                } elseif (method_exists($value, 'getKey')) {
                    $data[$name] = ['id' => $value->getKey()];
                } else {
                    $data[$name] = get_class($value);
                }
            } else {
                $data[$name] = $value;
            }
        }
        
        return $data;
    }

    /**
     * Register event listeners programmatically
     */
    public function registerListeners(): void
    {
        // Task events
        Event::listen(\App\Events\TaskCreated::class, \App\Listeners\SendTaskNotification::class);
        Event::listen(\App\Events\TaskCompleted::class, \App\Listeners\UpdateProjectProgress::class);
        Event::listen(\App\Events\TaskUpdated::class, \App\Listeners\LogTaskAudit::class);
        
        // Component events
        Event::listen(\App\Events\ComponentCreated::class, function($event) {
            // Log component creation
            Log::info("Component created", [
                'component_id' => $event->component->id,
                'component_name' => $event->component->name,
                'user_id' => $event->user->id,
            ]);
        });
        
        // Change request events
        Event::listen(\App\Events\ChangeRequested::class, function($event) {
            // Send notifications for change requests
            Log::info("Change request created", [
                'change_request_id' => $event->changeRequest->id,
                'title' => $event->changeRequest->title,
                'priority' => $event->changeRequest->priority,
            ]);
        });
        
        // Document events
        Event::listen(\App\Events\DocumentUploaded::class, function($event) {
            // Log document upload
            Log::info("Document uploaded", [
                'document_id' => $event->document->id,
                'document_name' => $event->document->name,
                'file_size' => $event->document->file_size,
            ]);
        });
    }

    /**
     * Test event system
     */
    public function testEventSystem(): array
    {
        $results = [];
        
        try {
            // Test basic event dispatch
            $this->dispatch(\App\Events\TaskCreated::class, 
                new \App\Models\Task(), 
                new \App\Models\User(), 
                ['test' => true]
            );
            $results['basic_dispatch'] = 'success';
        } catch (\Exception $e) {
            $results['basic_dispatch'] = 'failed: ' . $e->getMessage();
        }
        
        try {
            // Test async event dispatch
            $this->dispatchAsync(\App\Events\TaskCompleted::class, 
                new \App\Models\Task(), 
                new \App\Models\User(), 
                ['test' => true]
            );
            $results['async_dispatch'] = 'success';
        } catch (\Exception $e) {
            $results['async_dispatch'] = 'failed: ' . $e->getMessage();
        }
        
        return $results;
    }
}
