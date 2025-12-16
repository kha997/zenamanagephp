<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Cache Invalidation Service
 * 
 * Centralized service for cache invalidation based on domain events.
 * Ensures cache consistency across the application.
 * 
 * Usage:
 * - Domain events trigger invalidation automatically
 * - Manual invalidation via invalidateOnEvent()
 * - Supports key-based, tag-based, and pattern-based invalidation
 */
class CacheInvalidationService
{
    private AdvancedCacheService $cacheService;
    private CacheKeyService $keyService;

    public function __construct(
        AdvancedCacheService $cacheService,
        CacheKeyService $keyService
    ) {
        $this->cacheService = $cacheService;
        $this->keyService = $keyService;
    }

    /**
     * Invalidate cache based on domain event
     * 
     * @param string $event Event name (e.g., 'TaskMoved', 'ProjectUpdated')
     * @param array $payload Event payload with entity data
     */
    public function invalidateOnEvent(string $event, array $payload): void
    {
        try {
            $invalidationMap = $this->getInvalidationMap();
            
            if (!isset($invalidationMap[$event])) {
                Log::debug('No cache invalidation mapping for event', [
                    'event' => $event,
                ]);
                return;
            }

            $rules = $invalidationMap[$event];
            
            foreach ($rules as $rule) {
                $this->applyInvalidationRule($rule, $payload);
            }

            Log::info('Cache invalidated for domain event', [
                'event' => $event,
                'tenant_id' => $payload['tenant_id'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Cache invalidation failed', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get invalidation map: event → cache keys to invalidate
     * 
     * @return array
     */
    private function getInvalidationMap(): array
    {
        return [
            'TaskMoved' => [
                ['type' => 'key', 'pattern' => 'task:{task_id}'],
                ['type' => 'key', 'pattern' => 'task:{task_id}:detail'],
                ['type' => 'pattern', 'pattern' => 'tasks:project:{project_id}:*'],
                ['type' => 'key', 'pattern' => 'project:{project_id}:kpis'],
                ['type' => 'tag', 'tags' => ['task', 'tasks', 'kpi']],
            ],
            'TaskUpdated' => [
                ['type' => 'key', 'pattern' => 'task:{task_id}'],
                ['type' => 'key', 'pattern' => 'task:{task_id}:detail'],
                ['type' => 'pattern', 'pattern' => 'tasks:project:{project_id}:*'],
                ['type' => 'tag', 'tags' => ['task']],
            ],
            'ProjectUpdated' => [
                ['type' => 'key', 'pattern' => 'project:{project_id}'],
                ['type' => 'key', 'pattern' => 'project:{project_id}:detail'],
                ['type' => 'key', 'pattern' => 'project:{project_id}:kpis'],
                ['type' => 'pattern', 'pattern' => 'projects:*'],
                ['type' => 'tag', 'tags' => ['project', 'projects', 'kpi']],
            ],
            'ProjectCreated' => [
                ['type' => 'pattern', 'pattern' => 'projects:*'],
                ['type' => 'tag', 'tags' => ['projects']],
            ],
            'DocumentVersioned' => [
                ['type' => 'key', 'pattern' => 'document:{document_id}'],
                ['type' => 'key', 'pattern' => 'document:{document_id}:detail'],
                ['type' => 'pattern', 'pattern' => 'documents:project:{project_id}:*'],
                ['type' => 'tag', 'tags' => ['document', 'documents']],
            ],
            'DocumentCreated' => [
                ['type' => 'pattern', 'pattern' => 'documents:project:{project_id}:*'],
                ['type' => 'tag', 'tags' => ['documents']],
            ],
        ];
    }

    /**
     * Apply invalidation rule
     * 
     * @param array $rule Invalidation rule
     * @param array $payload Event payload
     */
    private function applyInvalidationRule(array $rule, array $payload): void
    {
        $type = $rule['type'] ?? 'key';
        
        switch ($type) {
            case 'key':
                $key = $this->resolvePattern($rule['pattern'] ?? '', $payload);
                if ($key) {
                    $this->cacheService->invalidate($key);
                }
                break;
                
            case 'pattern':
                $pattern = $this->resolvePattern($rule['pattern'] ?? '', $payload);
                if ($pattern) {
                    $this->cacheService->invalidate(null, null, $pattern);
                }
                break;
                
            case 'tag':
                $tags = $rule['tags'] ?? [];
                if (!empty($tags)) {
                    $this->cacheService->invalidate(null, $tags);
                }
                break;
        }
    }

    /**
     * Resolve pattern with payload values
     * 
     * @param string $pattern Pattern with placeholders (e.g., 'task:{task_id}')
     * @param array $payload Event payload
     * @return string Resolved pattern
     */
    private function resolvePattern(string $pattern, array $payload): string
    {
        // Replace placeholders like {task_id}, {project_id} with actual values
        $resolved = $pattern;
        
        preg_match_all('/\{(\w+)\}/', $pattern, $matches);
        
        foreach ($matches[1] as $placeholder) {
            $value = $payload[$placeholder] ?? $payload['data'][$placeholder] ?? null;
            if ($value !== null) {
                $resolved = str_replace("{{$placeholder}}", $value, $resolved);
            } else {
                // If placeholder not found, return empty (skip this rule)
                return '';
            }
        }
        
        return $resolved;
    }

    /**
     * Invalidate cache for a specific entity
     * 
     * @param string $entity Entity type (e.g., 'task', 'project')
     * @param string $entityId Entity ID
     * @param string|null $tenantId Tenant ID (optional)
     */
    public function invalidateEntity(string $entity, string $entityId, ?string $tenantId = null): void
    {
        $keys = [
            "{$entity}:{$entityId}",
            "{$entity}:{$entityId}:detail",
            "{$entity}:{$entityId}:kpis",
        ];

        foreach ($keys as $key) {
            $this->cacheService->invalidate(key: $key);
        }

        // Also invalidate by pattern
        $this->cacheService->invalidate(pattern: "{$entity}s:*");
        
        // Invalidate by tags
        $this->cacheService->invalidate(tags: [$entity, "{$entity}s"]);
    }

    /**
     * Invalidate all cache for a tenant
     * 
     * @param string $tenantId Tenant ID
     */
    public function invalidateTenant(string $tenantId): void
    {
        $pattern = $this->keyService->tenantPattern($tenantId);
        $this->cacheService->invalidate(pattern: $pattern);
        
        Log::info('Tenant cache invalidated', [
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Invalidate cache for task update
     * 
     * Convenience method for task-related cache invalidation
     * 
     * @param \App\Models\Task $task
     */
    public function forTaskUpdate($task): void
    {
        $tenantId = $task->tenant_id ?? $task->project->tenant_id ?? null;
        
        if (!$tenantId) {
            return;
        }
        
        // Invalidate task-specific cache
        $this->cacheService->invalidate(
            key: "task:{$task->id}",
            tags: ['task', "task_{$task->id}", "tenant_{$tenantId}"]
        );
        
        // Invalidate task list cache for project
        if ($task->project_id) {
            $this->cacheService->invalidate(
                pattern: "tasks:project:{$task->project_id}:*",
                tags: ['tasks', "project_{$task->project_id}"]
            );
            
            // Also invalidate project KPIs since task changes affect project stats
            $this->cacheService->invalidate(
                key: "project:{$task->project_id}:kpis",
                tags: ['kpis', "project_{$task->project_id}"]
            );
        }
        
        // Invalidate task list cache for tenant
        $this->cacheService->invalidate(
            pattern: "tasks:*",
            tags: ['tasks', "tenant_{$tenantId}"]
        );
        
        Log::debug('Task cache invalidated via CacheInvalidationService', [
            'task_id' => $task->id,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Invalidate cache for project update
     * 
     * Convenience method for project-related cache invalidation
     * 
     * @param \App\Models\Project $project
     */
    public function forProjectUpdate($project): void
    {
        $tenantId = $project->tenant_id;
        
        // Invalidate project-specific cache
        $this->cacheService->invalidate(
            key: "project:{$project->id}",
            tags: ['project', "project_{$project->id}", "tenant_{$tenantId}"]
        );
        
        // Invalidate project list cache
        $this->cacheService->invalidate(
            pattern: "projects:*",
            tags: ['projects', "tenant_{$tenantId}"]
        );
        
        // Invalidate project KPIs cache
        $this->cacheService->invalidate(
            key: "project:{$project->id}:kpis",
            tags: ['kpis', "project_{$project->id}"]
        );
        
        Log::debug('Project cache invalidated via CacheInvalidationService', [
            'project_id' => $project->id,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Invalidate cache for document update
     * 
     * Convenience method for document-related cache invalidation
     * 
     * @param \App\Models\Document $document
     */
    public function forDocumentUpdate($document): void
    {
        $tenantId = $document->tenant_id;
        
        // Invalidate document-specific cache
        $this->cacheService->invalidate(
            key: "document:{$document->id}",
            tags: ['document', "document_{$document->id}", "tenant_{$tenantId}"]
        );
        
        // Invalidate document list cache for project
        if ($document->project_id) {
            $this->cacheService->invalidate(
                pattern: "documents:project:{$document->project_id}:*",
                tags: ['documents', "project_{$document->project_id}"]
            );
        }
        
        // Invalidate document list cache for tenant
        $this->cacheService->invalidate(
            pattern: "documents:*",
            tags: ['documents', "tenant_{$tenantId}"]
        );
        
        Log::debug('Document cache invalidated via CacheInvalidationService', [
            'document_id' => $document->id,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Invalidate cache for user update
     * 
     * Convenience method for user-related cache invalidation
     * 
     * @param \App\Models\User $user
     */
    public function forUserUpdate($user): void
    {
        $tenantId = $user->tenant_id;
        
        // Invalidate user-specific cache
        $this->cacheService->invalidate(
            key: "user:{$user->id}",
            tags: ['user', "user_{$user->id}", "tenant_{$tenantId}"]
        );
        
        // Invalidate user list cache for tenant
        if ($tenantId) {
            $this->cacheService->invalidate(
                pattern: "users:tenant:{$tenantId}:*",
                tags: ['users', "tenant_{$tenantId}"]
            );
        }
        
        // Invalidate notifications cache
        $this->cacheService->invalidate(
            key: "notifications:user:{$user->id}",
            tags: ['notifications', "user_{$user->id}"]
        );
        
        Log::debug('User cache invalidated via CacheInvalidationService', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
        ]);
    }
}

