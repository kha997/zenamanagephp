import { http, HttpResponse } from 'msw';
import type { DashboardStats, RecentProject, RecentTask, ActivityItem, DashboardAlert, AdminDashboardData } from '../../../frontend/src/features/dashboard/types';

/**
 * MSW handlers for Dashboard API endpoints
 * 
 * Routes:
 * - GET /api/v1/app/dashboard
 * - GET /api/v1/app/dashboard/stats
 * - GET /api/v1/app/dashboard/recent-projects
 * - GET /api/v1/app/dashboard/recent-tasks
 * - GET /api/v1/app/dashboard/recent-activity
 * - GET /api/v1/app/dashboard/alerts
 * - GET /api/v1/app/dashboard/metrics
 * - GET /api/v1/app/dashboard/team-status
 * - PUT /api/v1/app/dashboard/alerts/{id}/read
 * - PUT /api/v1/app/dashboard/alerts/read-all
 * - GET /api/admin/dashboard/summary
 */

const mockStats: DashboardStats = {
  projects: {
    total: 12,
    active: 5,
    completed: 7,
  },
  tasks: {
    total: 45,
    completed: 20,
    in_progress: 15,
    overdue: 3,
  },
  users: {
    total: 8,
    active: 6,
  },
};

const mockRecentProjects: RecentProject[] = [
  {
    id: '1',
    name: 'Project Alpha',
    status: 'active',
    progress: 75,
    updated_at: new Date().toISOString(),
    owner: {
      id: '1',
      name: 'John Doe',
    },
  },
  {
    id: '2',
    name: 'Project Beta',
    status: 'completed',
    progress: 100,
    updated_at: new Date(Date.now() - 86400000).toISOString(),
    owner: {
      id: '2',
      name: 'Jane Smith',
    },
  },
];

const mockRecentTasks: RecentTask[] = [
  {
    id: '1',
    name: 'Task 1',
    status: 'in_progress',
    project_name: 'Project Alpha',
    updated_at: new Date().toISOString(),
  },
  {
    id: '2',
    name: 'Task 2',
    status: 'completed',
    project_name: 'Project Beta',
    updated_at: new Date(Date.now() - 3600000).toISOString(),
  },
];

const mockActivities: ActivityItem[] = [
  {
    id: '1',
    type: 'project',
    action: 'updated',
    description: "Project 'Project Alpha' was updated",
    timestamp: new Date().toISOString(),
    user: {
      id: '1',
      name: 'John Doe',
    },
  },
  {
    id: '2',
    type: 'task',
    action: 'completed',
    description: "Task 'Task 2' in 'Project Beta' was completed",
    timestamp: new Date(Date.now() - 3600000).toISOString(),
    user: {
      id: '2',
      name: 'Jane Smith',
    },
  },
];

const mockAlerts: DashboardAlert[] = [
  {
    id: '1',
    type: 'warning',
    message: '3 tasks are overdue',
    created_at: new Date().toISOString(),
  },
];

const mockAdminDashboard: AdminDashboardData = {
  stats: {
    total_users: 50,
    total_projects: 100,
    total_tasks: 500,
    active_sessions: 12,
  },
  recent_activities: mockActivities,
  system_health: 'good',
};

export const dashboardHandlers = [
  // Main dashboard data
  http.get('/api/v1/app/dashboard', () => {
    return HttpResponse.json({
      success: true,
      data: {
        stats: mockStats,
        recent_projects: mockRecentProjects,
        recent_tasks: mockRecentTasks,
        recent_activity: mockActivities,
      },
    });
  }),

  // Dashboard stats/KPIs
  http.get('/api/v1/app/dashboard/stats', () => {
    return HttpResponse.json({
      success: true,
      data: mockStats,
    });
  }),

  // Recent projects
  http.get('/api/v1/app/dashboard/recent-projects', ({ request }) => {
    const url = new URL(request.url);
    const limit = parseInt(url.searchParams.get('limit') || '5', 10);
    return HttpResponse.json({
      success: true,
      data: mockRecentProjects.slice(0, limit),
    });
  }),

  // Recent tasks
  http.get('/api/v1/app/dashboard/recent-tasks', ({ request }) => {
    const url = new URL(request.url);
    const limit = parseInt(url.searchParams.get('limit') || '5', 10);
    return HttpResponse.json({
      success: true,
      data: mockRecentTasks.slice(0, limit),
    });
  }),

  // Recent activity
  http.get('/api/v1/app/dashboard/recent-activity', ({ request }) => {
    const url = new URL(request.url);
    const limit = parseInt(url.searchParams.get('limit') || '10', 10);
    return HttpResponse.json({
      success: true,
      data: mockActivities.slice(0, limit),
    });
  }),

  // Dashboard alerts
  http.get('/api/v1/app/dashboard/alerts', () => {
    // Return empty array if no alerts (matches backend behavior)
    return HttpResponse.json({
      success: true,
      data: mockAlerts.length > 0 ? mockAlerts : [],
    });
  }),

  // Dashboard metrics
  http.get('/api/v1/app/dashboard/metrics', () => {
    return HttpResponse.json({
      success: true,
      data: {
        project_progress: {},
        task_completion: {},
        team_performance: {},
      },
    });
  }),

  // Team status
  http.get('/api/v1/app/dashboard/team-status', () => {
    return HttpResponse.json({
      success: true,
      data: {
        total_members: 8,
        active_members: 6,
        members_by_role: {
          admin: 1,
          pm: 2,
          member: 5,
        },
      },
    });
  }),

  // Mark alert as read
  http.put('/api/v1/app/dashboard/alerts/:id/read', ({ params }) => {
    const { id } = params;
    if (!id) {
      return HttpResponse.json(
        {
          success: false,
          message: 'Alert ID is required',
        },
        { status: 400 }
      );
    }
    return HttpResponse.json({
      success: true,
      message: 'Alert marked as read',
      data: {
        id: id,
        read_at: new Date().toISOString(),
      },
    });
  }),

  // Mark all alerts as read
  http.put('/api/v1/app/dashboard/alerts/read-all', () => {
    return HttpResponse.json({
      success: true,
      message: 'All alerts marked as read',
      data: {
        marked_count: mockAlerts.length,
        marked_at: new Date().toISOString(),
      },
    });
  }),

  // Admin dashboard
  http.get('/api/admin/dashboard/summary', () => {
    return HttpResponse.json({
      success: true,
      data: mockAdminDashboard,
    });
  }),
];

