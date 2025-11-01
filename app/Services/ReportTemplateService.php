<?php

namespace App\Services;

use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class ReportTemplateService
{
    /**
     * Create a new report template
     */
    public function createTemplate(User $user, array $data): ReportTemplate
    {
        try {
            $template = ReportTemplate::create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'format' => $data['format'],
                'layout' => $data['layout'] ?? null,
                'sections' => $data['sections'] ?? null,
                'filters' => $data['filters'] ?? null,
                'styling' => $data['styling'] ?? null,
                'is_public' => $data['is_public'] ?? false,
                'is_active' => true
            ]);

            Log::info('Report template created successfully', [
                'template_id' => $template->id,
                'user_id' => $user->id,
                'type' => $template->type
            ]);

            return $template;
        } catch (\Exception $e) {
            Log::error('Failed to create report template', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing report template
     */
    public function updateTemplate(ReportTemplate $template, array $data): ReportTemplate
    {
        try {
            $template->update([
                'name' => $data['name'] ?? $template->name,
                'description' => $data['description'] ?? $template->description,
                'type' => $data['type'] ?? $template->type,
                'format' => $data['format'] ?? $template->format,
                'layout' => $data['layout'] ?? $template->layout,
                'sections' => $data['sections'] ?? $template->sections,
                'filters' => $data['filters'] ?? $template->filters,
                'styling' => $data['styling'] ?? $template->styling,
                'is_public' => $data['is_public'] ?? $template->is_public,
                'is_active' => $data['is_active'] ?? $template->is_active
            ]);

            Log::info('Report template updated successfully', [
                'template_id' => $template->id,
                'user_id' => $template->user_id
            ]);

            return $template;
        } catch (\Exception $e) {
            Log::error('Failed to update report template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Get templates for a user
     */
    public function getUserTemplates(User $user, array $filters = []): Collection
    {
        $query = ReportTemplate::where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->active();

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['format'])) {
            $query->where('format', $filters['format']);
        }

        if (isset($filters['is_public'])) {
            $query->where('is_public', $filters['is_public']);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Get public templates
     */
    public function getPublicTemplates(array $filters = []): Collection
    {
        $query = ReportTemplate::public()->active();

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['format'])) {
            $query->where('format', $filters['format']);
        }

        return $query->orderBy('usage_count', 'desc')->get();
    }

    /**
     * Get template by ID with access control
     */
    public function getTemplate(User $user, int $templateId): ?ReportTemplate
    {
        $template = ReportTemplate::find($templateId);

        if (!$template) {
            return null;
        }

        // Check if user can access this template
        if ($template->user_id === $user->id || $template->is_public) {
            return $template;
        }

        return null;
    }

    /**
     * Duplicate a template
     */
    public function duplicateTemplate(ReportTemplate $template, User $user, string $newName = null): ReportTemplate
    {
        try {
            $newTemplate = ReportTemplate::create([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $newName ?: $template->name . ' (Copy)',
                'description' => $template->description,
                'type' => $template->type,
                'format' => $template->format,
                'layout' => $template->layout,
                'sections' => $template->sections,
                'filters' => $template->filters,
                'styling' => $template->styling,
                'is_public' => false, // Duplicated templates are private by default
                'is_active' => true
            ]);

            Log::info('Report template duplicated successfully', [
                'original_template_id' => $template->id,
                'new_template_id' => $newTemplate->id,
                'user_id' => $user->id
            ]);

            return $newTemplate;
        } catch (\Exception $e) {
            Log::error('Failed to duplicate report template', [
                'template_id' => $template->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete a template
     */
    public function deleteTemplate(ReportTemplate $template): bool
    {
        try {
            $template->delete();

            Log::info('Report template deleted successfully', [
                'template_id' => $template->id,
                'user_id' => $template->user_id
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to delete report template', [
                'template_id' => $template->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get template statistics
     */
    public function getTemplateStats(User $user): array
    {
        $userTemplates = ReportTemplate::where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->active();

        $publicTemplates = ReportTemplate::public()->active();

        return [
            'user_templates' => [
                'total' => $userTemplates->count(),
                'by_type' => $userTemplates->groupBy('type')->map->count(),
                'by_format' => $userTemplates->groupBy('format')->map->count(),
                'public_count' => $userTemplates->where('is_public', true)->count()
            ],
            'public_templates' => [
                'total' => $publicTemplates->count(),
                'by_type' => $publicTemplates->groupBy('type')->map->count(),
                'by_format' => $publicTemplates->groupBy('format')->map->count()
            ],
            'most_used' => $userTemplates->orderBy('usage_count', 'desc')->take(5)->get()
        ];
    }

    /**
     * Get available template types
     */
    public function getAvailableTypes(): array
    {
        return [
            'dashboard' => 'Dashboard Report',
            'projects' => 'Projects Report',
            'tasks' => 'Tasks Report',
            'team' => 'Team Report',
            'financial' => 'Financial Report',
            'custom' => 'Custom Report'
        ];
    }

    /**
     * Get available formats
     */
    public function getAvailableFormats(): array
    {
        return [
            'pdf' => 'PDF Document',
            'excel' => 'Excel Spreadsheet',
            'csv' => 'CSV File',
            'json' => 'JSON Data'
        ];
    }

    /**
     * Validate template data
     */
    public function validateTemplateData(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'Template name is required';
        }

        if (empty($data['type'])) {
            $errors[] = 'Template type is required';
        } elseif (!array_key_exists($data['type'], $this->getAvailableTypes())) {
            $errors[] = 'Invalid template type';
        }

        if (empty($data['format'])) {
            $errors[] = 'Template format is required';
        } elseif (!array_key_exists($data['format'], $this->getAvailableFormats())) {
            $errors[] = 'Invalid template format';
        }

        return $errors;
    }

    /**
     * Create default templates for a user
     */
    public function createDefaultTemplates(User $user): void
    {
        $defaultTemplates = [
            [
                'name' => 'Executive Dashboard',
                'description' => 'High-level overview of key metrics and KPIs',
                'type' => 'dashboard',
                'format' => 'pdf',
                'is_public' => false
            ],
            [
                'name' => 'Project Status Report',
                'description' => 'Detailed project progress and status updates',
                'type' => 'projects',
                'format' => 'excel',
                'is_public' => false
            ],
            [
                'name' => 'Team Performance Report',
                'description' => 'Team member productivity and performance metrics',
                'type' => 'team',
                'format' => 'pdf',
                'is_public' => false
            ],
            [
                'name' => 'Financial Summary',
                'description' => 'Budget utilization and financial overview',
                'type' => 'financial',
                'format' => 'excel',
                'is_public' => false
            ]
        ];

        foreach ($defaultTemplates as $templateData) {
            try {
                $this->createTemplate($user, $templateData);
            } catch (\Exception $e) {
                Log::warning('Failed to create default template', [
                    'user_id' => $user->id,
                    'template_name' => $templateData['name'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
