<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Runtime PermissionService backed by the RBAC tables.
 */
class PermissionService
{
    private PermissionCacheService $cacheService;
    private SecurityAuditService $auditService;
    private ?array $permissionRegistry = null;

    public function __construct(PermissionCacheService $cacheService, SecurityAuditService $auditService)
    {
        $this->cacheService = $cacheService;
        $this->auditService = $auditService;
    }

    public function getEffectivePermissions(User $user, ?string $tenantId = null): array
    {
        $resolvedTenantId = $this->resolveTenantId($user, $tenantId);

        if (!$resolvedTenantId) {
            return [];
        }

        return $this->cacheService->getCachedPermissions((string) $user->id, $resolvedTenantId);
    }

    public function userHasPermission(User $user, string $permissionKey, ?string $tenantId = null): bool
    {
        $resolvedTenantId = $this->resolveTenantId($user, $tenantId);

        if (!$resolvedTenantId) {
            $this->logPermissionCheck($permissionKey, (string) $user->id, '', false, 'tenant_missing');
            return false;
        }

        if (!$this->isPermissionRegistered($permissionKey)) {
            Log::warning('PermissionService: permission key is not registered', [
                'permission' => $permissionKey,
                'user_id' => $user->id,
                'tenant_id' => $resolvedTenantId,
            ]);

            $this->logPermissionCheck($permissionKey, (string) $user->id, $resolvedTenantId, false, 'permission_not_registered');
            $this->noteUnknownPermission($permissionKey, $resolvedTenantId, (string) $user->id);
            return false;
        }

        $effectivePermissions = $this->getEffectivePermissions($user, $resolvedTenantId);
        $granted = $this->matchesPermissionList($permissionKey, $effectivePermissions);

        $this->logPermissionCheck($permissionKey, (string) $user->id, $resolvedTenantId, $granted, $granted ? 'granted' : 'denied');

        return $granted;
    }

    public function userHasPermissionById(string $userId, string $permissionKey, ?string $tenantId = null): bool
    {
        $user = User::find($userId);

        if (!$user) {
            Log::warning('PermissionService: user not found for permission check', [
                'user_id' => $userId,
                'permission' => $permissionKey,
            ]);

            return false;
        }

        return $this->userHasPermission($user, $permissionKey, $tenantId);
    }

    public function filterSidebarItems(array $items, ?User $user, ?string $tenantId = null): array
    {
        if (!$user) {
            return $items;
        }

        $resolvedTenantId = $this->resolveTenantId($user, $tenantId);

        if (!$resolvedTenantId) {
            return $items;
        }

        return $this->filterItemsRecursive($items, $user, $resolvedTenantId);
    }

    private function filterItemsRecursive(array $items, User $user, string $tenantId): array
    {
        $result = [];

        foreach ($items as $item) {
            $itemCopy = $item;
            $requiredPermissions = $this->normalizeRequiredPermissions(
                $itemCopy['required_permissions'] ?? $itemCopy['permissions'] ?? []
            );

            $allowed = true;

            foreach ($requiredPermissions as $permission) {
                if ($permission === '*') {
                    continue;
                }

                if (!$this->userHasPermission($user, $permission, $tenantId)) {
                    $allowed = false;
                    break;
                }
            }

            if (!$allowed) {
                continue;
            }

            if (!empty($itemCopy['items']) && is_array($itemCopy['items'])) {
                $itemCopy['items'] = $this->filterItemsRecursive($itemCopy['items'], $user, $tenantId);

                if (empty($itemCopy['items'])) {
                    unset($itemCopy['items']);
                }
            }

            $result[] = $itemCopy;
        }

        return $result;
    }

    private function normalizeRequiredPermissions(mixed $input): array
    {
        if (is_string($input)) {
            $input = [$input];
        }

        if (!is_iterable($input)) {
            return [];
        }

        return array_values(array_filter(Arr::wrap($input), static fn($permission) => is_string($permission)));
    }

