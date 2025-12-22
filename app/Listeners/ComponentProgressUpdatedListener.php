<?php declare(strict_types=1);

namespace App\Listeners;

use Src\CoreProject\Events\ComponentProgressUpdated;
use Src\CoreProject\Listeners\ProjectCalculationListener;
use Src\Foundation\Listeners\EventLogListener;
use Src\CoreProject\Listeners\NotificationListener;

/**
 * Laravel Event Listeners cho ComponentProgressUpdated
 * Wrapper cho existing listeners để tương thích với Laravel Event system
 */
class ComponentProgressUpdatedListener
{
    public function handle(ComponentProgressUpdated $event): void
    {
        // Log that listener is called
        \Log::info('ComponentProgressUpdatedListener called', [
            'componentId' => $event->componentId,
            'projectId' => $event->projectId,
            'newProgress' => $event->newProgress
        ]);
        
        // Call ProjectCalculationListener
        $projectListener = app(ProjectCalculationListener::class);
        $projectListener->handle($event);
        
        // Call EventLogListener
        $eventLogListener = app(EventLogListener::class);
        $eventLogListener->handle($event->getEventName(), [$event]);
        
        // Call NotificationListener (if it has a method for ComponentProgressUpdated)
        $notificationListener = app(NotificationListener::class);
        // Note: NotificationListener might need to be updated to handle ComponentProgressUpdated
    }
}
