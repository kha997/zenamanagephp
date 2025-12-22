import React, { useState, useEffect } from 'react';
import KPIWidget, { KPIData } from '../components/dashboard/KPIWidget';
import ChartWidget, { ChartData } from '../components/dashboard/ChartWidget';
import ActivityList, { ActivityItem } from '../components/dashboard/ActivityList';

interface DashboardData {
  kpis: KPIData[];
  charts: ChartData[];
  activities: ActivityItem[];
  recentProjects: Array<{
    id: string;
    name: string;
    status: string;
    progress: number;
    owner: {
      name: string;
    };
  }>;
}

// Constants
const API_HEADERS = {
  'Accept': 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
};

// Helper functions to reduce complexity
const getChangeType = (change: number): 'increase' | 'decrease' | 'neutral' => {
  if (change > 0) return 'increase';
  if (change < 0) return 'decrease';
  return 'neutral';
};

const transformKPIData = (kpisData: any): KPIData[] => {
  return [
    {
      id: 'projects',
      title: 'Total Projects',
      value: kpisData.data.projects.total,
      change: kpisData.data.projects.change,
      changeType: getChangeType(kpisData.data.projects.change),
      icon: 'fas fa-project-diagram',
      color: 'blue'
    },
    {
      id: 'users',
      title: 'Active Users',
      value: kpisData.data.users.active,
      change: kpisData.data.users.change,
      changeType: getChangeType(kpisData.data.users.change),
      icon: 'fas fa-users',
      color: 'green'
    },
    {
      id: 'progress',
      title: 'Average Progress',
      value: `${kpisData.data.progress.overall}%`,
      change: kpisData.data.progress.change,
      changeType: getChangeType(kpisData.data.progress.change),
      icon: 'fas fa-chart-line',
      color: 'purple'
    },
    {
      id: 'budget',
      title: 'Budget Utilization',
      value: `${kpisData.data.budget.utilization}%`,
      change: kpisData.data.budget.change,
      changeType: getChangeType(kpisData.data.budget.change),
      icon: 'fas fa-dollar-sign',
      color: 'yellow'
    }
  ];
};

