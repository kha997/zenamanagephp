<?php declare(strict_types=1);

namespace Src\Foundation\Events;

use Src\Foundation\EventBus as BaseEventBus;

/**
 * Proxy to the main EventBus implementation under the Src\Foundation namespace.
 * Some services and controllers expect the class to live under Src\Foundation\Events.
 */
class EventBus extends BaseEventBus
{
}
