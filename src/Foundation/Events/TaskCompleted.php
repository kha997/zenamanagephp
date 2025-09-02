<?php declare(strict_types=1);

namespace Src\Foundation\Events;

/**
 * Sự kiện khi task được hoàn thành
 */
class TaskCompleted extends BaseEvent {
    public function getEventName(): string {
        return 'Project.Task.Completed';
    }
}