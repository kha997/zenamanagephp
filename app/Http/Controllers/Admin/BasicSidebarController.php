<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class BasicSidebarController extends Controller
{
    /**
     * Display the sidebar builder interface.
     */
    public function index(): View
    {
        // No auth check for testing

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
        
        // Get existing configs from database
        try {
            $configs = \App\Models\SidebarConfig::where('is_enabled', true)
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

        return view('admin.simple-sidebar-builder', compact('roles', 'configs', 'groupedConfigs'));
    }

    /**
     * Show sidebar builder for a specific role.
     */
    public function show(string $role): View
    {
        // No auth check for testing

        // Get config from database or 
        
        if ($dbConfig) {
            $config = $dbConfig;
        } else {
            // Use default config from model
            $defaultConfig = \App\Models\SidebarConfig::getDefaultForRole($role);
            $config = (object) [
                'role_name' => $role,
                'config' => $defaultConfig,
                'is_enabled' => true,
            ];
        }

        return view('admin.sidebar-builder-edit', compact('config', 'role'));
    }

    /**
     * Preview sidebar for a specific role.
     */
    public function preview(string $role): View
    {
        // No auth check for testing

        // Get config from database or 
        
        if ($dbConfig) {
            $configData = $dbConfig->config;
        } else {
            // Use default config from model
            $configData = \App\Models\SidebarConfig::getDefaultForRole($role);
        }

        return view('admin.sidebar-preview', compact('role', 'configData'));
    }
}