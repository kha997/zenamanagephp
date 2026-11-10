<?php

declare(strict_types=1);

namespace App\Services\WorkTemplates;

use App\Models\WorkTemplate;
use App\Models\WorkTemplateField;
use App\Models\WorkTemplateStep;
use App\Models\WorkTemplateVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * GAP-123 (Gate 2 E-1) — turns the web editor's step rows into the `steps[]` payload WorkTemplateCrudService already
 * accepts (the same shape as the JSON API), and back. Only the subset that drives "apply" is editable: name, type, after
 * which steps, days, description, priority, phase, checklist, required documents. Everything else on an existing step
 * (custom fields, assignee rule, assignment rules, required document types, other config keys) is carried over untouched.
 * New steps are assigned to the project manager, like every template today.
 */
class WorkTemplateEditorService
{
    public const TYPES = [
        'task' => 'Việc',
        'approval' => 'Duyệt',
        'deliverable' => 'Bàn giao',
        'inspection' => 'Nghiệm thu',
    ];

    public const PRIORITIES = [
        'low' => 'Thấp',
        'medium' => 'Trung bình',
        'high' => 'Cao',
    ];

    public const MAX_DAYS = 365;

    public const MAX_STEPS = 100;

    /**
     * The version the editor shows: the open draft, else the latest published version, else the latest one.
     */
    public function editableVersion(WorkTemplate $template): ?WorkTemplateVersion
    {
        $draft = null;
        $published = null;
        $latest = null;
        // The relation orders by created_at desc; tenant isolation comes from the parent and the global tenant scope.
        $versions = $template->versions()->get();
        foreach ($versions as $version) {
            if (!$version instanceof WorkTemplateVersion) {
                continue;
            }
            $latest ??= $version;
            if ($version->getAttribute('published_at') === null) {
                $draft ??= $version;
            } else {
                $published ??= $version;
            }
        }

        return $draft ?? $published ?? $latest;
    }

    public function isEditable(?WorkTemplateVersion $version): bool
    {
        return $version === null || $this->int($version, 'schema_version', 1) !== 2;
    }

    /**
     * Steps of a version in the API `steps[]` shape (used for duplicate and as the base for carried-over data).
     *
     * @return list<array<string, mixed>>
     */
    public function apiSteps(?WorkTemplateVersion $version): array
    {
        $out = [];
        foreach ($this->steps($version) as [$step, $fields]) {
            $out[] = array_filter([
                'key' => $this->str($step, 'step_key'),
                'name' => $this->str($step, 'name'),
                'type' => $this->str($step, 'type'),
                'order' => $this->int($step, 'step_order', 1),
                'depends_on' => $this->stringList($step->getAttribute('depends_on')),
                'assignee_rule' => $this->arrOrNull($step, 'assignee_rule_json'),
                'sla_hours' => $step->getAttribute('sla_hours') === null ? null : $this->int($step, 'sla_hours', 0),
                'config' => $this->arrOrNull($step, 'config_json'),
                'required_document_types' => $this->arrOrNull($step, 'required_document_types'),
                'fields' => array_map(fn (WorkTemplateField $field): array => array_filter([
                    'key' => $this->str($field, 'field_key'),
                    'label' => $this->str($field, 'label'),
                    'type' => $this->str($field, 'type'),
                    'required' => (bool) $field->getAttribute('is_required'),
                    'default' => $field->getAttribute('default_value'),
                    'validation' => $this->arrOrNull($field, 'validation_json'),
                    'enum_options' => $this->arrOrNull($field, 'enum_options_json'),
                    'visibility_rule' => $this->arrOrNull($field, 'visibility_rule_json'),
                ], fn ($value): bool => $value !== null), $fields),
            ], fn ($value): bool => $value !== null);
        }

        return $out;
    }

