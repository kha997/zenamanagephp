<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController extends Controller
{
    /**
     * Display the team page
     */
    public function index(Request $request): View
    {
        // Get KPI data
        $kpis = [
            [
                'label' => 'Total Members',
                'value' => 8,
                'subtitle' => 'Team size',
                'icon' => 'fas fa-users',
                'gradient' => 'from-blue-500 to-blue-600',
                'action' => 'View All'
            ],
            [
                'label' => 'Active Members',
                'value' => 7,
                'subtitle' => 'Currently active',
                'icon' => 'fas fa-user-check',
                'gradient' => 'from-green-500 to-green-600',
                'action' => 'View Active'
            ],
            [
                'label' => 'Project Managers',
                'value' => 2,
                'subtitle' => 'PMs in team',
                'icon' => 'fas fa-user-tie',
                'gradient' => 'from-purple-500 to-purple-600',
                'action' => 'View PMs'
            ],
            [
                'label' => 'Team Members',
                'value' => 6,
                'subtitle' => 'Regular members',
                'icon' => 'fas fa-user-friends',
                'gradient' => 'from-orange-500 to-orange-600',
                'action' => 'View Members'
            ]
        ];
        
        // Get team members for the current tenant
        $teamMembers = collect([
            [
                'id' => 'user_001',
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'role' => 'pm',
                'status' => 'active',
                'avatar' => null,
                'last_active' => '2025-10-06T14:00:00Z',
                'projects_count' => 3,
                'tasks_count' => 12,
                'joined_at' => '2025-09-06T14:00:00Z',
            ],
            [
                'id' => 'user_002',
                'name' => 'Jane Smith',
                'email' => 'jane.smith@example.com',
                'role' => 'member',
                'status' => 'active',
                'avatar' => null,
                'last_active' => '2025-10-06T12:00:00Z',
                'projects_count' => 2,
                'tasks_count' => 8,
                'joined_at' => '2025-09-21T14:00:00Z',
            ],
            [
                'id' => 'user_003',
                'name' => 'Bob Johnson',
                'email' => 'bob.johnson@example.com',
                'role' => 'member',
                'status' => 'inactive',
                'avatar' => null,
                'last_active' => '2025-10-03T14:00:00Z',
                'projects_count' => 1,
                'tasks_count' => 4,
                'joined_at' => '2025-08-22T14:00:00Z',
            ],
        ]);

        // Get team statistics
        $teamStats = [
            'total_members' => $teamMembers->count(),
            'active_members' => $teamMembers->where('status', 'active')->count(),
            'pending_invitations' => 2,
            'roles_distribution' => [
                'pm' => 1,
                'member' => 2,
                'client' => 0,
            ],
        ];

        // Get recent team activities
        $recentActivities = [
            [
                'id' => 'activity_001',
                'type' => 'member_joined',
                'description' => 'Jane Smith joined the team',
                'user_id' => 'user_002',
                'timestamp' => '2025-09-21T14:00:00Z',
            ],
            [
                'id' => 'activity_002',
                'type' => 'role_changed',
                'description' => 'John Doe promoted to Project Manager',
                'user_id' => 'user_001',
                'timestamp' => '2025-09-26T14:00:00Z',
            ],
            [
                'id' => 'activity_003',
                'type' => 'task_assigned',
                'description' => 'New task assigned to Bob Johnson',
                'user_id' => 'user_003',
                'timestamp' => '2025-10-01T14:00:00Z',
            ],
        ];

        return view('app.team.index', compact('kpis', 'teamMembers', 'teamStats', 'recentActivities'));
    }

    /**
     * Show team member details
     */
    public function show(Request $request, string $id): View
    {
        $member = [
            'id' => $id,
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'role' => 'pm',
            'status' => 'active',
            'avatar' => null,
            'last_active' => now()->subMinutes(15)->toISOString(),
            'joined_at' => now()->subDays(30)->toISOString(),
            'bio' => 'Experienced project manager with 10+ years in construction management.',
            'skills' => ['Project Management', 'Construction', 'Team Leadership', 'Budget Planning'],
            'projects' => [
                ['id' => 'project_001', 'name' => 'Office Building', 'role' => 'Project Manager'],
                ['id' => 'project_002', 'name' => 'Warehouse Construction', 'role' => 'Project Manager'],
            ],
            'tasks' => [
                ['id' => 'task_001', 'title' => 'Project Planning', 'status' => 'completed'],
                ['id' => 'task_002', 'title' => 'Team Coordination', 'status' => 'in_progress'],
                ['id' => 'task_003', 'title' => 'Budget Review', 'status' => 'pending'],
            ],
            'performance' => [
                'tasks_completed' => 45,
                'tasks_on_time' => 42,
                'tasks_overdue' => 3,
                'team_rating' => 4.8,
            ],
        ];

        return view('app.team.show', compact('member'));
    }

    /**
     * Invite a new team member
     */
    public function invite(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:pm,member,client',
            'message' => 'nullable|string|max:500',
        ]);

        // In a real application, you would send an invitation email
        $invitation = [
            'id' => 'invitation_' . uniqid(),
            'email' => $validated['email'],
            'role' => $validated['role'],
            'message' => $validated['message'],
            'invited_by' => $request->user()?->id ?? 'current_user',
            'invited_at' => now()->toISOString(),
            'expires_at' => now()->addDays(7)->toISOString(),
        ];

        return redirect()->route('app.team.index')
            ->with('success', 'Invitation sent successfully');
    }

    /**
     * Update team member role
     */
    public function updateRole(Request $request, string $id)
    {
        $validated = $request->validate([
            'role' => 'required|in:pm,member,client',
        ]);

        // In a real application, you would update the role in the database
        return redirect()->route('app.team.show', $id)
            ->with('success', 'Role updated successfully');
    }

    /**
     * Remove team member
     */
    public function remove(Request $request, string $id)
    {
        // In a real application, you would remove the member from the team
        return redirect()->route('app.team.index')
            ->with('success', 'Team member removed successfully');
    }

    /**
     * Get team analytics
     */
    public function analytics(Request $request): View
    {
        $analytics = [
            'productivity' => [
                'avg_tasks_per_member' => 8.5,
                'completion_rate' => 87.5,
                'on_time_delivery' => 92.3,
            ],
            'collaboration' => [
                'avg_collaborations_per_day' => 15.2,
                'cross_project_work' => 34.5,
                'knowledge_sharing_score' => 8.7,
            ],
            'engagement' => [
                'active_members_percentage' => 85.7,
                'avg_session_duration' => '2.5 hours',
                'satisfaction_score' => 4.6,
            ],
            'trends' => [
                'members_growth' => 12.5,
                'productivity_trend' => 'increasing',
                'collaboration_trend' => 'stable',
            ],
        ];

        return view('app.team.analytics', compact('analytics'));
    }
}
