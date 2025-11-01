<?php

namespace App\Services;

class MetricsService
{
    public function getProjectMetrics(string $projectId): array
    {
        return [
            'project_id' => $projectId,
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'progress_percentage' => 0
        ];
    }
}