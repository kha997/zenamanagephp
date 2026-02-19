<?php declare(strict_types=1);

namespace Src\Foundation\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched after an event is audited to the database.
 */
class EventLogged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly string $eventName,
        public readonly array $payload = [],
        public readonly ?string $projectId = null,
        public readonly ?string $actorId = null,
        public readonly ?string $sourceModule = null,
        public readonly ?string $severity = null
    ) {}
}