    /**
     * Form rows for the editor.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(?WorkTemplateVersion $version): array
    {
        $out = [];
        foreach ($this->steps($version) as [$step, $fields]) {
            $config = $this->arrOrNull($step, 'config_json') ?? [];
            $out[] = [
                'key' => $this->str($step, 'step_key'),
                'order' => $this->int($step, 'step_order', 1),
                'name' => $this->str($step, 'name'),
                'type' => $this->str($step, 'type'),
                'days' => (int) ceil($this->int($step, 'sla_hours', 0) / 24),
                'after' => $this->stringList($step->getAttribute('depends_on')),
                'description' => is_string($config['description'] ?? null) ? $config['description'] : '',
                'priority' => is_string($config['priority'] ?? null) ? $config['priority'] : 'medium',
                'phase' => is_string($config['phase_name'] ?? null) ? $config['phase_name'] : '',
                'checklist' => $this->linesFromItems($config['checklist_items'] ?? []),
                'docs' => $this->linesFromItems($config['required_docs'] ?? []),
                'field_count' => count($fields),
            ];
        }

        return $out;
    }

    /**
     * Validates the posted rows and builds the `steps[]` payload. Blank rows (no name and nothing else filled in) are
     * skipped. Data of existing steps that the editor does not show is carried over from $source.
     *
     * @param array<int|string, mixed> $rows
     *
     * @return list<array<string, mixed>>
     *
     * @throws ValidationException
     */
    public function stepsFromRows(array $rows, ?WorkTemplateVersion $source): array
    {
        $existing = collect($this->apiSteps($source))->keyBy('key');
        $errors = [];
        $kept = [];

        foreach (array_values($rows) as $i => $row) {
            if (!is_array($row) || $this->isBlank($row)) {
                continue;
            }
            $label = 'Bước ' . ($i + 1);
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $errors["steps.{$i}.name"] = "{$label}: nhập tên bước.";
                continue;
            }
            $type = (string) ($row['type'] ?? 'task');
            if (!array_key_exists($type, self::TYPES)) {
                $errors["steps.{$i}.type"] = "{$label}: loại bước không hợp lệ.";
            }
            $priority = (string) ($row['priority'] ?? 'medium');
            if (!array_key_exists($priority, self::PRIORITIES)) {
                $errors["steps.{$i}.priority"] = "{$label}: mức ưu tiên không hợp lệ.";
            }
            $days = $row['days'] ?? '';
            if (!is_numeric($days) || (float) $days < 0 || (float) $days > self::MAX_DAYS || floor((float) $days) != (float) $days) {
                $errors["steps.{$i}.days"] = "{$label}: số ngày phải là số nguyên từ 0 đến " . self::MAX_DAYS . '.';
            }
            $kept[] = ['index' => $i, 'row' => $row, 'name' => $name, 'type' => $type, 'priority' => $priority, 'days' => (int) $days];
        }

        // Order by the "Thứ tự" column, then by position on the form.
        usort($kept, fn (array $a, array $b): int => [(int) ($a['row']['order'] ?? 0), $a['index']] <=> [(int) ($b['row']['order'] ?? 0), $b['index']]);

        $names = [];
        $keys = [];
        foreach ($kept as $n => $item) {
            $lower = mb_strtolower($item['name']);
            if (isset($names[$lower])) {
                $errors["steps.{$item['index']}.name"] = "Bước \"{$item['name']}\" bị trùng tên.";
            }
            $names[$lower] = true;

            $key = trim((string) ($item['row']['key'] ?? ''));
            if ($key === '' || !$existing->has($key) || isset($keys[$key])) {
                $key = $this->uniqueKey(Str::slug($item['name']) ?: 'buoc', $keys + $existing->keys()->flip()->all());
            }
            $keys[$key] = true;
            $kept[$n]['key'] = $key;
        }

