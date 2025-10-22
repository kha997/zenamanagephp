import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import toast from 'react-hot-toast';
import { 
  useProject, 
  useDeleteProject 
} from '@/entities/app/projects/hooks';
import { 
  useDocuments 
} from '@/entities/app/documents/hooks';
import { 
  ArrowLeftIcon,
  PencilIcon,
  CalendarIcon,
  UserGroupIcon,
  ChartBarIcon,
  DocumentTextIcon,
  PlusIcon,
  TrashIcon
} from '@heroicons/react/24/outline';

export default function ProjectDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const projectId = id ? parseInt(id) : 0;

  const { data: projectResponse, isLoading, error } = useProject(projectId);
  const { data: documentsResponse } = useDocuments({ project_id: projectId, per_page: 5 });
  const deleteProjectMutation = useDeleteProject();

  const project = projectResponse?.data;
  const documents = documentsResponse?.data || [];

  const handleDeleteProject = async () => {
    if (window.confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
      try {
        await deleteProjectMutation.mutateAsync(projectId);
        toast.success('Project deleted successfully');
        navigate('/app/projects');
      } catch (error) {
        toast.error('Failed to delete project');
      }
    }
  };

  const handleEditProject = () => {
    // TODO: Navigate to edit project page or open modal
    toast('Edit project functionality coming soon');
  };

  const handleAddTask = () => {
    // TODO: Navigate to add task page or open modal
    toast('Add task functionality coming soon');
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-center h-64">
          <div className="text-gray-500">Loading project...</div>
        </div>
      </div>
    );
  }

  if (error || !project) {
    return (
      <div className="space-y-6">
        <div className="flex items-center justify-center h-64">
          <div className="text-red-500">
            <h3 className="text-lg font-medium mb-2">Failed to load project</h3>
            <p className="text-gray-600">There was an error loading the project. Please try again.</p>
          </div>
        </div>
      </div>
    );
  }

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'active':
        return 'default';
      case 'planning':
        return 'secondary';
      case 'on_hold':
        return 'destructive';
      case 'completed':
        return 'outline';
      case 'cancelled':
        return 'destructive';
      default:
        return 'secondary';
    }
  };

  const getTaskStatusColor = (status: string) => {
    switch (status) {
      case 'done':
        return 'default';
      case 'in_progress':
        return 'secondary';
      case 'todo':
        return 'outline';
      default:
        return 'secondary';
    }
  };

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'urgent':
        return 'destructive';
      case 'high':
        return 'default';
      case 'medium':
        return 'secondary';
      case 'low':
        return 'outline';
      default:
        return 'secondary';
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center space-x-4">
          <Button variant="ghost" size="sm" onClick={() => navigate('/app/projects')}>
            <ArrowLeftIcon className="h-4 w-4 mr-2" />
            Back to Projects
          </Button>
          <div>
            <h2 className="text-3xl font-bold text-gray-900">{project.name}</h2>
            <p className="text-gray-600">{project.description}</p>
          </div>
        </div>
        <div className="flex space-x-2">
          <Button variant="outline" onClick={handleEditProject}>
            <PencilIcon className="h-4 w-4 mr-2" />
            Edit Project
          </Button>
          <Button onClick={handleAddTask}>
            <PlusIcon className="h-4 w-4 mr-2" />
            Add Task
          </Button>
          <Button 
            variant="outline" 
            className="text-red-600"
            onClick={handleDeleteProject}
            disabled={deleteProjectMutation.isPending}
          >
            <TrashIcon className="h-4 w-4 mr-2" />
            Delete
          </Button>
        </div>
      </div>

      {/* Project Stats */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Status</CardTitle>
            <ChartBarIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <Badge variant={getStatusColor(project.status)} className="text-sm">
              {project.status}
            </Badge>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Progress</CardTitle>
            <ChartBarIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{project.progress}%</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Team Size</CardTitle>
            <UserGroupIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{project.team_members.length}</div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Budget</CardTitle>
            <ChartBarIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">${(project.spent || 0).toLocaleString()}</div>
            <p className="text-xs text-muted-foreground">
              of ${(project.budget || 0).toLocaleString()}
            </p>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Project Details */}
        <div className="lg:col-span-2 space-y-6">
          {/* Tasks */}
          <Card>
            <CardHeader>
              <CardTitle>Tasks</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {tasks.map(task => (
                  <div key={task.id} className="flex items-center justify-between p-4 border rounded-lg">
                    <div className="flex-1">
                      <h4 className="font-medium">{task.title}</h4>
                      <p className="text-sm text-gray-600">{task.description}</p>
                      <div className="flex items-center space-x-4 mt-2">
                        <Badge variant={getTaskStatusColor(task.status)}>
                          {task.status}
                        </Badge>
                        <Badge variant={getPriorityColor(task.priority)}>
                          {task.priority}
                        </Badge>
                        <span className="text-sm text-gray-500">{task.assignee}</span>
                      </div>
                    </div>
                    <div className="text-right">
                      <p className="text-sm text-gray-600">{task.dueDate}</p>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Project Info */}
          <Card>
            <CardHeader>
              <CardTitle>Project Information</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center space-x-2">
                <CalendarIcon className="h-4 w-4 text-gray-400" />
                <div>
                  <p className="text-sm font-medium">Start Date</p>
                  <p className="text-sm text-gray-600">{new Date(project.start_date).toLocaleDateString()}</p>
                </div>
              </div>
              <div className="flex items-center space-x-2">
                <CalendarIcon className="h-4 w-4 text-gray-400" />
                <div>
                  <p className="text-sm font-medium">End Date</p>
                  <p className="text-sm text-gray-600">{new Date(project.end_date).toLocaleDateString()}</p>
                </div>
              </div>
              <div className="flex items-center space-x-2">
                <UserGroupIcon className="h-4 w-4 text-gray-400" />
                <div>
                  <p className="text-sm font-medium">Team Size</p>
                  <p className="text-sm text-gray-600">{project.team_members.length} members</p>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Recent Documents */}
          <Card>
            <CardHeader>
              <CardTitle>Recent Documents</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {documents.length > 0 ? (
                  documents.map(doc => (
                    <div key={doc.id} className="flex items-center space-x-3">
                      <DocumentTextIcon className="h-5 w-5 text-gray-400" />
                      <div className="flex-1">
                        <p className="text-sm font-medium">{doc.name}</p>
                        <p className="text-xs text-gray-500">
                          Updated {new Date(doc.updated_at).toLocaleDateString()}
                        </p>
                      </div>
                    </div>
                  ))
                ) : (
                  <div className="text-center text-gray-500 py-4">
                    <DocumentTextIcon className="h-8 w-8 mx-auto mb-2 text-gray-300" />
                    <p className="text-sm">No documents yet</p>
                  </div>
                )}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}