    public function canUserCreateProjects(string $userId, string $tenantId): bool
    {
        return $this->userHasPermissionById($userId, 'projects.create', $tenantId);
    }

    public function canUserCreateTasks(string $userId, string $tenantId): bool
    {
        return $this->userHasPermissionById($userId, 'tasks.create', $tenantId);
    }

    public function canUserAccessProject(Project $project, string $userId): bool
    {
        $user = User::find($userId);

        if (!$user) {
            return false;
        }

        if ($project->tenant_id && $user->tenant_id && $project->tenant_id !== $user->tenant_id) {
            Log::warning('PermissionService: tenant mismatch during project access', [
                'project_id' => $project->id,
                'project_tenant_id' => $project->tenant_id,
                'user_id' => $userId,
                'user_tenant_id' => $user->tenant_id,
            ]);

            return false;
        }

        if ($project->user_id === $userId) {
            return true;
        }

        return $this->userHasPermission(
            $user,
            'projects.view',
            $project->tenant_id ?? $user->tenant_id
        );
    }

    public function canUserModifyProject(Project $project, string $userId): bool
    {
        $user = User::find($userId);

        if (!$user) {
            return false;
        }

        if ($project->tenant_id && $user->tenant_id && $project->tenant_id !== $user->tenant_id) {
            return false;
        }

        if ($project->user_id === $userId) {
            return true;
        }

        return $this->userHasPermission(
            $user,
            'projects.update',
            $project->tenant_id ?? $user->tenant_id
        );
    }

    public function canUserDeleteProject(Project $project, string $userId): bool
    {
        $user = User::find($userId);

        if (!$user) {
            return false;
        }

        if ($project->tenant_id && $user->tenant_id && $project->tenant_id !== $user->tenant_id) {
            return false;
        }

        if ($project->user_id === $userId) {
            return true;
        }

        return $this->userHasPermission(
            $user,
            'projects.delete',
            $project->tenant_id ?? $user->tenant_id
        );
    }

    public function canUserAccessTask(Task $task, string $userId): bool
    {
        $user = User::find($userId);

        if (!$user) {
            return false;
        }

        if ($task->tenant_id && $user->tenant_id && $task->tenant_id !== $user->tenant_id) {
            Log::warning('PermissionService: tenant mismatch during task access', [
                'task_id' => $task->id,
                'task_tenant_id' => $task->tenant_id,
                'user_id' => $userId,
                'user_tenant_id' => $user->tenant_id,
            ]);

            return false;
        }

        if ($task->user_id === $userId || $task->assigned_to === $userId) {
            return true;
        }

        if ($task->project_id) {
            $project = Project::find($task->project_id);
            if ($project && $this->canUserAccessProject($project, $userId)) {
                return true;
            }
        }

        return $this->userHasPermission(
            $user,
            'tasks.view',
            $task->tenant_id ?? $user->tenant_id
        );
    }

    public function canUserModifyTask(Task $task, string $userId): bool
    {
        $user = User::find($userId);

        if (!$user) {
            return false;
        }

        if ($task->tenant_id && $user->tenant_id && $task->tenant_id !== $user->tenant_id) {
            return false;
        }

        if ($task->user_id === $userId || $task->assigned_to === $userId) {
            return true;
        }

        if ($task->project_id) {
            $project = Project::find($task->project_id);
            if ($project && $this->canUserModifyProject($project, $userId)) {
                return true;
            }
        }

        return $this->userHasPermission(
            $user,
            'tasks.update',
            $task->tenant_id ?? $user->tenant_id
        );
    }

