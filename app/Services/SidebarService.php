<?php declare(strict_types=1);

namespace App\Services;

use App\Models\SidebarConfig;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SidebarService
{
    protected PermissionService $permissionService;
    protected ConditionalDisplayService $conditionalDisplayService;
    protected SecurityGuardService $securityGuardService;
    protected UserPreferenceService $userPreferenceService;

    public function __construct(
        PermissionService $permissionService,
        ConditionalDisplayService $conditionalDisplayService,
        SecurityGuardService $securityGuardService,
        UserPreferenceService $userPreferenceService
    ) {
        $this->permissionService = $permissionService;
        $this->conditionalDisplayService = $conditionalDisplayService;
        $this->securityGuardService = $securityGuardService;
        $this->userPreferenceService = $userPreferenceService;
    }
    /**
     * Get sidebar configuration for a user with 3-layer priority.
     * 
     * Priority order:
     * 1. User preferences (highest priority)
     * 2. Tenant-specific role override
     * 3. Default system configuration (lowest priority)
     */
    public function getSidebarForUser(?User $user = null): array
    {
        $user = $user ?? Auth::user();
        
        if (!$user) {
            return $this->getDefaultSidebar();
        }

        $cacheKey = "sidebar_user_{$user->id}_{$user->tenant_id}";
        
        return Cache::remember($cacheKey, 300, function () use ($user) {
            $userPreferences = $this->userPreferenceService->getUserPreferences($user);
            $conditionalService = app(ConditionalDisplayService::class);
            
            return $this->buildSidebar($userPreferences, $conditionalService, $user);
        });
    }

    /**
     * Get sidebar configuration for a specific role.
     */
    public function getSidebarForRole(string $roleName, ?string $tenantId = null): array
    {
        $cacheKey = "sidebar_role_{$roleName}_{$tenantId}";
        
        return Cache::remember($cacheKey, 600, function () use ($roleName, $tenantId) {
            $roleConfig = $this->getRoleBasedSidebar($roleName);
            $conditionalService = app(ConditionalDisplayService::class);
            
            return $this->buildSidebar($roleConfig, $conditionalService, null, $tenantId);
        });
    }

    /**
     * Get role-based sidebar configuration.
     */
    public function getRoleBasedSidebar(string $roleName): array
    {
        $roleConfigs = [
            'super_admin' => [
                'items' => [
                    ['id' => 'dashboard', 'name' => 'Dashboard', 'icon' => 'home', 'route' => 'app.dashboard'],
                    ['id' => 'projects', 'name' => 'Projects', 'icon' => 'folder', 'route' => 'app.projects'],
                    ['id' => 'users', 'name' => 'Users', 'icon' => 'users', 'route' => 'app.users'],
                    ['id' => 'reports', 'name' => 'Reports', 'icon' => 'chart', 'route' => 'app.reports'],
                    ['id' => 'settings', 'name' => 'Settings', 'icon' => 'settings', 'route' => 'app.settings'],
                ]
            ],
            'admin' => [
                'items' => [
                    ['id' => 'dashboard', 'name' => 'Dashboard', 'icon' => 'home', 'route' => 'app.dashboard'],
                    ['id' => 'projects', 'name' => 'Projects', 'icon' => 'folder', 'route' => 'app.projects'],
                    ['id' => 'users', 'name' => 'Users', 'icon' => 'users', 'route' => 'app.users'],
                    ['id' => 'reports', 'name' => 'Reports', 'icon' => 'chart', 'route' => 'app.reports'],
                ]
            ],
            'project_manager' => [
                'items' => [
                    ['id' => 'dashboard', 'name' => 'Dashboard', 'icon' => 'home', 'route' => 'app.dashboard'],
                    ['id' => 'projects', 'name' => 'Projects', 'icon' => 'folder', 'route' => 'app.projects'],
                    ['id' => 'reports', 'name' => 'Reports', 'icon' => 'chart', 'route' => 'app.reports'],
                ]
            ],
            'member' => [
                'items' => [
                    ['id' => 'dashboard', 'name' => 'Dashboard', 'icon' => 'home', 'route' => 'app.dashboard'],
                    ['id' => 'projects', 'name' => 'Projects', 'icon' => 'folder', 'route' => 'app.projects'],
                ]
            ],
            'client' => [
                'items' => [
                    ['id' => 'dashboard', 'name' => 'Dashboard', 'icon' => 'home', 'route' => 'app.dashboard'],
                    ['id' => 'projects', 'name' => 'Projects', 'icon' => 'folder', 'route' => 'app.projects'],
                ]
            ],
        ];

        return $roleConfigs[$roleName] ?? $roleConfigs['member'];
    }

    /**
     * Build sidebar configuration with conditional display.
     */
    protected function buildSidebar(array $config, ConditionalDisplayService $conditionalService, ?User $user = null, ?string $tenantId = null): array
    {
        $items = $config['items'] ?? [];
        $builtItems = [];

        foreach ($items as $item) {
            // Check conditional display
            if (isset($item['conditions']) && $user) {
                $shouldShow = $conditionalService->evaluateConditions($item['conditions'], $user);
                if (!$shouldShow) {
                    continue;
                }
            }

            // Add item to built sidebar
            $builtItems[] = $item;
        }

        return [
            'items' => $builtItems,
            'metadata' => [
                'user_id' => $user?->id,
                'tenant_id' => $tenantId ?? $user?->tenant_id,
                'generated_at' => now()->toISOString(),
            ]
        ];
    }

    /**
     * Build sidebar configuration with 3-layer priority.
     */
    protected function buildSidebarConfig(User $user): array
    {
        $userRole = $this->getUserRole($user);
        $tenantId = $user->tenant_id;
        
        // Layer 1: User preferences (highest priority)
        $userPrefs = $this->getUserPreferences($user);
        
        // Layer 2: Tenant-specific role override
        $tenantConfig = $this->getTenantConfig($userRole, $tenantId);
        
        // Layer 3: Default system configuration (lowest priority)
        $defaultConfig = SidebarConfig::getDefaultForRole($userRole);
        
        // Merge configurations with priority
        $mergedConfig = $this->mergeConfigurations($userPrefs, $tenantConfig, $defaultConfig);
        
        // Apply permission filtering
        $filteredConfig = $this->applyPermissionFiltering($mergedConfig, $user);
        
        // Apply conditional display rules
        $finalConfig = $this->conditionalDisplayService->applyConditionalDisplay($filteredConfig, $user);
        
        // Security validation and fallback
        if (!$this->securityGuardService->isSafeToUse($finalConfig, $user)) {
            $this->securityGuardService->logSecurityEvent('Unsafe sidebar config detected, using fallback', [
                'user_id' => $user->id,
                'config' => $finalConfig,
            ]);
            
            return $this->securityGuardService->getFallbackSidebar($user);
        }
        
        // Apply user preferences (highest priority)
        $finalConfig = $this->userPreferenceService->applyUserPreferences($finalConfig, $user);
        
        // Sanitize config
        $finalConfig = $this->securityGuardService->sanitizeConfig($finalConfig);
        
        return $finalConfig;
    }

    /**
     * Build sidebar configuration for a specific role.
     */
    protected function buildRoleSidebarConfig(string $roleName, ?string $tenantId): array
    {
        // Layer 1: Tenant-specific role override
        $tenantConfig = $this->getTenantConfig($roleName, $tenantId);
        
        // Layer 2: Default system configuration
        $defaultConfig = SidebarConfig::getDefaultForRole($roleName);
        
        // Merge configurations
        $mergedConfig = $this->mergeConfigurations([], $tenantConfig, $defaultConfig);
        
        return $mergedConfig;
    }

    /**
     * Get user's role.
     */
    protected function getUserRole(User $user): string
    {
        
        // For now, return a default role based on user attributes
        if ($user->email === 'admin@zena.com') {
            return 'super_admin';
        }
        
        return 'project_manager'; // Default role
    }

    /**
     * Get user preferences (Layer 1 - Highest Priority).
     */
    protected function getUserPreferences(User $user): array
    {
        
        // This would come from a user_preferences table or similar
        return [];
    }

    /**
     * Get tenant-specific configuration (Layer 2).
     */
    protected function getTenantConfig(string $roleName, ?string $tenantId): array
    {
        if (!$tenantId) {
            return [];
        }

        $config = SidebarConfig::forRole($roleName)
            ->forTenant($tenantId)
            ->enabled()
            ->first();

        return $config ? $config->config : [];
    }

    /**
     * Merge configurations with priority.
     */
    protected function mergeConfigurations(array $userPrefs, array $tenantConfig, array $defaultConfig): array
    {
        // Start with default configuration
        $merged = $defaultConfig;
        
        // Apply tenant overrides
        if (!empty($tenantConfig)) {
            $merged = $this->mergeConfig($merged, $tenantConfig);
        }
        
        // Apply user preferences (highest priority)
        if (!empty($userPrefs)) {
            $merged = $this->mergeConfig($merged, $userPrefs);
        }
        
        return $merged;
    }

    /**
     * Merge two configuration arrays.
     */
    protected function mergeConfig(array $base, array $override): array
    {
        $result = $base;
        
        // Merge items array
        if (isset($override['items'])) {
            $result['items'] = $this->mergeItems($result['items'] ?? [], $override['items']);
        }
        
        return $result;
    }

    /**
     * Merge items arrays with priority.
     */
    protected function mergeItems(array $baseItems, array $overrideItems): array
    {
        $result = [];
        $processedIds = [];
        
        // Process override items first (higher priority)
        foreach ($overrideItems as $item) {
            $result[] = $item;
            $processedIds[] = $item['id'];
        }
        
        // Add base items that weren't overridden
        foreach ($baseItems as $item) {
            if (!in_array($item['id'], $processedIds)) {
                $result[] = $item;
            }
        }
        
        // Sort by order
        usort($result, function ($a, $b) {
            $orderA = $a['order'] ?? 9999;
            $orderB = $b['order'] ?? 9999;
            return $orderA <=> $orderB;
        });
        
        return $result;
    }

    /**
     * Apply permission filtering to sidebar items.
     */
    protected function applyPermissionFiltering(array $config, User $user): array
    {
        if (!isset($config['items'])) {
            return $config;
        }

        $config['items'] = $this->permissionService->filterSidebarItems($config['items'], $user);
        return $config;
    }

    /**
     * Get default sidebar configuration.
     */
    protected function getDefaultSidebar(): array
    {
        return [
            'items' => [
                [
                    'id' => 'dashboard',
                    'type' => 'link',
                    'label' => 'Dashboard',
                    'icon' => 'TachometerAlt',
                    'to' => '/dashboard',
                    'required_permissions' => [],
                    'enabled' => true,
                    'order' => 10,
                ],
            ],
        ];
    }

    /**
     * Clear sidebar cache for a user.
     */
    public function clearUserCache(User $user): void
    {
        $cacheKey = "sidebar_user_{$user->id}_{$user->tenant_id}";
        Cache::forget($cacheKey);
    }

    /**
     * Clear sidebar cache for a role.
     */
    public function clearRoleCache(string $roleName, ?string $tenantId = null): void
    {
        $cacheKey = "sidebar_role_{$roleName}_{$tenantId}";
        Cache::forget($cacheKey);
    }

    /**
     * Clear all sidebar caches.
     */
    public function clearAllCaches(): void
    {
        
        Cache::flush();
    }
}