        // "Làm sau bước" may only point at steps placed above it (no forward references, so no cycles).
        $above = [];
        foreach ($kept as $n => $item) {
            $after = array_values(array_unique(array_filter(array_map('strval', (array) ($item['row']['after'] ?? [])))));
            foreach ($after as $dependency) {
                if (!isset($above[$dependency])) {
                    $errors["steps.{$item['index']}.after"] = "Bước \"{$item['name']}\" chỉ được làm sau các bước nằm phía trên nó.";
                }
            }
            $kept[$n]['after'] = $after;
            $above[$item['key']] = true;
        }

        if (count($kept) > self::MAX_STEPS) {
            $errors['steps'] = 'Một mẫu có tối đa ' . self::MAX_STEPS . ' bước.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $phaseOrder = [];
        $steps = [];
        foreach ($kept as $position => $item) {
            $base = $existing->get($item['key'], []);
            $config = is_array($base['config'] ?? null) ? $base['config'] : ['assignment_rules' => ['reviewers' => [], 'watchers' => []]];
            $phase = trim((string) ($item['row']['phase'] ?? ''));
            if ($phase !== '' && !isset($phaseOrder[$phase])) {
                $phaseOrder[$phase] = count($phaseOrder) + 1;
            }
            $config = array_merge($config, [
                'description' => trim((string) ($item['row']['description'] ?? '')) ?: null,
                'priority' => $item['priority'],
                'phase_key' => $phase !== '' ? (Str::slug($phase) ?: 'giai-doan-' . $phaseOrder[$phase]) : null,
                'phase_name' => $phase !== '' ? $phase : null,
                'phase_order' => $phase !== '' ? $phaseOrder[$phase] : null,
                'checklist_items' => $this->itemsFromLines((string) ($item['row']['checklist'] ?? ''), $config['checklist_items'] ?? []),
                'required_docs' => $this->itemsFromLines((string) ($item['row']['docs'] ?? ''), $config['required_docs'] ?? []),
            ]);
            $config = array_filter($config, fn ($value): bool => $value !== null);

            $steps[] = array_merge($base, [
                'key' => $item['key'],
                'name' => $item['name'],
                'type' => $item['type'],
                'order' => $position + 1,
                'depends_on' => $item['after'],
                'assignee_rule' => $base['assignee_rule'] ?? ['role' => 'project_manager'],
                // Days are shown rounded up; an unchanged day value keeps the exact stored hours (e.g. 12 h steps).
                'sla_hours' => isset($base['sla_hours']) && is_numeric($base['sla_hours']) && (int) ceil(((int) $base['sla_hours']) / 24) === $item['days']
                    ? (int) $base['sla_hours']
                    : $item['days'] * 24,
                'config' => $config,
                'fields' => $base['fields'] ?? [],
            ]);
        }

        return $steps;
    }

    /**
     * What applying the steps would create, with day offsets from the apply date (day 1), mirroring the apply timeline:
     * a step starts when all the steps it follows are due, and is due after its days.
     *
     * @param list<array<string, mixed>> $steps
     *
     * @return list<array{name: string, type: string, phase: string, after: list<string>, from: int, to: int, checklist: int, docs: int}>
     */
    public function preview(array $steps): array
    {
        $names = [];
        $endHours = [];
        $out = [];
        foreach ($steps as $step) {
            $key = (string) $step['key'];
            $names[$key] = (string) $step['name'];
            $start = 0;
            foreach ((array) ($step['depends_on'] ?? []) as $dependency) {
                $start = max($start, $endHours[(string) $dependency] ?? 0);
            }
            $end = $start + (int) ($step['sla_hours'] ?? 0);
            $endHours[$key] = $end;
            $config = is_array($step['config'] ?? null) ? $step['config'] : [];
            $from = intdiv($start, 24) + 1;
            $out[] = [
                'name' => (string) $step['name'],
                'type' => (string) $step['type'],
                'phase' => is_string($config['phase_name'] ?? null) ? $config['phase_name'] : '',
                'after' => array_values(array_map(fn ($k): string => $names[(string) $k] ?? (string) $k, (array) ($step['depends_on'] ?? []))),
                'from' => $from,
                'to' => max($from, (int) ceil($end / 24)),
                'checklist' => count((array) ($config['checklist_items'] ?? [])),
                'docs' => count((array) ($config['required_docs'] ?? [])),
            ];
        }

        return $out;
    }

