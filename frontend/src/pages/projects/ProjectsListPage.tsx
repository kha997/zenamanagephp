import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import toast from 'react-hot-toast';
import { 
  useProjects, 
  useDeleteProject,
  useCreateProject
} from '@/entities/app/projects/hooks';
import type { ProjectsFilters } from '@/entities/app/projects/types';
import { 
  MagnifyingGlassIcon,
  PlusIcon,
  PencilIcon,
  TrashIcon,
  EyeIcon,
  CalendarIcon,
  UserGroupIcon,
  FolderIcon
} from '@heroicons/react/24/outline';

export default function ProjectsListPage() {
  const [filters, setFilters] = useState<ProjectsFilters>({
    page: 1,
    per_page: 12
  });
  const [selectedProjects, setSelectedProjects] = useState<number[]>([]);
  const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
  const [createForm, setCreateForm] = useState({
    name: '',
    description: '',
    status: 'planning',
    priority: 'medium',
    start_date: new Date().toISOString().split('T')[0]
  });

  const { data: projectsResponse, isLoading, error } = useProjects(filters);
  const deleteProjectMutation = useDeleteProject();
  const createProjectMutation = useCreateProject();

  const projects = projectsResponse?.data || [];
  const meta = projectsResponse?.meta;

  const handleSearch = (search: string) => {
    setFilters(prev => ({ ...prev, search, page: 1 }));
  };

  const handleStatusFilter = (status: string) => {
    setFilters(prev => ({ 
      ...prev, 
      status: status === 'all' ? undefined : status,
      page: 1 
    }));
  };

  const handlePriorityFilter = (priority: string) => {
    setFilters(prev => ({
      ...prev,
      priority: priority === 'all' ? undefined : priority,
      page: 1 
    }));
  };

  const handleDeleteProject = async (projectId: number) => {
    if (window.confirm('Are you sure you want to delete this project?')) {
      try {
        await deleteProjectMutation.mutateAsync(projectId);
        toast.success('Project deleted successfully');
      } catch (error) {
        toast.error('Failed to delete project');
      }
    }
  };

  const handleSelectProject = (projectId: number) => {
    setSelectedProjects(prev => 
      prev.includes(projectId) 
        ? prev.filter(id => id !== projectId)
        : [...prev, projectId]
    );
  };

  const handleSelectAll = () => {
    if (selectedProjects.length === projects.length) {
      setSelectedProjects([]);
    } else {
      setSelectedProjects(projects.map(project => project.id));
    }
  };

  const handleCreateProject = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      await createProjectMutation.mutateAsync(createForm);
      toast.success('Project created successfully');
      setIsCreateModalOpen(false);
      setCreateForm({
        name: '',
        description: '',
        status: 'planning',
        priority: 'medium',
        start_date: new Date().toISOString().split('T')[0]
      });
    } catch (error) {
      toast.error('Failed to create project');
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
        <div>
            <h2 className="text-3xl font-bold text-gray-900">Projects</h2>
            <p className="text-gray-600">Manage your projects and track progress</p>
          </div>
        </div>
        <div className="flex items-center justify-center h-64">
          <div className="text-gray-500">Loading projects...</div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">Projects</h2>
            <p className="text-gray-600">Manage your projects and track progress</p>
          </div>
        </div>
        <Card>
          <CardContent className="text-center py-12">
            <div className="text-red-500">
              <h3 className="text-lg font-medium mb-2">Failed to load projects</h3>
              <p className="text-gray-600">There was an error loading the projects. Please try again.</p>
            </div>
          </CardContent>
        </Card>
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
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-3xl font-bold text-gray-900">Projects</h2>
          <p className="text-gray-600">Manage your projects and track progress</p>
        </div>
        <Dialog open={isCreateModalOpen} onOpenChange={setIsCreateModalOpen}>
          <DialogTrigger asChild>
            <Button>
              <PlusIcon className="h-4 w-4 mr-2" />
              New Project
            </Button>
          </DialogTrigger>
          <DialogContent className="sm:max-w-[425px]">
            <DialogHeader>
              <DialogTitle>Create New Project</DialogTitle>
            </DialogHeader>
            <form onSubmit={handleCreateProject} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="name">Project Name</Label>
                <Input
                  id="name"
                  value={createForm.name}
                  onChange={(e) => setCreateForm(prev => ({ ...prev, name: e.target.value }))}
                  placeholder="Enter project name"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                  id="description"
                  value={createForm.description}
                  onChange={(e) => setCreateForm(prev => ({ ...prev, description: e.target.value }))}
                  placeholder="Enter project description"
                  rows={3}
                />
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="status">Status</Label>
                  <Select
                    value={createForm.status}
                    onValueChange={(value) => setCreateForm(prev => ({ ...prev, status: value }))}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="planning">Planning</SelectItem>
                      <SelectItem value="active">Active</SelectItem>
                      <SelectItem value="on_hold">On Hold</SelectItem>
                      <SelectItem value="completed">Completed</SelectItem>
                      <SelectItem value="cancelled">Cancelled</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-2">
                  <Label htmlFor="priority">Priority</Label>
                  <Select
                    value={createForm.priority}
                    onValueChange={(value) => setCreateForm(prev => ({ ...prev, priority: value }))}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="low">Low</SelectItem>
                      <SelectItem value="medium">Medium</SelectItem>
                      <SelectItem value="high">High</SelectItem>
                      <SelectItem value="urgent">Urgent</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div className="space-y-2">
                <Label htmlFor="start_date">Start Date</Label>
                <Input
                  id="start_date"
                  type="date"
                  value={createForm.start_date}
                  onChange={(e) => setCreateForm(prev => ({ ...prev, start_date: e.target.value }))}
                  required
                />
              </div>
              <div className="flex justify-end space-x-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsCreateModalOpen(false)}
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  disabled={createProjectMutation.isPending}
                >
                  {createProjectMutation.isPending ? 'Creating...' : 'Create Project'}
                </Button>
              </div>
            </form>
          </DialogContent>
        </Dialog>
      </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle>Filters</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="text-sm font-medium text-gray-700">Search</label>
              <div className="relative">
                <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
              <Input
                  placeholder="Search projects..."
                  value={filters.search || ''}
                onChange={(e) => handleSearch(e.target.value)}
                  className="pl-10"
                />
              </div>
            </div>
            
            <div>
              <label className="text-sm font-medium text-gray-700">Status</label>
              <select
                value={filters.status || 'all'}
                onChange={(e) => handleStatusFilter(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="all">All Status</option>
                <option value="planning">Planning</option>
                <option value="active">Active</option>
                <option value="on_hold">On Hold</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
            
            <div>
              <label className="text-sm font-medium text-gray-700">Priority</label>
              <select
                value={filters.priority || 'all'}
                onChange={(e) => handlePriorityFilter(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="all">All Priorities</option>
                <option value="urgent">Urgent</option>
                <option value="high">High</option>
                <option value="medium">Medium</option>
                <option value="low">Low</option>
              </select>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Projects Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" role="grid" aria-label="Projects grid">
          {projects.map(project => (
            <Card key={project.id} className="hover:shadow-lg transition-shadow" role="gridcell">
              <CardHeader>
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <CardTitle className="text-lg">{project.name}</CardTitle>
                    <p className="text-sm text-gray-600 mt-1">{project.description}</p>
                  </div>
                  <div className="flex space-x-2">
                    <Badge variant={getStatusColor(project.status)} aria-label={`Project status: ${project.status}`}>
                      {project.status}
                    </Badge>
                    <Badge variant={getPriorityColor(project.priority)} aria-label={`Project priority: ${project.priority}`}>
                      {project.priority}
                    </Badge>
                  </div>
                </div>
              </CardHeader>
            <CardContent>
              <div className="space-y-3">
                {/* Progress Bar */}
                <div>
                  <div className="flex justify-between text-sm text-gray-600 mb-1">
                    <span>Progress</span>
                    <span aria-live="polite">{project.progress}%</span>
                  </div>
                  <div className="w-full bg-gray-200 rounded-full h-2" role="progressbar" aria-valuenow={project.progress} aria-valuemin={0} aria-valuemax={100} aria-label={`Project progress: ${project.progress}%`}>
                    <div 
                      className="bg-blue-600 h-2 rounded-full" 
                      style={{ width: `${project.progress}%` }}
                    ></div>
                  </div>
                </div>

                {/* Project Info */}
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div className="flex items-center space-x-2">
                    <CalendarIcon className="h-4 w-4 text-gray-400" />
                    <span className="text-gray-600">{new Date(project.start_date).toLocaleDateString()}</span>
                  </div>
                  <div className="flex items-center space-x-2">
                    <UserGroupIcon className="h-4 w-4 text-gray-400" />
                    <span className="text-gray-600">{project.team_members.length} members</span>
                  </div>
                </div>

                  {/* Actions */}
                  <div className="flex space-x-2 pt-2">
                    <Button variant="outline" size="sm" className="flex-1" aria-label={`View project ${project.name}`}>
                      <EyeIcon className="h-4 w-4 mr-1" />
                      View
                    </Button>
                    <Button variant="outline" size="sm" aria-label={`Edit project ${project.name}`}>
                      <PencilIcon className="h-4 w-4" />
                    </Button>
                    <Button 
                      variant="outline" 
                      size="sm" 
                      className="text-red-600"
                      onClick={() => handleDeleteProject(project.id)}
                      disabled={deleteProjectMutation.isPending}
                      aria-label={`Delete project ${project.name}`}
                    >
                      <TrashIcon className="h-4 w-4" />
                    </Button>
                  </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Pagination */}
      {meta && meta.last_page > 1 && (
        <div className="flex justify-between items-center">
          <div className="text-sm text-gray-600">
            Showing {((meta.current_page - 1) * meta.per_page) + 1} to {Math.min(meta.current_page * meta.per_page, meta.total)} of {meta.total} results
          </div>
          <div className="flex space-x-2">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setFilters(prev => ({ ...prev, page: prev.page! - 1 }))}
              disabled={meta.current_page <= 1}
            >
              Previous
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setFilters(prev => ({ ...prev, page: prev.page! + 1 }))}
              disabled={meta.current_page >= meta.last_page}
            >
              Next
            </Button>
          </div>
        </div>
      )}

      {projects.length === 0 && !isLoading && (
      <Card>
          <CardContent className="text-center py-12">
            <div className="text-gray-500">
              <FolderIcon className="h-12 w-12 mx-auto mb-4 text-gray-300" />
              <h3 className="text-lg font-medium mb-2">No projects found</h3>
              <p className="text-gray-600">Try adjusting your filters or create a new project.</p>
            </div>
        </CardContent>
      </Card>
      )}
    </div>
  );
}