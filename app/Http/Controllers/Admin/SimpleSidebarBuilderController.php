<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Auth;


use App\Http\Controllers\Controller;
use App\Models\SidebarConfig;
use Illuminate\View\View;

class SimpleSidebarBuilderController extends Controller
{
    /**
     * Display the sidebar builder interface.
     */
    public function index(): View
    {
        // Simple authorization check
        if (!Auth::check()) {
            abort(403, 'Unauthorized');
        }

        // Get all available roles
        $roles = [
            'super_admin',
            'admin', 
            'project_manager',
            'designer',
            'site_engineer',
            'qc_engineer',
            'procurement',
            'finance',
            'client',
        ];
        
        // Get existing configs
        try {
            $configs = SidebarConfig::where('is_enabled', true)
                ->orderBy('role_name')
                ->get();
        } catch (\Exception $e) {
            $configs = collect();
        }

        // Group configs by role_name safely
        $groupedConfigs = [];
        foreach ($configs as $config) {
            $groupedConfigs[$config->role_name][] = $config;
        }

        return view('admin.sidebar-builder', compact('roles', 'configs', 'groupedConfigs'));
    }

    /**
     * Show sidebar builder for a specific role.
     */
    public function show(string $role): View
    {
        // Simple authorization check
        if (!Auth::check()) {
            abort(403, 'Unauthorized');
        }

        // Get config for role (or default)
        $config = SidebarConfig::where('role_name', $role)
            ->where('is_enabled', true)
            ->first();
        
        if (!$config) {
            // Return default config
            $config = new SidebarConfig([
                'role_name' => $role,
                'config' => $this->getDefaultConfigForRole($role),
                'is_enabled' => true,
            ]);
        }

        return view('admin.sidebar-builder-edit', compact('config', 'role'));
    }

    /**
     * Preview sidebar for a specific role.
     */
    public function preview(string $role): View
    {
        // Simple authorization check
        if (!Auth::check()) {
            abort(403, 'Unauthorized');
        }

        return view('admin.sidebar-preview', compact('role'));
    }

    /**
     * Get default config for role.
     */
    protected function getDefaultConfigForRole(string $role): array
    {
        $defaultConfigs = [
            'super_admin' => [
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
                        'id' => 'admin',
                        'type' => 'group',
                        'label' => 'Administration',
                        'icon' => 'Cog',
                        'enabled' => true,
                        'order' => 20,
                        'children' => [
                            [
                                'id' => 'users',
                                'type' => 'link',
                                'label' => 'Users',
                                'icon' => 'Users',
                                'to' => '/admin/users',
                                'required_permissions' => ['user.manage'],
                                'enabled' => true,
                                'order' => 10,
                            ],
                            [
                                'id' => 'roles',
                                'type' => 'link',
                                'label' => 'Roles',
                                'icon' => 'Shield',
                                'to' => '/admin/roles',
                                'required_permissions' => ['role.manage'],
                                'enabled' => true,
                                'order' => 20,
                            ],
                        ],
                    ],
                ],
            ],
            'project_manager' => [
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
                        'icon' => 'Tasks',
                        'to' => '/tasks',
                        'required_permissions' => ['task.read'],
                        'enabled' => true,
                        'order' => 30,
                    ],
                ],
            ],
        ];

        return $defaultConfigs[$role] ?? [
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
}