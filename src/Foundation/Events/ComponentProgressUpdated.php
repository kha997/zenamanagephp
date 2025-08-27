<?php declare(strict_types=1);

namespace Src\Foundation\Events;

/**
 * Sự kiện khi component được cập nhật progress
 */
class ComponentProgressUpdated extends BaseEvent {
    public function getEventName(): string {
        return 'Project.Component.ProgressUpdated';
    }
}