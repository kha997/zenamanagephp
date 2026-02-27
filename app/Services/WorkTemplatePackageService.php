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

    public function assertSupportedSchemaVersion(string $schemaVersion): void
    {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException(
                sprintf('Unsupported schema_version "%s". Expected "%s".', $schemaVersion, self::SCHEMA_VERSION)
            );
        }
    }

    /**
     * @param Collection<int, WorkTemplateVersion> $versions
     */
    public function buildExportPayload(WorkTemplate $template, Collection $versions): array
    {
        return [
            'manifest' => [
                'format' => 'json',
                'exported_at' => now()->toIso8601String(),
                'work_template_id' => (string) $template->id,
                'code' => $template->code,
                'name' => $template->name,
            ],
            'schema_version' => self::SCHEMA_VERSION,
            'template' => [
                'code' => $template->code,
                'name' => $template->name,
                'description' => $template->description,
                'status' => $template->status,
                'versions' => $versions->map(fn (WorkTemplateVersion $version): array => $this->exportVersion($version))
                    ->values()
                    ->all(),
            ],
        ];
    }

    public function importTemplate(string $tenantId, string $userId, array $payload): WorkTemplate
    {
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

            $content = $this->buildVersionContent($versionData);
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

            $this->createStepsAndFields($version, $versionData['steps'] ?? $content['steps'] ?? []);
        }

        return $template->fresh(['versions.steps.fields']);
    }

    private function exportVersion(WorkTemplateVersion $version): array
    {
        return [
            'semver' => $version->semver,
            'is_immutable' => (bool) $version->is_immutable,
            'published_at' => $version->published_at?->toIso8601String(),
            'content_json' => $version->content_json ?? ['steps' => [], 'approvals' => [], 'rules' => []],
            'steps' => $version->steps
                ->sortBy('step_order')
                ->values()
                ->map(fn (WorkTemplateStep $step): array => $this->exportStep($step))
                ->all(),
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

    private function buildVersionContent(array $versionData): array
    {
        $content = $versionData['content_json'] ?? [];
        if (!is_array($content)) {
            $content = [];
        }

        if (isset($versionData['steps']) && is_array($versionData['steps'])) {
            $content['steps'] = $versionData['steps'];
        }

        $content['steps'] = is_array($content['steps'] ?? null) ? $content['steps'] : [];
        $content['approvals'] = is_array($content['approvals'] ?? null) ? $content['approvals'] : [];
        $content['rules'] = is_array($content['rules'] ?? null) ? $content['rules'] : [];

        return $content;
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
