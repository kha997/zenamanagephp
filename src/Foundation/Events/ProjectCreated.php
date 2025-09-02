<?php declare(strict_types=1);

namespace Src\Foundation\Events;

/**
 * Sự kiện khi project được tạo
 */
class ProjectCreated extends BaseEvent {
    public function getEventName(): string {
        return 'Project.Project.Created';
    }
}