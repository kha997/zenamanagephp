<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** OWN-2026-024 — SPLIT-PERCENT: a rejected assignment-share write (never a partial write: thrown inside the task-lock transaction). */
final class TaskAssignmentShareException extends RuntimeException
{
    public const REASON_TASK_NOT_FOUND = 'task_not_found';

    public const REASON_EXCEEDS_TOTAL = 'exceeds_total';

    public const REASON_DUPLICATE = 'duplicate';

    private function __construct(string $message, public readonly string $reason, public readonly ?float $currentTotal = null)
    {
        parent::__construct($message);
    }

    public static function taskNotFound(): self
    {
        return new self('Task not found', self::REASON_TASK_NOT_FOUND);
    }

    public static function exceedsTotal(float $currentTotal): self
    {
        return new self('Total split percentage cannot exceed 100%. Current total: ' . self::format($currentTotal) . '%', self::REASON_EXCEEDS_TOTAL, $currentTotal);
    }

    public static function duplicate(): self
    {
        return new self('User is already assigned to this task', self::REASON_DUPLICATE);
    }

    private static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
