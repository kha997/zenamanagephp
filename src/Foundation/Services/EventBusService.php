<?php declare(strict_types=1);

namespace Src\Foundation\Services;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

/**
 * Event Bus Service để quản lý events trong hệ thống
 * Cung cấp interface thống nhất cho việc dispatch events
 */
class EventBusService
{
    /**
     * Dispatch event với error handling
     * 
     * @param object $event Event object
     * @param bool $async Có dispatch async không
     * @return bool Success status
     */
    public function dispatch(object $event, bool $async = false): bool
    {
        try {
            if ($async) {
                // Dispatch async using queues
                Event::dispatch($event);
            } else {
                // Dispatch synchronously
                Event::dispatch($event);
            }
            
            Log::info('Event dispatched successfully', [
                'event_class' => get_class($event),
                'event_name' => method_exists($event, 'getEventName') ? $event->getEventName() : 'Unknown',
                'async' => $async
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to dispatch event', [
                'event_class' => get_class($event),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }
    
    /**
     * Dispatch multiple events trong batch
     * 
     * @param array $events Array of event objects
     * @param bool $async Có dispatch async không
     * @return array Results array
     */
    public function dispatchBatch(array $events, bool $async = false): array
    {
        $results = [];
        
        foreach ($events as $index => $event) {
            $results[$index] = $this->dispatch($event, $async);
        }
        
        return $results;
    }
    
    /**
     * Listen tới specific event với callback
     * 
     * @param string $eventClass Event class name
     * @param callable $callback Callback function
     */
    public function listen(string $eventClass, callable $callback): void
    {
        Event::listen($eventClass, $callback);
    }
    
    /**
     * Forget listener cho specific event
     * 
     * @param string $eventClass Event class name
     */
    public function forget(string $eventClass): void
    {
        Event::forget($eventClass);
    }
    
    /**
     * Lấy danh sách listeners cho event
     * 
     * @param string $eventClass Event class name
     * @return array Listeners array
     */
    public function getListeners(string $eventClass): array
    {
        return Event::getListeners($eventClass);
    }
}