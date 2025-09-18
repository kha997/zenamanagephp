<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\SidebarConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SecurityGuardService
{
    /**
     * Validate sidebar configuration for security.
     */
    public function validateSidebarConfig(array $config, User $user): array
    {
        $errors = [];
        
        // Check if user has permission to access sidebar config
        if (!$this->canAccessSidebarConfig($user)) {
            $errors[] = 'User does not have permission to access sidebar configuration';
            return $errors;
        }

        // Validate config structure
        $structureErrors = $this->validateConfigStructure($config);
        $errors = array_merge($errors, $structureErrors);

        // Validate permissions
        $permissionErrors = $this->validatePermissions($config, $user);
        $errors = array_merge($errors, $permissionErrors);

        // Validate URLs
        $urlErrors = $this->validateUrls($config);
        $errors = array_merge($errors, $urlErrors);

        // Validate external links
        $externalErrors = $this->validateExternalLinks($config);
        $errors = array_merge($errors, $externalErrors);

        return $errors;
    }

    /**
     * Check if user can access sidebar config.
     */
    protected function canAccessSidebarConfig(User $user): bool
    {
        // Super Admin can always access
        if ($user->email === 'admin@zena.com') {
            return true;
        }

        // Check if user has admin.sidebar.manage permission
        return $user->hasPermissionCheck('admin.sidebar.manage');
    }

    /**
     * Validate config structure.
     */
    protected function validateConfigStructure(array $config): array
    {
        $errors = [];

        if (!isset($config['items']) || !is_array($config['items'])) {
            $errors[] = 'Config must have items array';
            return $errors;
        }

        foreach ($config['items'] as $index => $item) {
            $itemErrors = $this->validateItemStructure($item, $index);
            $errors = array_merge($errors, $itemErrors);
        }

        return $errors;
    }

    /**
     * Validate individual item structure.
     */
    protected function validateItemStructure(array $item, int $index): array
    {
        $errors = [];

        // Required fields
        $requiredFields = ['id', 'type', 'label', 'enabled', 'order'];
        foreach ($requiredFields as $field) {
            if (!isset($item[$field])) {
                $errors[] = "Item {$index}: Missing required field '{$field}'";
            }
        }

        // Validate type
        $validTypes = ['group', 'link', 'external', 'divider'];
        if (isset($item['type']) && !in_array($item['type'], $validTypes)) {
            $errors[] = "Item {$index}: Invalid type '{$item['type']}'. Must be one of: " . implode(', ', $validTypes);
        }

        // Validate order
        if (isset($item['order']) && (!is_numeric($item['order']) || $item['order'] < 0)) {
            $errors[] = "Item {$index}: Order must be a positive number";
        }

        // Validate children for groups
        if (isset($item['type']) && $item['type'] === 'group') {
            if (!isset($item['children']) || !is_array($item['children'])) {
                $errors[] = "Item {$index}: Group items must have children array";
            } else {
                foreach ($item['children'] as $childIndex => $child) {
                    $childErrors = $this->validateItemStructure($child, "{$index}.{$childIndex}");
                    $errors = array_merge($errors, $childErrors);
                }
            }
        }

        return $errors;
    }

    /**
     * Validate permissions.
     */
    protected function validatePermissions(array $config, User $user): array
    {
        $errors = [];

        foreach ($config['items'] as $index => $item) {
            $permissionErrors = $this->validateItemPermissions($item, $user, $index);
            $errors = array_merge($errors, $permissionErrors);
        }

        return $errors;
    }

    /**
     * Validate item permissions.
     */
    protected function validateItemPermissions(array $item, User $user, int $index): array
    {
        $errors = [];

        if (isset($item['required_permissions']) && is_array($item['required_permissions'])) {
            foreach ($item['required_permissions'] as $permission) {
                if (!$user->hasPermissionCheck($permission)) {
                    $errors[] = "Item {$index}: User does not have permission '{$permission}'";
                }
            }
        }

        // Validate children permissions
        if (isset($item['children'])) {
            foreach ($item['children'] as $childIndex => $child) {
                $childErrors = $this->validateItemPermissions($child, $user, "{$index}.{$childIndex}");
                $errors = array_merge($errors, $childErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate URLs.
     */
    protected function validateUrls(array $config): array
    {
        $errors = [];

        foreach ($config['items'] as $index => $item) {
            $urlErrors = $this->validateItemUrls($item, $index);
            $errors = array_merge($errors, $urlErrors);
        }

        return $errors;
    }

    /**
     * Validate item URLs.
     */
    protected function validateItemUrls(array $item, int $index): array
    {
        $errors = [];

        // Validate internal routes
        if (isset($item['to'])) {
            if (!$this->isValidInternalRoute($item['to'])) {
                $errors[] = "Item {$index}: Invalid internal route '{$item['to']}'";
            }
        }

        // Validate external links
        if (isset($item['href'])) {
            if (!$this->isValidExternalUrl($item['href'])) {
                $errors[] = "Item {$index}: Invalid external URL '{$item['href']}'";
            }
        }

        // Validate children URLs
        if (isset($item['children'])) {
            foreach ($item['children'] as $childIndex => $child) {
                $childErrors = $this->validateItemUrls($child, "{$index}.{$childIndex}");
                $errors = array_merge($errors, $childErrors);
            }
        }

        return $errors;
    }

    /**
     * Validate external links.
     */
    protected function validateExternalLinks(array $config): array
    {
        $errors = [];

        foreach ($config['items'] as $index => $item) {
            $externalErrors = $this->validateItemExternalLinks($item, $index);
            $errors = array_merge($errors, $externalErrors);
        }

        return $errors;
    }

    /**
     * Validate item external links.
     */
    protected function validateItemExternalLinks(array $item, int $index): array
    {
        $errors = [];

        if (isset($item['href'])) {
            // Check for dangerous protocols
            $dangerousProtocols = ['javascript:', 'data:', 'vbscript:', 'file:'];
            $href = strtolower($item['href']);
            
            foreach ($dangerousProtocols as $protocol) {
                if (strpos($href, $protocol) === 0) {
                    $errors[] = "Item {$index}: Dangerous protocol detected in href '{$item['href']}'";
                }
            }

            // Check for suspicious domains
            if (!$this->isAllowedDomain($item['href'])) {
                $errors[] = "Item {$index}: External domain not allowed '{$item['href']}'";
            }
        }

        // Validate children external links
        if (isset($item['children'])) {
            foreach ($item['children'] as $childIndex => $child) {
                $childErrors = $this->validateItemExternalLinks($child, "{$index}.{$childIndex}");
                $errors = array_merge($errors, $childErrors);
            }
        }

        return $errors;
    }

    /**
     * Check if internal route is valid.
     */
    protected function isValidInternalRoute(string $route): bool
    {
        // Allow common routes
        $allowedRoutes = [
            '/dashboard',
            '/projects',
            '/tasks',
            '/users',
            '/team',
            '/analytics',
            '/reports',
            '/admin',
            '/admin/sidebar-builder',
            '/admin/settings',
            '/rfis',
            '/submittals',
            '/change-requests',
            '/qc',
            '/finance',
            '/procurement',
            '/vendors',
            '/drawings',
            '/site-diary',
            '/materials',
        ];

        // Check exact match
        if (in_array($route, $allowedRoutes)) {
            return true;
        }

        // Check if route starts with allowed prefix
        foreach ($allowedRoutes as $allowedRoute) {
            if (strpos($route, $allowedRoute) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if external URL is valid.
     */
    protected function isValidExternalUrl(string $url): bool
    {
        // Basic URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check protocol
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['http', 'https'])) {
            return false;
        }

        return true;
    }

    /**
     * Check if domain is allowed.
     */
    protected function isAllowedDomain(string $url): bool
    {
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host'])) {
            return false;
        }

        $host = $parsedUrl['host'];
        
        // Allow localhost for development
        if (in_array($host, ['localhost', '127.0.0.1', '::1'])) {
            return true;
        }

        // Allow trusted domains
        $trustedDomains = [
            'zena.com',
            'zenamanage.com',
            'github.com',
            'stackoverflow.com',
            'laravel.com',
            'tailwindcss.com',
        ];

        foreach ($trustedDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get fallback sidebar configuration.
     */
    public function getFallbackSidebar(User $user): array
    {
        Log::warning('Using fallback sidebar configuration', [
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);

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
                [
                    'id' => 'projects',
                    'type' => 'link',
                    'label' => 'Projects',
                    'icon' => 'Building',
                    'to' => '/projects',
                    'required_permissions' => ['project.read'],
                    'enabled' => true,
                    'order' => 20,
                ],
                [
                    'id' => 'tasks',
                    'type' => 'link',
                    'label' => 'Tasks',
                    'icon' => 'ListChecks',
                    'to' => '/tasks',
                    'required_permissions' => ['task.read'],
                    'enabled' => true,
                    'order' => 30,
                ],
            ],
        ];
    }

    /**
     * Check if sidebar config is safe to use.
     */
    public function isSafeToUse(array $config, User $user): bool
    {
        $errors = $this->validateSidebarConfig($config, $user);
        return empty($errors);
    }

    /**
     * Sanitize sidebar configuration.
     */
    public function sanitizeConfig(array $config): array
    {
        $sanitized = $config;

        // Remove dangerous fields
        $dangerousFields = ['script', 'onclick', 'onload', 'onerror'];
        $sanitized = $this->removeDangerousFields($sanitized, $dangerousFields);

        // Sanitize strings
        $sanitized = $this->sanitizeStrings($sanitized);

        return $sanitized;
    }

    /**
     * Remove dangerous fields from config.
     */
    protected function removeDangerousFields(array $config, array $dangerousFields): array
    {
        foreach ($config as $key => $value) {
            if (in_array($key, $dangerousFields)) {
                unset($config[$key]);
            } elseif (is_array($value)) {
                $config[$key] = $this->removeDangerousFields($value, $dangerousFields);
            }
        }

        return $config;
    }

    /**
     * Sanitize strings in config.
     */
    protected function sanitizeStrings(array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_string($value)) {
                // Remove HTML tags
                $config[$key] = strip_tags($value);
                
                // Escape special characters
                $config[$key] = htmlspecialchars($config[$key], ENT_QUOTES, 'UTF-8');
            } elseif (is_array($value)) {
                $config[$key] = $this->sanitizeStrings($value);
            }
        }

        return $config;
    }

    /**
     * Log security events.
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        Log::channel('security')->warning($event, array_merge($context, [
            'timestamp' => now(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]));
    }

    /**
     * Check for suspicious activity.
     */
    public function checkSuspiciousActivity(User $user): bool
    {
        $cacheKey = "suspicious_activity_user_{$user->id}";
        $activityCount = Cache::get($cacheKey, 0);

        // If user has made too many requests in short time
        if ($activityCount > 100) {
            $this->logSecurityEvent('Suspicious activity detected', [
                'user_id' => $user->id,
                'activity_count' => $activityCount,
            ]);
            return true;
        }

        // Increment activity counter
        Cache::put($cacheKey, $activityCount + 1, 300); // 5 minutes

        return false;
    }

    /**
     * Clear security cache.
     */
    public function clearSecurityCache(User $user): void
    {
        $cacheKey = "suspicious_activity_user_{$user->id}";
        Cache::forget($cacheKey);
    }
}
