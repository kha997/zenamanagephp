<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Document;
use App\Models\Project;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;

/**
 * Read-only comparison of a Project's uploaded Documents against the
 * required_document_types declared on its applied WorkTemplate steps
 * (spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md, Phase 9).
 * Pure PHP set comparison — no AI/LLM call. Matching is project-wide
 * (any Document with a matching document_type counts, regardless of step).
 */
class DocumentChecklistService
{
    /**
     * @return list<array{step_id: string, step_name: string, required: list<string>, missing: list<string>}>
     */
    public function buildReport(Project $project): array
    {
        $tenantId = (string) $project->tenant_id;

        $presentDocumentTypes = Document::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', (string) $project->id)
            ->whereIn('document_type', Document::VALID_DOCUMENT_TYPES)
            ->pluck('document_type')
            ->map(fn ($type): string => (string) $type)
            ->unique()
            ->values()
            ->all();

        $instanceIds = WorkInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', (string) $project->id)
            ->where('scope_type', 'project')
            ->pluck('id');

        if ($instanceIds->isEmpty()) {
            return [];
        }

        $steps = WorkInstanceStep::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('work_instance_id', $instanceIds)
            ->with('templateStep')
            ->get();

        $report = [];

        foreach ($steps as $step) {
            $templateStep = $step->templateStep;
            $required = $templateStep?->required_document_types ?? [];

            if (empty($required)) {
                continue;
            }

            $missing = array_values(array_diff($required, $presentDocumentTypes));

            $report[] = [
                'step_id' => (string) $step->id,
                'step_name' => (string) ($step->name ?? $step->step_key),
                'required' => array_values($required),
                'missing' => $missing,
            ];
        }

        return $report;
    }
}
