import React, { useState } from 'react';
import { useTaskTemplates, useDeleteTaskTemplate } from '../hooks';
import type { TaskTemplate } from '../api';
import { Button } from '../../../components/ui/Button';
import { LoadingSpinner } from '../../../components/ui/LoadingSpinner';
import { Plus, Edit, Trash2, CheckCircle2, Circle } from 'lucide-react';
import { TaskTemplateDialog } from './TaskTemplateDialog';

interface TaskTemplateListProps {
  templateId: string | number;
  templateName?: string;
}

/**
 * TaskTemplateList
 * 
 * Round 200: Task Template Vertical MVP
 * 
 * Displays a list of task templates for a given template
 */
export const TaskTemplateList: React.FC<TaskTemplateListProps> = ({
  templateId,
  templateName,
}) => {
  const { data, isLoading, error } = useTaskTemplates(templateId);
  const deleteTaskTemplate = useDeleteTaskTemplate(templateId);
  
  const [showCreateDialog, setShowCreateDialog] = useState(false);
  const [editingTaskTemplate, setEditingTaskTemplate] = useState<TaskTemplate | null>(null);

  const taskTemplates = data?.data || [];

  const handleDelete = async (taskId: string | number) => {
    if (window.confirm('Are you sure you want to delete this task template?')) {
      try {
        await deleteTaskTemplate.mutateAsync(taskId);
      } catch (error) {
        console.error('Failed to delete task template:', error);
        alert('Failed to delete task template. Please try again.');
      }
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-8">
        <LoadingSpinner />
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-4 bg-red-50 border border-red-200 rounded-md">
        <p className="text-red-600">Failed to load task templates. Please try again.</p>
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div>
          <h3 className="text-lg font-semibold text-gray-900">
            {templateName ? `${templateName} - Task Templates` : 'Task Templates'}
          </h3>
          <p className="text-sm text-gray-500 mt-1">
            Manage checklist items for this template
          </p>
        </div>
        <Button
          onClick={() => setShowCreateDialog(true)}
          size="sm"
        >
          <Plus className="w-4 h-4 mr-2" />
          Add Task Template
        </Button>
      </div>

      {taskTemplates.length === 0 ? (
        <div className="p-8 text-center border border-gray-200 rounded-lg bg-gray-50">
          <p className="text-gray-600 mb-4">No task templates yet.</p>
          <Button
            onClick={() => setShowCreateDialog(true)}
            variant="outline"
            size="sm"
          >
            <Plus className="w-4 h-4 mr-2" />
            Add First Task Template
          </Button>
        </div>
      ) : (
        <div className="border border-gray-200 rounded-lg overflow-hidden">
          <table className="w-full">
            <thead className="bg-gray-50 border-b border-gray-200">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Order
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Task Name
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Phase / Group
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Description
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Required
                </th>
                <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Est. Hours
                </th>
                <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {taskTemplates.map((taskTemplate) => (
                <tr key={taskTemplate.id} className="hover:bg-gray-50">
                  <td className="px-4 py-3 text-sm text-gray-900">
                    {taskTemplate.order_index ?? '-'}
                  </td>
                  <td className="px-4 py-3 text-sm font-medium text-gray-900">
                    {taskTemplate.name}
                  </td>
                  <td className="px-4 py-3 text-sm">
                    {taskTemplate.phase_label || taskTemplate.group_label ? (
                      <div className="flex flex-col">
                        {taskTemplate.phase_label && (
                          <span className="font-medium text-gray-900">{taskTemplate.phase_label}</span>
                        )}
                        {taskTemplate.group_label && (
                          <span className="text-xs text-gray-500 mt-0.5">{taskTemplate.group_label}</span>
                        )}
                      </div>
                    ) : (
                      <span className="text-gray-400">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-500">
                    {taskTemplate.description || '-'}
                  </td>
                  <td className="px-4 py-3 text-sm">
                    {taskTemplate.is_required ? (
                      <span className="inline-flex items-center text-green-600">
                        <CheckCircle2 className="w-4 h-4 mr-1" />
                        Required
                      </span>
                    ) : (
                      <span className="inline-flex items-center text-gray-400">
                        <Circle className="w-4 h-4 mr-1" />
                        Optional
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-sm text-gray-900">
                    {taskTemplate.estimated_hours ? `${taskTemplate.estimated_hours}h` : '-'}
                  </td>
                  <td className="px-4 py-3 text-sm text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        onClick={() => setEditingTaskTemplate(taskTemplate)}
                        className="text-blue-600 hover:text-blue-800"
                        title="Edit"
                      >
                        <Edit className="w-4 h-4" />
                      </button>
                      <button
                        onClick={() => handleDelete(taskTemplate.id)}
                        className="text-red-600 hover:text-red-800"
                        title="Delete"
                        disabled={deleteTaskTemplate.isPending}
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Create Dialog */}
      {showCreateDialog && (
        <TaskTemplateDialog
          open={showCreateDialog}
          templateId={templateId}
          onClose={() => setShowCreateDialog(false)}
          onSuccess={() => setShowCreateDialog(false)}
        />
      )}

      {/* Edit Dialog */}
      {editingTaskTemplate && (
        <TaskTemplateDialog
          open={!!editingTaskTemplate}
          templateId={templateId}
          taskTemplate={editingTaskTemplate}
          onClose={() => setEditingTaskTemplate(null)}
          onSuccess={() => setEditingTaskTemplate(null)}
        />
      )}
    </div>
  );
};