    public function canUserDeleteTask(Task $task, string $userId): bool
    {
        $user = User::find($userId);

        if (!$user) {
            return false;
        }

        if ($task->tenant_id && $user->tenant_id && $task->tenant_id !== $user->tenant_id) {
            return false;
        }

        if ($task->user_id === $userId || $task->assigned_to === $userId) {
            return true;
        }

        if ($task->project_id) {
            $project = Project::find($task->project_id);
            if ($project && $this->canUserDeleteProject($project, $userId)) {
                return true;
            }
        }

        return $this->userHasPermission(
            $user,
            'tasks.delete',
            $task->tenant_id ?? $user->tenant_id
        );
    }

    public function invalidateCachesForUserId(string $userId): void
    {
        $user = User::with('tenants')->find($userId);

        if (!$user) {
            return;
        }

        $this->invalidateCachesForUser($user);
    }

    public function invalidateCachesForRole(Role $role): void
    {
        $role->loadMissing('systemUsers.tenants');
        $processed = [];

        foreach ($role->systemUsers as $user) {
            if (isset($processed[$user->id])) {
                continue;
            }

            $tenantIds = $this->collectUserTenantIds($user);
            if (empty($tenantIds)) {
                $processed[$user->id] = true;
                continue;
            }

            $this->invalidateCachesForUser($user, $tenantIds);
            $processed[$user->id] = true;
        }

        if ($role->tenant_id) {
            $this->cacheService->invalidateTenantPermissions($role->tenant_id);
        }
    }

    private function invalidateCachesForUser(User $user, ?array $tenantIds = null): void
    {
        $tenantIds = $tenantIds ?? $this->collectUserTenantIds($user);

        foreach (array_unique($tenantIds) as $tenantId) {
            $this->cacheService->invalidateUserPermissions($user->id, $tenantId);
        }
    }

    private function collectUserTenantIds(User $user): array
    {
        if (!$user->relationLoaded('tenants')) {
            $user->load('tenants');
        }

        $tenantIds = $user->tenants->pluck('id')->filter()->unique()->values()->all();

        if ($user->tenant_id) {
            $tenantIds[] = $user->tenant_id;
        }

        return array_values(array_unique($tenantIds));
    }

    private function resolveTenantId(?User $user, ?string $tenantId = null): ?string
    {
        if (!empty($tenantId)) {
            return $tenantId;
        }

        if ($user && !empty($user->tenant_id)) {
            return $user->tenant_id;
        }

        if (app()->has('current_tenant_id')) {
            return (string) app('current_tenant_id');
        }

        return null;
    }

    private function matchesPermissionList(string $permission, array $granted): bool
    {
        foreach ($granted as $candidate) {
            if ($candidate === $permission || $this->matchesWildcardPermission($permission, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function matchesWildcardPermission(string $permission, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -2);
            return str_starts_with($permission, $prefix . '.');
        }

        return false;
    }

    private function logPermissionCheck(string $permission, string $userId, string $tenantId, bool $result, string $reason = 'check'): void
    {
        try {
            $this->auditService->logPermissionCheck($permission, $userId, $tenantId, $result, $reason);
        } catch (\Throwable $exception) {
            Log::debug('PermissionService: failed to log permission event', [
                'permission' => $permission,
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function noteUnknownPermission(string $permission, string $tenantId, string $userId): void
    {
        $key = "rbac:orphan_permission:{$permission}";

        if (Cache::add($key, true, 86400)) {
            Log::warning('RBAC contains permission code not defined in registry', [
                'permission' => $permission,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
            ]);
        }
    }

    private function isPermissionRegistered(string $permission): bool
    {
        return in_array($permission, $this->loadPermissionRegistry(), true);
    }

    private function loadPermissionRegistry(): array
    {
        if ($this->permissionRegistry !== null) {
            return $this->permissionRegistry;
        }

        $registry = [];
        $permissionConfig = config('permissions', []);

        array_walk_recursive($permissionConfig, function ($value) use (&$registry) {
            if (is_string($value) && str_contains($value, '.')) {
                $registry[] = $value;
            }
        });

        $this->permissionRegistry = array_unique($registry);

        return $this->permissionRegistry;
    }
}
