import React from 'react';

export interface Project {
  id: string | number;
  name: string;
  description?: string;
  status?: string;
  progress?: number;
  owner?: {
    id: string | number;
    name: string;
  };
  start_date?: string;
  due_date?: string;
  budget?: number;
  spent?: number;
  created_at?: string;
  updated_at?: string;
}

interface ProjectListProps {
  projects?: Project[];
  loading?: boolean;
  error?: string | null;
}

const mockProjects: Project[] = [
  { id: 1, name: 'ZenaManage', description: 'Project management tool', status: 'active', progress: 75 },
  { id: 2, name: 'Customer Portal', description: 'Client-facing portal', status: 'planning', progress: 25 },
  { id: 3, name: 'API Integration', description: 'Third-party API integration', status: 'active', progress: 60 },
];

export const ProjectList: React.FC<ProjectListProps> = ({ 
  projects = mockProjects,
  loading = false,
  error = null
}) => {
  if (loading) {
    return (
      <div className="bg-white shadow rounded-lg p-8">
        <div className="text-center">
          <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
          <p className="mt-4 text-gray-600">Loading projects...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-white shadow rounded-lg p-8">
        <div className="text-center">
          <div className="text-red-500 text-2xl mb-4">⚠️</div>
          <h3 className="text-lg font-semibold text-gray-900 mb-2">Error loading projects</h3>
          <p className="text-sm text-gray-600">{error}</p>
        </div>
      </div>
    );
  }

  if (!projects || projects.length === 0) {
    return (
      <div className="bg-white shadow rounded-lg p-8">
        <div className="text-center">
          <div className="text-gray-400 text-4xl mb-4">📁</div>
          <h3 className="text-lg font-semibold text-gray-900 mb-2">No projects found</h3>
          <p className="text-sm text-gray-600">Get started by creating your first project.</p>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white shadow rounded-lg">
      <ul className="divide-y divide-gray-200">
        {projects.map((project) => (
          <li key={project.id} className="p-4 hover:bg-gray-50">
            <div className="flex items-center justify-between">
              <div className="flex-1">
                <h3 className="text-lg font-medium text-gray-900">{project.name}</h3>
                {project.description && (
                  <p className="text-sm text-gray-500 mt-1">{project.description}</p>
                )}
                <div className="flex items-center gap-4 mt-2">
                  {project.status && (
                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                      project.status === 'active' ? 'bg-green-100 text-green-800' :
                      project.status === 'planning' ? 'bg-yellow-100 text-yellow-800' :
                      project.status === 'completed' ? 'bg-blue-100 text-blue-800' :
                      'bg-gray-100 text-gray-800'
                    }`}>
                      {project.status}
                    </span>
                  )}
                  {project.progress !== undefined && (
                    <span className="text-sm text-gray-600">{project.progress}% complete</span>
                  )}
                </div>
              </div>
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
};
