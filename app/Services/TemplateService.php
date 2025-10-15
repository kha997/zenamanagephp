<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * TemplateService
 * 
 * Service class để xử lý business logic cho Template Management
 * Bao gồm CRUD operations, template application, versioning
 */
class TemplateService
{
    /**
     * Create a new template
     */
    public function createTemplate(array $data, string $userId, string $tenantId): Template
    {
        DB::beginTransaction();
        
        try {
            $template = Template::create([
                'id' => Str::ulid(),
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'],
                'template_data' => $data['template_data'] ?? [],
                'settings' => $data['settings'] ?? [],
                'status' => $data['status'] ?? Template::STATUS_DRAFT,
                'version' => 1,
                'is_public' => $data['is_public'] ?? false,
                'is_active' => true,
                'created_by' => $userId,
                'updated_by' => $userId,
                'tags' => $data['tags'] ?? [],
                'metadata' => $data['metadata'] ?? []
            ]);

            // Create initial version
            $this->createVersion($template, $userId, 'Initial version');

            DB::commit();

            Log::info('Template created', [
                'template_id' => $template->id,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'name' => $template->name
            ]);

            return $template;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create template', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing template
     */
    public function updateTemplate(Template $template, array $data, string $userId): Template
    {
        DB::beginTransaction();
        
        try {
            $oldData = $template->template_data;
            
            $template->update([
                'name' => $data['name'] ?? $template->name,
                'description' => $data['description'] ?? $template->description,
                'category' => $data['category'] ?? $template->category,
                'template_data' => $data['template_data'] ?? $template->template_data,
                'settings' => $data['settings'] ?? $template->settings,
                'status' => $data['status'] ?? $template->status,
                'is_public' => $data['is_public'] ?? $template->is_public,
                'updated_by' => $userId,
                'tags' => $data['tags'] ?? $template->tags,
                'metadata' => $data['metadata'] ?? $template->metadata
            ]);

            // Create new version if template data changed
            if ($data['template_data'] && $data['template_data'] !== $oldData) {
                $template->increment('version');
                $this->createVersion($template, $userId, 'Template updated');
            }

            DB::commit();

            Log::info('Template updated', [
                'template_id' => $template->id,
                'user_id' => $userId,
                'version' => $template->version
            ]);

            return $template->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw $e;
        }
    }

    /**
     * Apply template to a project
     */
    public function applyTemplateToProject(Template $template, Project $project, string $userId): array
    {
        DB::beginTransaction();
        
        try {
            $templateData = $template->template_data;
            $createdTasks = [];
            $createdMilestones = [];

            // Increment template usage
            $template->incrementUsage();

            // Create tasks from template
            if (isset($templateData['tasks'])) {
                foreach ($templateData['tasks'] as $taskData) {
                    $task = Task::create([
                        'id' => Str::ulid(),
                        'tenant_id' => $project->tenant_id,
                        'project_id' => $project->id,
                        'name' => $taskData['name'],
                        'description' => $taskData['description'] ?? null,
                        'status' => 'pending',
                        'priority' => $taskData['priority'] ?? 'medium',
                        'estimated_hours' => $taskData['estimated_hours'] ?? 0,
                        'actual_hours' => 0,
                        'progress_percent' => 0,
                        'start_date' => $taskData['start_date'] ?? null,
                        'due_date' => $taskData['due_date'] ?? null,
                        'assigned_to' => $taskData['assigned_to'] ?? null,
                        'created_by' => $userId,
                        'tags' => $taskData['tags'] ?? [],
                        'metadata' => array_merge($taskData['metadata'] ?? [], [
                            'template_id' => $template->id,
                            'template_version' => $template->version
                        ])
                    ]);
                    
                    $createdTasks[] = $task;
                }
            }

            // Create milestones from template
            if (isset($templateData['milestones'])) {
                foreach ($templateData['milestones'] as $milestoneData) {
                    // Create milestone logic here
                    $createdMilestones[] = $milestoneData;
                }
            }

            DB::commit();

            Log::info('Template applied to project', [
                'template_id' => $template->id,
                'project_id' => $project->id,
                'user_id' => $userId,
                'tasks_created' => count($createdTasks),
                'milestones_created' => count($createdMilestones)
            ]);

            return [
                'template' => $template,
                'project' => $project,
                'tasks' => $createdTasks,
                'milestones' => $createdMilestones
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to apply template to project', [
                'template_id' => $template->id,
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw $e;
        }
    }

    /**
     * Duplicate a template
     */
    public function duplicateTemplate(Template $template, string $newName, string $userId): Template
    {
        return $template->duplicate($newName, $userId);
    }

    /**
     * Create a new version of a template
     */
    public function createVersion(Template $template, string $userId, string $description = null): TemplateVersion
    {
        $version = TemplateVersion::create([
            'id' => Str::ulid(),
            'template_id' => $template->id,
            'version' => $template->version,
            'name' => $description ?: "Version {$template->version}",
            'description' => $description,
            'template_data' => $template->template_data,
            'changes' => [],
            'created_by' => $userId,
            'is_active' => true
        ]);

        // Deactivate other versions
        TemplateVersion::where('template_id', $template->id)
            ->where('id', '!=', $version->id)
            ->update(['is_active' => false]);

        return $version;
    }

    /**
     * Get template analytics
     */
    public function getTemplateAnalytics(string $tenantId): array
    {
        $templates = Template::byTenant($tenantId)->get();

        return [
            'total_templates' => $templates->count(),
            'published_templates' => $templates->where('status', Template::STATUS_PUBLISHED)->count(),
            'draft_templates' => $templates->where('status', Template::STATUS_DRAFT)->count(),
            'archived_templates' => $templates->where('status', Template::STATUS_ARCHIVED)->count(),
            'public_templates' => $templates->where('is_public', true)->count(),
            'total_usage' => $templates->sum('usage_count'),
            'most_used_template' => $templates->sortByDesc('usage_count')->first(),
            'categories' => $templates->groupBy('category')->map->count(),
            'recent_templates' => $templates->sortByDesc('created_at')->take(5),
            'popular_templates' => $templates->sortByDesc('usage_count')->take(5)
        ];
    }

    /**
     * Search templates
     */
    public function searchTemplates(string $tenantId, array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        $query = Template::byTenant($tenantId);

        if (isset($filters['category'])) {
            $query->byCategory($filters['category']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['is_public'])) {
            $query->where('is_public', $filters['is_public']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['tags'])) {
            $query->whereJsonContains('tags', $filters['tags']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}