<?php declare(strict_types=1);

namespace App\Support\Work;

use Illuminate\Support\Carbon;

/**
 * Một Task hoặc DesignItem đang mở — dùng chung bởi WorkloadPageController
 * (My Work, Workload) và TodayWorkspaceReadService.
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §3.1
 */
final class OpenWorkItem
{
    public function __construct(
        public readonly string $sourceType,
        public readonly string $sourceId,
        public readonly ?string $assignedTo,
        public readonly string $kindLabel,
        public readonly string $name,
        public readonly ?string $projectId,
        public readonly string $projectName,
        public readonly ?Carbon $endDate,
        public readonly bool $isOverdue,
        public readonly bool $isBlocked,
        public readonly ?string $blockerNote,
        public readonly ?string $blockedBy,
        public readonly ?string $priority,
        public readonly string $status,
        public readonly string $url,
    ) {
    }
}
