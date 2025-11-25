<?php declare(strict_types=1);

namespace App\Enums;

/**
 * Task Status Enum
 * 
 * Standardized task status values for the system.
 * This enum serves as the single source of truth for task statuses.
 */
enum TaskStatus: string
{
    case BACKLOG = 'backlog';
    case IN_PROGRESS = 'in_progress';
    case BLOCKED = 'blocked';
    case DONE = 'done';
    case CANCELED = 'canceled';

    /**
     * Get all status values as array
     * 
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Check if a string is a valid task status
     * 
     * @param string $status
     * @return bool
     */
    public static function isValid(string $status): bool
    {
        return in_array($status, self::values(), true);
    }

    /**
     * Get human-readable label for status
     * 
     * @return string
     */
    public function label(): string
    {
        return match($this) {
            self::BACKLOG => 'Backlog',
            self::IN_PROGRESS => 'In Progress',
            self::BLOCKED => 'Blocked',
            self::DONE => 'Done',
            self::CANCELED => 'Canceled',
        };
    }

    /**
     * Check if status is a terminal state (cannot be changed by project status)
     * 
     * @return bool
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::DONE, self::CANCELED], true);
    }
}