const Dashboard: React.FC = () => {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchDashboardData = async () => {
    try {
      setLoading(true);
      setError(null);

      const [kpisResponse, chartsResponse, activitiesResponse, projectsResponse] = await Promise.all([
        fetch('/api/dashboard/kpis', {
          headers: API_HEADERS
        }),
        fetch('/api/dashboard/charts', {
          headers: API_HEADERS
        }),
        fetch('/api/dashboard/recent-activity', {
          headers: API_HEADERS
        }),
        fetch('/api/projects?limit=5&sort=updated_at&order=desc', {
          headers: API_HEADERS
        })
      ]);

      if (!kpisResponse.ok || !chartsResponse.ok || !activitiesResponse.ok || !projectsResponse.ok) {
        throw new Error('Failed to fetch dashboard data');
      }

      const [kpisData, chartsData, activitiesData, projectsData] = await Promise.all([
        kpisResponse.json(),
        chartsResponse.json(),
        activitiesResponse.json(),
        projectsResponse.json()
      ]);

      // Transform KPIs data
      const kpis = transformKPIData(kpisData);

      // Transform charts data
      const charts: ChartData[] = [
        {
          id: 'project-progress',
          type: 'doughnut',
          title: 'Project Progress',
          data: chartsData.data.project_progress
        },
        {
          id: 'task-distribution',
          type: 'line',
          title: 'Average Progress %',
          data: chartsData.data.task_distribution
        }
      ];

      // Transform activities data
      const activities: ActivityItem[] = activitiesData.data.map((activity: any) => ({
        id: activity.id,
        type: activity.type,
        action: activity.action,
        description: activity.description,
        timestamp: activity.timestamp,
        user: activity.user,
        url: activity.url
      }));

      // Transform projects data
      const recentProjects = projectsData.data || [];

      setData({
        kpis,
        charts,
        activities,
        recentProjects
      });
    } catch (err) {
      console.error('Failed to fetch dashboard data:', err);
      setError(err instanceof Error ? err.message : 'Unknown error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const handleRefresh = () => {
    fetchDashboardData();
  };

  const handleLoadMoreActivity = () => {
    // Implement load more activity
    console.log('Load more activity');
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <KPIWidget data={[]} loading={true} />
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <div className="bg-white shadow-sm rounded-lg border border-gray-200">
              <div className="px-6 py-4 border-b border-gray-200">
                <div className="h-6 bg-gray-200 rounded animate-pulse"></div>
              </div>
              <div className="p-6">
                <div className="space-y-4">
                  {Array.from({ length: 5 }).map((_, index) => (
                    <div key={index} className="flex items-center space-x-4">
                      <div className="w-10 h-10 bg-gray-200 rounded-lg animate-pulse"></div>
                      <div className="flex-1">
                        <div className="h-4 bg-gray-200 rounded animate-pulse mb-2"></div>
                        <div className="h-3 bg-gray-200 rounded animate-pulse w-1/2"></div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
            <ActivityList data={[]} loading={true} />
          </div>
          <ChartWidget data={[]} loading={true} />
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div className="bg-red-50 border border-red-200 rounded-lg p-6">
            <div className="flex items-center">
              <div className="flex-shrink-0">
                <i className="fas fa-exclamation-triangle text-red-400 text-xl"></i>
              </div>
              <div className="ml-3">
                <h3 className="text-sm font-medium text-red-800">Failed to load dashboard</h3>
                <p className="text-sm text-red-700 mt-1">{error}</p>
                <button
                  onClick={handleRefresh}
                  className="mt-2 text-sm text-red-600 hover:text-red-500 font-medium"
                >
                  Try again
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      {/* Page Header */}
      <div className="bg-white shadow-sm border-b border-gray-200">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
              <p className="mt-1 text-sm text-gray-600">
                Welcome back! Here's what's happening with your projects.
              </p>
            </div>
            <div className="flex items-center space-x-3">
              <button
                onClick={handleRefresh}
                className="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                <i className="fas fa-sync-alt mr-2"></i>
                Refresh
              </button>
              <a
                href="/app/projects/create"
                className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
              >
                <i className="fas fa-plus mr-2"></i>
                New Project
              </a>
            </div>
          </div>
        </div>
      </div>

      {/* Main Content */}
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* KPI Cards */}
        <KPIWidget data={data?.kpis || []} />

        {/* Main Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
          {/* Recent Projects */}
          <div className="bg-white shadow-sm rounded-lg border border-gray-200">
            <div className="px-6 py-4 border-b border-gray-200">
              <div className="flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-900">Recent Projects</h2>
                <a
                  href="/app/projects"
                  className="text-sm text-blue-600 hover:text-blue-500 font-medium"
                >
                  View all
                </a>
              </div>
            </div>
            <div className="p-6">
              {data?.recentProjects.length === 0 ? (
                <div className="text-center py-8">
                  <div className="w-16 h-16 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                    <i className="fas fa-project-diagram text-2xl text-gray-400"></i>
                  </div>
                  <h3 className="text-lg font-medium text-gray-900 mb-2">No projects yet</h3>
                  <p className="text-gray-500 mb-4">Get started by creating your first project.</p>
                  <a
                    href="/app/projects/create"
                    className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700"
                  >
                    <i className="fas fa-plus mr-2"></i>
                    Create First Project
                  </a>
                </div>
              ) : (
                <div className="space-y-4">
                  {data?.recentProjects.map((project) => (
                    <div
                      key={project.id}
                      className="flex items-center space-x-4 p-3 hover:bg-gray-50 rounded-lg transition-colors"
                    >
                      <div className="flex-shrink-0">
                        <div className="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                          <i className="fas fa-project-diagram text-blue-600"></i>
                        </div>
                      </div>
                      <div className="flex-1 min-w-0">
                        <h3 className="text-sm font-medium text-gray-900 truncate">{project.name}</h3>
                        <p className="text-sm text-gray-500">{project.owner?.name || 'No owner'}</p>
                      </div>
                      <div className="flex-shrink-0">
                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                          project.status === 'active' ? 'bg-green-100 text-green-800' :
                          project.status === 'completed' ? 'bg-blue-100 text-blue-800' :
                          project.status === 'on_hold' ? 'bg-red-100 text-red-800' :
                          'bg-gray-100 text-gray-800'
                        }`}>
                          {project.status}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Recent Activity */}
          <ActivityList
            data={data?.activities || []}
            onLoadMore={handleLoadMoreActivity}
            showLoadMore={true}
          />
        </div>

        {/* Charts Section */}
        <ChartWidget data={data?.charts || []} />
      </main>
    </div>
  );
};

export default Dashboard;
