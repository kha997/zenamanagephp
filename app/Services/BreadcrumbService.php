<?php

namespace App\Services;

use Illuminate\Support\Facades\Route;

class BreadcrumbService
{
    /**
     * Generate breadcrumbs based on current route
     */
    public static function generate(): array
    {
        $currentRoute = Route::current();
        if (!$currentRoute) {
            return [];
        }

        $routeName = $currentRoute->getName();
        $breadcrumbs = [];

        // Root breadcrumb
        $breadcrumbs[] = [
            'title' => 'Dashboard',
            'url' => '/app/dashboard',
            'active' => false
        ];

        // Parse route name to generate breadcrumbs
        if ($routeName) {
            $segments = explode('.', $routeName);
            
            // Handle admin routes
            if (isset($segments[0]) && $segments[0] === 'admin') {
                $breadcrumbs = self::generateAdminBreadcrumbs($segments);
            }
            
            // Handle app routes
            if (isset($segments[0]) && $segments[0] === 'app') {
                $breadcrumbs = self::generateAppBreadcrumbs($segments);
            }
        }

        return $breadcrumbs;
    }

    /**
     * Generate breadcrumbs for admin routes
     */
    private static function generateAdminBreadcrumbs(array $segments): array
    {
        $breadcrumbs = [
            [
                'title' => 'Admin',
                'url' => '/admin',
                'active' => false
            ]
        ];

        if (count($segments) > 1) {
            $section = $segments[1];
            $breadcrumbs[] = [
                'title' => ucfirst(str_replace('_', ' ', $section)),
                'url' => "/admin/{$section}",
                'active' => count($segments) === 2
            ];

            // Handle sub-routes
            if (count($segments) > 2) {
                $subSection = $segments[2];
                $breadcrumbs[] = [
                    'title' => ucfirst(str_replace('_', ' ', $subSection)),
                    'url' => "/admin/{$section}/{$subSection}",
                    'active' => true
                ];
            }
        }

        return $breadcrumbs;
    }

    /**
     * Generate breadcrumbs for app routes
     */
    private static function generateAppBreadcrumbs(array $segments): array
    {
        $breadcrumbs = [
            [
                'title' => 'Dashboard',
                'url' => '/app/dashboard',
                'active' => false
            ]
        ];

        if (count($segments) > 1) {
            $section = $segments[1];
            $breadcrumbs[] = [
                'title' => ucfirst(str_replace('_', ' ', $section)),
                'url' => "/app/{$section}",
                'active' => count($segments) === 2
            ];

            // Handle sub-routes
            if (count($segments) > 2) {
                $subSection = $segments[2];
                $breadcrumbs[] = [
                    'title' => ucfirst(str_replace('_', ' ', $subSection)),
                    'url' => "/app/{$section}/{$subSection}",
                    'active' => true
                ];
            }
        }

        return $breadcrumbs;
    }

    /**
     * Get page title based on current route
     */
    public static function getPageTitle(): string
    {
        $currentRoute = Route::current();
        if (!$currentRoute) {
            return 'ZenaManage';
        }

        $routeName = $currentRoute->getName();
        if (!$routeName) {
            return 'ZenaManage';
        }

        $segments = explode('.', $routeName);
        
        // Handle admin routes
        if (isset($segments[0]) && $segments[0] === 'admin') {
            if (count($segments) === 1) {
                return 'Admin Dashboard - ZenaManage';
            }
            $section = ucfirst(str_replace('_', ' ', $segments[1]));
            return "{$section} - Admin - ZenaManage";
        }
        
        // Handle app routes
        if (isset($segments[0]) && $segments[0] === 'app') {
            if (count($segments) === 1) {
                return 'Dashboard - ZenaManage';
            }
            $section = ucfirst(str_replace('_', ' ', $segments[1]));
            return "{$section} - ZenaManage";
        }

        return 'ZenaManage';
    }

    /**
     * Get page description based on current route
     */
    public static function getPageDescription(): string
    {
        $currentRoute = Route::current();
        if (!$currentRoute) {
            return 'ZenaManage - Project Management System';
        }

        $routeName = $currentRoute->getName();
        if (!$routeName) {
            return 'ZenaManage - Project Management System';
        }

        $descriptions = [
            'admin.dashboard' => 'System administration and management dashboard',
            'admin.users' => 'Manage system users and permissions',
            'admin.tenants' => 'Manage tenant organizations',
            'admin.security' => 'Security settings and monitoring',
            'admin.alerts' => 'System alerts and notifications',
            'admin.activities' => 'System activity logs and monitoring',
            'admin.projects' => 'Manage system-wide projects',
            'admin.settings' => 'System configuration and settings',
            'app.dashboard' => 'Project management dashboard',
            'app.projects' => 'Manage your projects',
            'app.tasks' => 'Manage project tasks',
            'app.documents' => 'Document management and sharing',
            'app.team' => 'Team management and collaboration',
            'app.settings' => 'User settings and preferences',
        ];

        return $descriptions[$routeName] ?? 'ZenaManage - Project Management System';
    }

    /**
     * Get breadcrumb HTML
     */
    public static function render(): string
    {
        $breadcrumbs = self::generate();
        if (empty($breadcrumbs)) {
            return '';
        }

        $html = '<nav class="flex" aria-label="Breadcrumb">';
        $html .= '<ol class="flex items-center space-x-2">';
        
        foreach ($breadcrumbs as $index => $breadcrumb) {
            if ($index > 0) {
                $html .= '<li class="flex items-center">';
                $html .= '<svg class="w-4 h-4 text-gray-400 mx-2" fill="currentColor" viewBox="0 0 20 20">';
                $html .= '<path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>';
                $html .= '</svg>';
                $html .= '</li>';
            }
            
            $html .= '<li class="flex items-center">';
            if ($breadcrumb['active']) {
                $html .= '<span class="text-gray-500 font-medium">' . $breadcrumb['title'] . '</span>';
            } else {
                $html .= '<a href="' . $breadcrumb['url'] . '" class="text-blue-600 hover:text-blue-800 font-medium">' . $breadcrumb['title'] . '</a>';
            }
            $html .= '</li>';
        }
        
        $html .= '</ol>';
        $html .= '</nav>';
        
        return $html;
    }
}