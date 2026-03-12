<?php declare(strict_types=1);

namespace App\Services;

use App\Models\WorkTemplate;
use App\Models\WorkTemplateField;
use App\Models\WorkTemplateStep;
use App\Models\WorkTemplateVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class WorkTemplatePackageService
{
    public const SCHEMA_VERSION = '1.0.0';
    public const SCHEMA_VERSION_V2 = '2.0.0';
    public const SUPPORTED_SCHEMA_VERSIONS = [
        self::SCHEMA_VERSION,
        self::SCHEMA_VERSION_V2,
    ];

    public function assertSupportedSchemaVersion(string $schemaVersion): void
    {
        if (!in_array($schemaVersion, self::SUPPORTED_SCHEMA_VERSIONS, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unsupported schema_version "%s". Expected one of: "%s", "%s".',
                    $schemaVersion,
                    self::SCHEMA_VERSION,
                    self::SCHEMA_VERSION_V2
                )
            );
        }
    }

    /**
     * @param Collection<int, WorkTemplateVersion> $versions
     */
    public function buildExportPayload(WorkTemplate $template, Collection $versions, string $schemaVersion = self::SCHEMA_VERSION): array
    {
        $this->assertSupportedSchemaVersion($schemaVersion);

        $manifest = [
            'format' => 'json',
            'exported_at' => now()->toIso8601String(),
            'work_template_id' => (string) $template->id,
            'code' => $template->code,
            'name' => $template->name,
        ];

        if ($schemaVersion === self::SCHEMA_VERSION_V2) {
            $manifest['capabilities'] = [
                'generator.project-scope',
                'generator.component-scope',
                'generator.idempotent-apply',
                'generator.checklist-items',
            ];
        }

        return [
            'manifest' => $manifest,
            'schema_version' => $schemaVersion,
            'template' => [
                'code' => $template->code,
                'name' => $template->name,
                'description' => $template->description,
                'status' => $template->status,
                'versions' => $versions->map(
                    fn (WorkTemplateVersion $version): array => $this->exportVersion($version)
                )
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function importTemplate(string $tenantId, string $userId, array $payload, string $schemaVersion = self::SCHEMA_VERSION): WorkTemplate
    {
        $this->assertSupportedSchemaVersion($schemaVersion);

        $code = $this->resolveUniqueCode(
            $tenantId,
            (string) ($payload['code'] ?? 'WT-IMPORTED')
        );

        $template = WorkTemplate::create([
            'tenant_id' => $tenantId,
            'code' => $code,
            'name' => (string) ($payload['name'] ?? 'Imported Work Template'),
            'description' => $payload['description'] ?? null,
            'status' => (string) ($payload['status'] ?? 'draft'),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        foreach (($payload['versions'] ?? []) as $versionData) {
            $publishedAt = isset($versionData['published_at']) && is_string($versionData['published_at'])
                ? CarbonImmutable::parse($versionData['published_at'])
                : null;

            $canonicalSteps = $this->resolveCanonicalSteps(
                $versionData,
                $schemaVersion === self::SCHEMA_VERSION_V2
            );
            $content = $this->buildVersionContent($versionData, $canonicalSteps);
            $version = WorkTemplateVersion::create([
                'tenant_id' => $tenantId,
                'work_template_id' => $template->id,
                'semver' => (string) ($versionData['semver'] ?? ('imported-' . now()->format('YmdHisv'))),
                'content_json' => $content,
                'is_immutable' => (bool) ($versionData['is_immutable'] ?? $publishedAt !== null),
                'published_at' => $publishedAt,
                'published_by' => $publishedAt ? $userId : null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->createStepsAndFields($version, $canonicalSteps);
        }

        return $template->fresh(['versions.steps.fields']);
    }

    private function exportVersion(WorkTemplateVersion $version): array
    {
        $steps = $version->steps
            ->sortBy('step_order')
            ->values()
            ->map(fn (WorkTemplateStep $step): array => $this->exportStep($step))
            ->all();

        $content = $version->content_json ?? ['steps' => [], 'approvals' => [], 'rules' => []];
        $content['steps'] = $steps;
        $content['approvals'] = is_array($content['approvals'] ?? null) ? $content['approvals'] : [];
        $content['rules'] = is_array($content['rules'] ?? null) ? $content['rules'] : [];

        return [
            'semver' => $version->semver,
            'is_immutable' => (bool) $version->is_immutable,
            'published_at' => $version->published_at?->toIso8601String(),
            'content_json' => $content,
            'steps' => $steps,
        ];
    }

    private function exportStep(WorkTemplateStep $step): array
    {
        return [
            'key' => $step->step_key,
            'name' => $step->name,
            'type' => $step->type,
            'order' => (int) $step->step_order,
            'depends_on' => $step->depends_on ?? [],
            'assignee_rule' => $step->assignee_rule_json,
            'sla_hours' => $step->sla_hours,
            'config' => $step->config_json,
            'fields' => $step->fields
                ->values()
                ->map(fn (WorkTemplateField $field): array => [
                    'key' => $field->field_key,
                    'label' => $field->label,
                    'type' => $field->type,
                    'required' => (bool) $field->is_required,
                    'default' => $field->default_value,
                    'validation' => $field->validation_json,
                    'enum_options' => $field->enum_options_json,
                    'visibility_rule' => $field->visibility_rule_json,
                ])->all(),
        ];
    }

    private function buildVersionContent(array $versionData, array $canonicalSteps): array
    {
        $content = $versionData['content_json'] ?? [];
        if (!is_array($content)) {
            $content = [];
        }

        $content['steps'] = $canonicalSteps;

        $content['steps'] = is_array($content['steps'] ?? null) ? $content['steps'] : [];
        $content['approvals'] = is_array($content['approvals'] ?? null) ? $content['approvals'] : [];
        $content['rules'] = is_array($content['rules'] ?? null) ? $content['rules'] : [];

        return $content;
    }

    private function resolveCanonicalSteps(array $versionData, bool $required): array
    {
        $steps = $versionData['steps'] ?? null;
        if (is_array($steps)) {
            return array_values($steps);
        }

        $content = $versionData['content_json'] ?? null;
        if (is_array($content) && is_array($content['steps'] ?? null)) {
            return array_values($content['steps']);
        }

        if ($required) {
            throw new InvalidArgumentException(
                'Template version payload requires steps data for schema_version "2.0.0".'
            );
        }

        return [];
    }

    private function createStepsAndFields(WorkTemplateVersion $version, array $steps): void
    {
        foreach ($steps as $stepData) {
            $step = WorkTemplateStep::create([
                'tenant_id' => $version->tenant_id,
                'work_template_version_id' => $version->id,
                'step_key' => (string) ($stepData['key'] ?? ''),
                'name' => $stepData['name'] ?? null,
                'type' => (string) ($stepData['type'] ?? 'task'),
                'step_order' => (int) ($stepData['order'] ?? 1),
                'depends_on' => is_array($stepData['depends_on'] ?? null) ? $stepData['depends_on'] : [],
                'assignee_rule_json' => is_array($stepData['assignee_rule'] ?? null) ? $stepData['assignee_rule'] : null,
                'sla_hours' => isset($stepData['sla_hours']) ? (int) $stepData['sla_hours'] : null,
                'config_json' => is_array($stepData['config'] ?? null) ? $stepData['config'] : null,
            ]);

            foreach (($stepData['fields'] ?? []) as $fieldData) {
                WorkTemplateField::create([
                    'tenant_id' => $version->tenant_id,
                    'work_template_step_id' => $step->id,
                    'field_key' => (string) ($fieldData['key'] ?? ''),
                    'label' => (string) ($fieldData['label'] ?? ($fieldData['key'] ?? 'Field')),
                    'type' => (string) ($fieldData['type'] ?? 'string'),
                    'is_required' => (bool) ($fieldData['required'] ?? false),
                    'default_value' => isset($fieldData['default'])
                        ? (is_scalar($fieldData['default']) ? (string) $fieldData['default'] : json_encode($fieldData['default']))
                        : null,
                    'validation_json' => is_array($fieldData['validation'] ?? null) ? $fieldData['validation'] : null,
                    'enum_options_json' => is_array($fieldData['enum_options'] ?? null) ? $fieldData['enum_options'] : null,
                    'visibility_rule_json' => is_array($fieldData['visibility_rule'] ?? null) ? $fieldData['visibility_rule'] : null,
                ]);
            }
        }
    }

    private function resolveUniqueCode(string $tenantId, string $baseCode): string
    {
        $normalized = trim($baseCode);
        if ($normalized === '') {
            $normalized = 'WT-IMPORTED';
        }

        $candidate = substr($normalized, 0, 100);
        $suffix = 1;

        while (WorkTemplate::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $candidate)
            ->exists()) {
            $candidate = substr($normalized, 0, max(1, 95)) . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
