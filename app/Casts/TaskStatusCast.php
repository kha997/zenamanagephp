<?php

namespace App\Casts;

use App\Enums\TaskStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class TaskStatusCast implements CastsAttributes
{
    /**
     * Map legacy status values to standardized enum values
     */
    private const STATUS_MAP = [
        'pending' => 'backlog',
        'completed' => 'done',
        'cancelled' => 'canceled',
        'on_hold' => 'blocked',
    ];

    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?TaskStatus
    {
        if ($value === null) {
            return null;
        }

        // If already a TaskStatus enum, return it
        if ($value instanceof TaskStatus) {
            return $value;
        }

        // Normalize legacy status values
        $normalized = self::STATUS_MAP[$value] ?? $value;

        // Try to get enum from normalized value
        $enum = TaskStatus::tryFrom($normalized);

        // If still invalid, default to BACKLOG
        if (!$enum) {
            \Log::warning('Invalid task status encountered', [
                'task_id' => $model->id ?? null,
                'invalid_status' => $value,
                'normalized_status' => $normalized,
                'fallback' => 'backlog'
            ]);
            return TaskStatus::BACKLOG;
        }

        return $enum;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof TaskStatus) {
            return $value->value;
        }

        // Normalize legacy status values
        $normalized = self::STATUS_MAP[$value] ?? $value;

        // Validate normalized value
        if (!TaskStatus::isValid($normalized)) {
            \Log::warning('Invalid task status being set', [
                'task_id' => $model->id ?? null,
                'invalid_status' => $value,
                'normalized_status' => $normalized,
                'fallback' => 'backlog'
            ]);
            return TaskStatus::BACKLOG->value;
        }

        return $normalized;
    }
}

