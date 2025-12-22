<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(Request $request): View
    {
        // Admin-specific data
        $totalUsers = \App\Models\User::count();
        $activeTenants = \App\Models\Tenant::where('is_active', true)->count();
        $totalProjects = \App\Models\Project::count();
        $activeAlerts = 0; // TODO: Calculate from actual alerts
        $activeSessions = 0; // TODO: Calculate from actual sessions
        
        // Recent activities (mock data for now)
        $recentActivities = collect([
            [
                'id' => '1',
                'type' => 'user',
                'title' => 'New user registered',
                'description' => 'User john@example.com registered',
                'timestamp' => now()->subMinutes(2),
                'user' => 'System',
                'tenant' => 'Acme Corp',
                'severity' => 'info'
            ],
            [
                'id' => '2',
                'type' => 'tenant',
                'title' => 'New tenant created',
                'description' => 'Tenant "TechStart Inc" created',
                'timestamp' => now()->subMinutes(5),
                'user' => 'Admin',
                'tenant' => 'TechStart Inc',
                'severity' => 'info'
            ],
            [
                'id' => '3',
                'type' => 'security',
                'title' => 'Security alert resolved',
                'description' => 'Failed login attempt blocked',
                'timestamp' => now()->subMinutes(10),
                'user' => 'Security System',
                'tenant' => 'N/A',
                'severity' => 'warning'
            ],
            [
                'id' => '4',
                'type' => 'system',
                'title' => 'System backup completed',
                'description' => 'Daily backup completed successfully',
                'timestamp' => now()->subMinutes(15),
                'user' => 'Backup System',
                'tenant' => 'N/A',
                'severity' => 'info'
            ],
            [
                'id' => '5',
                'type' => 'project',
                'title' => 'Project milestone reached',
                'description' => 'Project "Website Redesign" reached 50% completion',
                'timestamp' => now()->subMinutes(20),
                'user' => 'Project Manager',
                'tenant' => 'Acme Corp',
                'severity' => 'info'
            ]
        ]);
        
        return view('admin.dashboard', compact(
            'totalUsers',
            'activeTenants', 
            'totalProjects',
            'activeAlerts',
            'activeSessions',
            'recentActivities'
        ));
    }
}