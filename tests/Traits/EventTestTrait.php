<?php declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

/**
 * Trait EventTestTrait
 * 
 * Provides event testing utilities for Z.E.N.A event-driven architecture
 * Handles event assertions and queue testing
 * 
 * @package Tests\Traits
 */
trait EventTestTrait
{
    /**
     * Assert that an event was dispatched
     * 
     * @param string $eventClass
     * @param callable|null $callback
     * @return void
     */
    protected function assertEventDispatched(string $eventClass, callable $callback = null): void
    {
        Event::assertDispatched($eventClass, $callback);
    }

    /**
     * Assert that an event was not dispatched
     * 
     * @param string $eventClass
     * @return void
     */
    protected function assertEventNotDispatched(string $eventClass): void
    {
        Event::assertNotDispatched($eventClass);
    }

    /**
     * Assert that a listener is attached to an event
     * 
     * @param string $eventClass
     * @param string $listenerClass
     * @return void
     */
    protected function assertEventHasListener(string $eventClass, string $listenerClass): void
    {
        $listeners = Event::getListeners($eventClass);
        $hasListener = false;

        foreach ($listeners as $listener) {
            if (is_string($listener) && $listener === $listenerClass) {
                $hasListener = true;
                break;
            }
            if (is_array($listener) && isset($listener[0]) && $listener[0] === $listenerClass) {
                $hasListener = true;
                break;
            }
        }

        $this->assertTrue($hasListener, "Listener {$listenerClass} is not attached to event {$eventClass}");
    }

    /**
     * Fake events for testing
     * 
     * @param array $events
     * @return void
     */
    protected function fakeEvents(array $events = []): void
    {
        if (empty($events)) {
            Event::fake();
        } else {
            Event::fake($events);
        }
    }

    /**
     * Fake queues for testing
     * 
     * @return void
     */
    protected function fakeQueues(): void
    {
        Queue::fake();
    }

    /**
     * Assert job was pushed to queue
     * 
     * @param string $jobClass
     * @param callable|null $callback
     * @return void
     */
    protected function assertJobPushed(string $jobClass, callable $callback = null): void
    {
        Queue::assertPushed($jobClass, $callback);
    }

    /**
     * Dispatch event and assert listeners were called
     * 
     * @param object $event
     * @param array $expectedListeners
     * @return void
     */
    protected function dispatchEventAndAssertListeners(object $event, array $expectedListeners): void
    {
        Event::dispatch($event);

        foreach ($expectedListeners as $listener) {
            $this->assertEventHasListener(get_class($event), $listener);
        }
    }
}