    /**
     * Code for a copy: "<code>-COPY", then "-COPY-2", … unique in the tenant.
     */
    public function copyCode(WorkTemplate $template): string
    {
        $base = Str::limit($this->str($template, 'code'), 90, '') . '-COPY';
        $code = $base;
        for ($n = 2; WorkTemplate::query()->where('tenant_id', $this->str($template, 'tenant_id'))->where('code', $code)->exists(); $n++) {
            $code = $base . '-' . $n;
        }

        return $code;
    }

    /**
     * Steps of a version in order, each with its custom fields.
     *
     * @return list<array{0: WorkTemplateStep, 1: list<WorkTemplateField>}>
     */
    private function steps(?WorkTemplateVersion $version): array
    {
        if ($version === null) {
            return [];
        }
        $steps = [];
        foreach ($version->steps()->get() as $step) { // ordered by step_order
            if (!$step instanceof WorkTemplateStep) {
                continue;
            }
            $fields = [];
            foreach ($step->fields()->get() as $field) {
                if ($field instanceof WorkTemplateField) {
                    $fields[] = $field;
                }
            }
            $steps[] = [$step, $fields];
        }

        return $steps;
    }

    private function str(Model $model, string $attribute): string
    {
        $value = $model->getAttribute($attribute);

        return is_scalar($value) ? (string) $value : '';
    }

    private function int(Model $model, string $attribute, int $default): int
    {
        $value = $model->getAttribute($attribute);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function arrOrNull(Model $model, string $attribute): ?array
    {
        $value = $model->getAttribute($attribute);

        return is_array($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_map('strval', array_filter($value, 'is_scalar'))) : [];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isBlank(array $row): bool
    {
        foreach (['name', 'description', 'phase', 'checklist', 'docs'] as $field) {
            if (trim((string) ($row[$field] ?? '')) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, bool> $taken
     */
    private function uniqueKey(string $base, array $taken): string
    {
        $key = Str::limit($base, 90, '');
        for ($n = 2; isset($taken[$key]); $n++) {
            $key = Str::limit($base, 90, '') . '-' . $n;
        }

        return $key;
    }

    /**
     * One item per line; a leading "*" marks it required. An item whose label is unchanged keeps its existing key.
     *
     * @return list<array{key: string, label: string, required: bool}>
     */
    private function itemsFromLines(string $text, mixed $previous = []): array
    {
        $previousKeys = [];
        foreach (is_array($previous) ? $previous : [] as $item) {
            if (is_array($item) && isset($item['label'], $item['key'])) {
                $previousKeys[(string) $item['label']] ??= (string) $item['key'];
            }
        }
        $items = [];
        $taken = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line === '*') {
                continue;
            }
            $required = str_starts_with($line, '*');
            $label = trim($required ? substr($line, 1) : $line);
            $key = isset($previousKeys[$label]) && !isset($taken[$previousKeys[$label]])
                ? $previousKeys[$label]
                : $this->uniqueKey(Str::slug($label) ?: 'muc', $taken);
            $taken[$key] = true;
            $items[] = ['key' => $key, 'label' => Str::limit($label, 255, ''), 'required' => $required];
        }

        return $items;
    }

    private function linesFromItems(mixed $items): string
    {
        $lines = [];
        foreach (is_array($items) ? $items : [] as $item) {
            if (is_array($item) && isset($item['label'])) {
                $lines[] = (($item['required'] ?? false) ? '* ' : '') . $item['label'];
            }
        }

        return implode("\n", $lines);
    }
}
