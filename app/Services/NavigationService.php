<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * NavigationService
 * 
 * Centralized service for generating navigation menus based on user permissions.
 * Used by both React (via API) and Blade (via direct service call).
 * 
 * Single source of truth for navigation structure and permission mapping.
 */
class NavigationService
{
    /**
     * Get navigation items for a user
     * 
     * @param User $user
     * @return array
     */
    public static function getNavigation(User $user): array
    {
        // Base navigation items for tenant users
        // PILOT MODE: Only essential menu items for pilot testing
        $navItems = [
            [
                'path' => '/app/dashboard',
                'label' => 'Dashboard',
                'icon' => 'Gauge',
                'perm' => 'dashboard.view',
                'pilot' => true,
            ],
            [
                'path' => '/app/projects',
                'label' => 'Projects',
                'icon' => 'Folder',
                'perm' => 'projects.view',
                'pilot' => true,
            ],
            [
                'path' => '/app/tasks',
                'label' => 'Tasks',
                'icon' => 'ClipboardList',
                'perm' => 'tasks.view',
                'pilot' => true,
            ],
            [
                'path' => '/app/documents',
                'label' => 'Documents',
                'icon' => 'Document',
                'perm' => 'documents.view',
                'pilot' => true,
            ],
            [
                'path' => '/app/clients',
                'label' => 'Clients',
                'icon' => 'Users',
                'perm' => 'clients.view',
                'pilot' => true,
            ],
            // HIDDEN FOR PILOT (half-baked modules):
            // - Quotes (commented out until CRUD is stable)
            // - Settings (commented out - only show for admin if needed)
        ];
        
        // Add Settings only for admin users (if needed)
        if ($user->can('admin.access.tenant') || $user->isSuperAdmin()) {
            $navItems[] = [
                'path' => '/app/settings',
                'label' => 'Settings',
                'icon' => 'Cog',
                'perm' => 'settings.view',
                'pilot' => false,
            ];
        }
        
        // Get user permissions
        $role = $user->role ?? 'member';
        $userPermissions = config('permissions.roles.' . $role, []);
        
        // Helper to check if user has permission (using Gate/Policy)
        $hasPermission = function ($perm) use ($user, $userPermissions) {
            // Super admin has all permissions
            if (in_array('*', $userPermissions) || $user->isSuperAdmin()) {
                return true;
            }
            
            // Use Laravel Gate/Policy for permission check
            return $user->can($perm) || in_array($perm, $userPermissions);
        };
        
        // Filter by permissions
        $filteredNav = array_filter($navItems, function ($item) use ($hasPermission) {
            $perm = $item['perm'] ?? null;
            if (!$perm) {
                return true; // No permission required
            }
            return $hasPermission($perm);
        });
        
        // Add admin items based on admin access level
        $isSuperAdmin = $user->isSuperAdmin() || $user->can('admin.access');
        $isOrgAdmin = $user->can('admin.access.tenant');
        
        if ($isSuperAdmin || $isOrgAdmin) {
            // Admin Dashboard (accessible to both)
            $filteredNav[] = [
                'path' => '/admin/dashboard',
                'label' => 'Admin Dashboard',
                'icon' => 'Shield',
                'admin' => true,
                'perm' => 'admin.access',
            ];
            
            // Templates (accessible to both)
            if ($user->can('admin.templates.manage') || $isSuperAdmin) {
                $filteredNav[] = [
                    'path' => '/admin/templates',
                    'label' => 'WBS Templates',
                    'icon' => 'FileText',
                    'admin' => true,
                    'perm' => 'admin.templates.manage',
                ];
            }
            
            // Projects Portfolio (accessible to both)
            if ($user->can('admin.projects.read') || $isSuperAdmin) {
                $filteredNav[] = [
                    'path' => '/admin/projects',
                    'label' => 'Projects Portfolio',
                    'icon' => 'Folder',
                    'admin' => true,
                    'perm' => 'admin.projects.read',
                ];
            }
            
            // Analytics (accessible to both)
            if ($user->can('admin.analytics.tenant') || $isSuperAdmin) {
                $filteredNav[] = [
                    'path' => '/admin/analytics',
                    'label' => 'Analytics',
                    'icon' => 'BarChart',
                    'admin' => true,
                    'perm' => 'admin.analytics.tenant',
                ];
            }
            
            // Activities (accessible to both)
            if ($user->can('admin.activities.tenant') || $isSuperAdmin) {
                $filteredNav[] = [
                    'path' => '/admin/activities',
                    'label' => 'Activity Log',
                    'icon' => 'History',
                    'admin' => true,
                    'perm' => 'admin.activities.tenant',
                ];
            }
            
            // Settings (accessible to both)
            if ($user->can('admin.settings.tenant') || $isSuperAdmin) {
                $filteredNav[] = [
                    'path' => '/admin/settings',
                    'label' => 'Settings',
                    'icon' => 'Cog',
                    'admin' => true,
                    'perm' => 'admin.settings.tenant',
                ];
            }
            
            // Tenant-scoped items (Org Admin)
            if ($isOrgAdmin && $user->can('admin.members.manage')) {
                $filteredNav[] = [
                    'path' => '/admin/members',
                    'label' => 'Members (Tenant)',
                    'icon' => 'UserGroup',
                    'admin' => true,
                    'tenant_scoped' => true,
                    'perm' => 'admin.members.manage',
                ];
            }
            
            // System-only items (Super Admin only)
            if ($isSuperAdmin) {
                // System-wide user management
                $filteredNav[] = [
                    'path' => '/admin/users',
                    'label' => 'Users (System)',
                    'icon' => 'Users',
                    'admin' => true,
                    'system_only' => true,
                    'perm' => 'admin.access',
                ];
                
                $filteredNav[] = [
                    'path' => '/admin/tenants',
                    'label' => 'Tenants',
                    'icon' => 'Building',
                    'admin' => true,
                    'system_only' => true,
                    'perm' => 'admin.access',
                ];
                
                $filteredNav[] = [
                    'path' => '/admin/security',
                    'label' => 'Security',
                    'icon' => 'Shield',
                    'admin' => true,
                    'system_only' => true,
                    'perm' => 'admin.access',
                ];
                
                $filteredNav[] = [
                    'path' => '/admin/maintenance',
                    'label' => 'Maintenance',
                    'icon' => 'Wrench',
                    'admin' => true,
                    'system_only' => true,
                    'perm' => 'admin.access',
                ];
            }
        }
        
        return array_values($filteredNav);
    }
    
    /**
     * Get navigation items for Blade components
     * Calls service directly (optimized - no HTTP overhead)
     * 
     * @return array
     */
    public static function getNavigationForBlade(): array
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        
        if (!$user) {
            return [];
        }
        
        try {
            return self::getNavigation($user);
        } catch (\Exception $e) {
            Log::error('Error fetching navigation for Blade', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            
            return [];
        }
    }
}

