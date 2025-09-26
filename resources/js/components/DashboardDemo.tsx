import React from 'react';
import { useAdminDashboard, useAppDashboard } from '../hooks';

const AdminDashboardDemo: React.FC = () => {
  const { stats, loading, error, refetch } = useAdminDashboard();

  if (loading) {
    return (
      <div className="flex justify-center items-center py-8">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span className="ml-2 text-gray-600">Loading admin dashboard...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <div className="flex">
          <div className="py-1">
            <i className="fas fa-exclamation-circle"></i>
          </div>
          <div className="ml-3">
            <p className="font-bold">Error loading dashboard</p>
            <p>{error}</p>
            <button 
              onClick={refetch}
              className="mt-2 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600"
            >
              Retry
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <div className="bg-white p-6 rounded-lg shadow">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-medium text-gray-500">Total Users</p>
            <p className="text-3xl font-bold text-gray-900">{stats.totalUsers || 0}</p>
          </div>
          <i className="fas fa-users text-4xl text-blue-400"></i>
        </div>
      </div>
      
      <div className="bg-white p-6 rounded-lg shadow">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-medium text-gray-500">Active Projects</p>
            <p className="text-3xl font-bold text-gray-900">{stats.activeProjects || 0}</p>
          </div>
          <i className="fas fa-project-diagram text-4xl text-green-400"></i>
        </div>
      </div>
      
      <div className="bg-white p-6 rounded-lg shadow">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-medium text-gray-500">Completed Tasks</p>
            <p className="text-3xl font-bold text-gray-900">{stats.completedTasks || 0}</p>
          </div>
          <i className="fas fa-check-circle text-4xl text-yellow-400"></i>
        </div>
      </div>
      
      <div className="bg-white p-6 rounded-lg shadow">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-medium text-gray-500">System Health</p>
            <p className="text-3xl font-bold text-gray-900">{stats.systemHealth || 'Good'}</p>
          </div>
          <i className="fas fa-heartbeat text-4xl text-red-400"></i>
        </div>
      </div>
    </div>
  );
};

const AppDashboardDemo: React.FC = () => {
  const { stats, loading, error, refetch } = useAppDashboard();

  if (loading) {
    return (
      <div className="flex justify-center items-center py-8">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
        <span className="ml-2 text-gray-600">Loading app dashboard...</span>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        <div className="flex">
          <div className="py-1">
            <i className="fas fa-exclamation-circle"></i>
          </div>
          <div className="ml-3">
            <p className="font-bold">Error loading dashboard</p>
            <p>{error}</p>
            <button 
              onClick={refetch}
              className="mt-2 bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600"
            >
              Retry
            </button>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      <div className="bg-white p-6 rounded-lg shadow">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-medium text-gray-500">Active Tasks</p>
            <p className="text-3xl font-bold text-gray-900">{stats.totalTasks || 0}</p>
          </div>
          <i className="fas fa-tasks text-4xl text-blue-400"></i>
        </div>
      </div>
      
      <div className="bg-white p-6 rounded-lg shadow">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-medium text-gray-500">Completed Today</p>
            <p className="text-3xl font-bold text-gray-900">{stats.completedTasks || 0}</p>
          </div>
          <i className="fas fa-check-circle text-4xl text-green-400"></i>
        </div>
      </div>
      
      <div className="bg-white p-6 rounded-lg shadow">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-medium text-gray-500">Team Members</p>
            <p className="text-3xl font-bold text-gray-900">{stats.teamMembers || 0}</p>
          </div>
          <i className="fas fa-users text-4xl text-yellow-400"></i>
        </div>
      </div>
      
      <div className="bg-white p-6 rounded-lg shadow">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-medium text-gray-500">Projects</p>
            <p className="text-3xl font-bold text-gray-900">{stats.totalProjects || 0}</p>
          </div>
          <i className="fas fa-project-diagram text-4xl text-red-400"></i>
        </div>
      </div>
    </div>
  );
};

export { AdminDashboardDemo, AppDashboardDemo };
