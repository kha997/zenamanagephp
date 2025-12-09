import React, { useState } from 'react';
import { useTemplates, useCreateTemplate, useDeleteTemplate } from '../features/templates/hooks';
import type { TemplateFilters } from '../features/templates/api';
import { Button } from '../components/ui/Button';
import { Input } from '../components/ui/Input';
import { Select } from '../components/ui/Select';
import { Card } from '../components/ui/Card';
import { Badge } from '../components/ui/Badge';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { Plus, Search, X, Edit, Trash2, FolderPlus, ListChecks } from 'lucide-react';
import { TemplateCreateDialog } from '../features/templates/components/TemplateCreateDialog';
import { TemplateEditDialog } from '../features/templates/components/TemplateEditDialog';
import { CreateProjectFromTemplateDialog } from '../features/templates/components/CreateProjectFromTemplateDialog';
import { TaskTemplateList } from '../features/templates/components/TaskTemplateList';

/**
 * TemplatesPage
 * 
 * Round 192: Templates Vertical MVP
 * 
 * Displays a list of templates with filters and ability to create new templates
 */
const TemplatesPage: React.FC = () => {
  const [filters, setFilters] = useState<TemplateFilters>({});
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [showCreateDialog, setShowCreateDialog] = useState(false);
  const [editingTemplateId, setEditingTemplateId] = useState<string | number | null>(null);
  const [creatingProjectFromTemplate, setCreatingProjectFromTemplate] = useState<Template | null>(null);
  const [managingTaskTemplates, setManagingTaskTemplates] = useState<Template | null>(null);

  // Fetch templates using React Query
  const { data, isLoading, error } = useTemplates(filters, { page, per_page: 15 });
  const createTemplate = useCreateTemplate();
  const deleteTemplate = useDeleteTemplate();

  const templates = data?.data || [];
  const pagination = data?.pagination;

  const handleSearch = (value: string) => {
    setSearch(value);
    setFilters(prev => ({ ...prev, search: value || undefined }));
    setPage(1);
  };

  const handleTypeFilter = (type: string) => {
    setFilters(prev => ({
      ...prev,
      type: type ? (type as 'project' | 'task' | 'document' | 'checklist') : undefined,
    }));
    setPage(1);
  };

  const handleStatusFilter = (isActive: string) => {
    setFilters(prev => ({
      ...prev,
      is_active: isActive ? isActive === 'true' : undefined,
    }));
    setPage(1);
  };

  const handleDelete = async (id: string | number) => {
    if (window.confirm('Are you sure you want to delete this template?')) {
      try {
        await deleteTemplate.mutateAsync(id);
      } catch (error) {
        console.error('Failed to delete template:', error);
      }
    }
  };

  const getTypeLabel = (type?: string) => {
    const typeMap: Record<string, string> = {
      project: 'Project',
      task: 'Task',
      document: 'Document',
      checklist: 'Checklist',
    };
    return typeMap[type || ''] || type || 'General';
  };

  const getTypeColor = (type?: string) => {
    const colorMap: Record<string, string> = {
      project: 'blue',
      task: 'green',
      document: 'purple',
      checklist: 'orange',
    };
    return colorMap[type || ''] || 'gray';
  };

  return (
    <div className="space-y-6 p-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Templates</h1>
          <p className="text-gray-600">Quản lý các mẫu dự án / công việc / checklist</p>
        </div>
        <Button onClick={() => setShowCreateDialog(true)}>
          <Plus className="w-4 h-4 mr-2" />
          New Template
        </Button>
      </div>

      {/* Filters */}
      <Card className="p-4">
        <div className="flex flex-wrap gap-4">
          <div className="flex-1 min-w-[200px]">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
              <Input
                placeholder="Search templates..."
                value={search}
                onChange={(e) => handleSearch(e.target.value)}
                className="pl-10"
              />
            </div>
          </div>

          <Select
            value={filters.type || ''}
            onChange={(e) => handleTypeFilter(e.target.value)}
            className="min-w-[150px]"
          >
            <option value="">All Types</option>
            <option value="project">Project</option>
            <option value="task">Task</option>
            <option value="document">Document</option>
            <option value="checklist">Checklist</option>
          </Select>

          <Select
            value={filters.is_active !== undefined ? String(filters.is_active) : ''}
            onChange={(e) => handleStatusFilter(e.target.value)}
            className="min-w-[150px]"
          >
            <option value="">All Status</option>
            <option value="true">Active</option>
            <option value="false">Inactive</option>
          </Select>

          {(filters.type || filters.is_active !== undefined || filters.search) && (
            <Button
              variant="outline"
              onClick={() => {
                setFilters({});
                setSearch('');
                setPage(1);
              }}
            >
              <X className="w-4 h-4 mr-2" />
              Clear
            </Button>
          )}
        </div>
      </Card>

      {/* Error State */}
      {error && (
        <Card className="p-4 border-red-200 bg-red-50">
          <p className="text-red-600">Failed to load templates. Please try again.</p>
        </Card>
      )}

      {/* Loading State */}
      {isLoading && templates.length === 0 && (
        <div className="flex items-center justify-center h-64">
          <LoadingSpinner size="lg" />
        </div>
      )}

      {/* Empty State */}
      {!isLoading && templates.length === 0 && !error && (
        <Card className="p-8 text-center">
          <div className="text-gray-400 mb-4">
            <Plus className="w-12 h-12 mx-auto" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-2">No templates found</h3>
          <p className="text-gray-600 mb-4">
            Create your first template to get started
          </p>
          <Button onClick={() => setShowCreateDialog(true)}>
            <Plus className="w-4 h-4 mr-2" />
            New Template
          </Button>
        </Card>
      )}

      {/* Templates List */}
      {templates.length > 0 && (
        <div className="space-y-4">
          {templates.map((template) => (
            <Card key={template.id} className="p-4">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <div className="flex items-center gap-3 mb-2">
                    <h3 className="text-lg font-semibold text-gray-900">{template.name}</h3>
                    <Badge variant={getTypeColor(template.type || template.category)}>
                      {getTypeLabel(template.type || template.category)}
                    </Badge>
                    {template.is_active ? (
                      <Badge variant="green">Active</Badge>
                    ) : (
                      <Badge variant="gray">Inactive</Badge>
                    )}
                  </div>
                  {template.description && (
                    <p className="text-gray-600 text-sm mb-2">{template.description}</p>
                  )}
                  <div className="flex items-center gap-4 text-xs text-gray-500">
                    <span>Created: {new Date(template.created_at).toLocaleDateString()}</span>
                    {template.updated_at && (
                      <span>Updated: {new Date(template.updated_at).toLocaleDateString()}</span>
                    )}
                  </div>
                </div>
                <div className="flex items-center gap-2">
                  {template.type === 'project' && (
                    <>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setManagingTaskTemplates(template)}
                        disabled={deleteTemplate.isPending}
                      >
                        <ListChecks className="w-4 h-4 mr-1" />
                        Manage Tasks
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setCreatingProjectFromTemplate(template)}
                        disabled={deleteTemplate.isPending}
                      >
                        <FolderPlus className="w-4 h-4 mr-1" />
                        Create Project
                      </Button>
                    </>
                  )}
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setEditingTemplateId(template.id)}
                    disabled={deleteTemplate.isPending}
                  >
                    <Edit className="w-4 h-4 mr-1" />
                    Edit
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleDelete(template.id)}
                    disabled={deleteTemplate.isPending}
                  >
                    <Trash2 className="w-4 h-4 mr-1" />
                    Delete
                  </Button>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Pagination */}
      {pagination && pagination.last_page > 1 && (
        <div className="flex items-center justify-center gap-2">
          <Button
            variant="outline"
            onClick={() => setPage(p => Math.max(1, p - 1))}
            disabled={page === 1}
          >
            Previous
          </Button>
          <span className="text-sm text-gray-600">
            Page {pagination.current_page} of {pagination.last_page}
          </span>
          <Button
            variant="outline"
            onClick={() => setPage(p => Math.min(pagination.last_page, p + 1))}
            disabled={page === pagination.last_page}
          >
            Next
          </Button>
        </div>
      )}

      {/* Create Template Dialog */}
      {showCreateDialog && (
        <TemplateCreateDialog
          open={showCreateDialog}
          onClose={() => setShowCreateDialog(false)}
          onSuccess={() => {
            setShowCreateDialog(false);
            setPage(1);
          }}
        />
      )}

      {/* Edit Template Dialog */}
      {editingTemplateId && (
        <TemplateEditDialog
          open={!!editingTemplateId}
          templateId={editingTemplateId}
          onClose={() => setEditingTemplateId(null)}
          onSuccess={() => {
            setEditingTemplateId(null);
            setPage(1);
          }}
        />
      )}

      {/* Create Project from Template Dialog */}
      {creatingProjectFromTemplate && (
        <CreateProjectFromTemplateDialog
          open={!!creatingProjectFromTemplate}
          template={creatingProjectFromTemplate}
          onClose={() => setCreatingProjectFromTemplate(null)}
          onSuccess={() => {
            setCreatingProjectFromTemplate(null);
          }}
        />
      )}

      {/* Manage Task Templates Dialog */}
      {managingTaskTemplates && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
          <div className="bg-white rounded-lg shadow-xl w-full max-w-4xl p-6 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-xl font-bold text-gray-900">Manage Task Templates</h2>
              <button
                onClick={() => setManagingTaskTemplates(null)}
                className="text-gray-400 hover:text-gray-600"
              >
                <X className="w-5 h-5" />
              </button>
            </div>
            <TaskTemplateList
              templateId={managingTaskTemplates.id}
              templateName={managingTaskTemplates.name}
            />
          </div>
        </div>
      )}
    </div>
  );
};

export default TemplatesPage;
