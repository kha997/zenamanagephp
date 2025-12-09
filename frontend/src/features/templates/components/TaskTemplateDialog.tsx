import React, { useState, useEffect } from 'react';
import { useCreateTaskTemplate, useUpdateTaskTemplate } from '../hooks';
import type { TaskTemplate, TaskTemplatePayload } from '../api';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { X } from 'lucide-react';

interface TaskTemplateDialogProps {
  open: boolean;
  templateId: string | number;
  taskTemplate?: TaskTemplate | null;
  onClose: () => void;
  onSuccess?: () => void;
}

/**
 * TaskTemplateDialog
 * 
 * Round 200: Task Template Vertical MVP
 * 
 * Dialog for creating or editing a task template
 */
export const TaskTemplateDialog: React.FC<TaskTemplateDialogProps> = ({
  open,
  templateId,
  taskTemplate,
  onClose,
  onSuccess,
}) => {
  const isEditMode = !!taskTemplate;
  const createTaskTemplate = useCreateTaskTemplate(templateId);
  const updateTaskTemplate = useUpdateTaskTemplate(
    templateId,
    taskTemplate?.id || ''
  );

  const [formData, setFormData] = useState<TaskTemplatePayload>({
    name: '',
    description: '',
    order_index: null,
    phase_code: null,
    phase_label: null,
    group_label: null,
    estimated_hours: null,
    is_required: true,
    metadata: null,
  });

  // Load task template data when editing
  useEffect(() => {
    if (open && taskTemplate) {
      setFormData({
        name: taskTemplate.name || '',
        description: taskTemplate.description || '',
        order_index: taskTemplate.order_index ?? null,
        phase_code: taskTemplate.phase_code ?? null,
        phase_label: taskTemplate.phase_label ?? null,
        group_label: taskTemplate.group_label ?? null,
        estimated_hours: taskTemplate.estimated_hours ?? null,
        is_required: taskTemplate.is_required ?? true,
        metadata: taskTemplate.metadata || null,
      });
    } else if (open && !taskTemplate) {
      // Reset form for create mode
      setFormData({
        name: '',
        description: '',
        order_index: null,
        phase_code: null,
        phase_label: null,
        group_label: null,
        estimated_hours: null,
        is_required: true,
        metadata: null,
      });
    }
  }, [open, taskTemplate]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
      if (isEditMode && taskTemplate) {
        await updateTaskTemplate.mutateAsync(formData);
      } else {
        await createTaskTemplate.mutateAsync(formData);
      }
      onSuccess?.();
      onClose();
    } catch (error) {
      console.error(`Failed to ${isEditMode ? 'update' : 'create'} task template:`, error);
      alert(`Failed to ${isEditMode ? 'update' : 'create'} task template. Please try again.`);
    }
  };

  if (!open) return null;

  const isLoading = isEditMode ? updateTaskTemplate.isPending : createTaskTemplate.isPending;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-6 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-bold text-gray-900">
            {isEditMode ? 'Edit Task Template' : 'Create Task Template'}
          </h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
            disabled={isLoading}
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Task Name <span className="text-red-500">*</span>
            </label>
            <Input
              value={formData.name}
              onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
              placeholder="Task template name"
              required
              disabled={isLoading}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Description
            </label>
            <textarea
              value={formData.description || ''}
              onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value || null }))}
              placeholder="Task description (optional)"
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              disabled={isLoading}
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Order Index
              </label>
              <Input
                type="number"
                value={formData.order_index ?? ''}
                onChange={(e) => setFormData(prev => ({ 
                  ...prev, 
                  order_index: e.target.value ? parseInt(e.target.value, 10) : null 
                }))}
                placeholder="0"
                min="0"
                disabled={isLoading}
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Estimated Hours
              </label>
              <Input
                type="number"
                step="0.5"
                value={formData.estimated_hours ?? ''}
                onChange={(e) => setFormData(prev => ({ 
                  ...prev, 
                  estimated_hours: e.target.value ? parseFloat(e.target.value) : null 
                }))}
                placeholder="0.0"
                min="0"
                disabled={isLoading}
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Phase Label
            </label>
            <Input
              value={formData.phase_label || ''}
              onChange={(e) => setFormData(prev => ({ ...prev, phase_label: e.target.value || null }))}
              placeholder="VD: Khảo sát, Thiết kế Concept, TKCS..."
              disabled={isLoading}
            />
            <p className="text-xs text-gray-500 mt-1">
              Giai đoạn chính của task (VD: Khảo sát & Thu thập thông tin)
            </p>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Group Label
            </label>
            <Input
              value={formData.group_label || ''}
              onChange={(e) => setFormData(prev => ({ ...prev, group_label: e.target.value || null }))}
              placeholder="VD: Họp với CĐT, Triển khai bản vẽ..."
              disabled={isLoading}
            />
            <p className="text-xs text-gray-500 mt-1">
              Nhóm nhỏ trong phase (tùy chọn)
            </p>
          </div>

          <div className="flex items-center">
            <input
              type="checkbox"
              id="is_required"
              checked={formData.is_required ?? true}
              onChange={(e) => setFormData(prev => ({ ...prev, is_required: e.target.checked }))}
              className="mr-2"
              disabled={isLoading}
            />
            <label htmlFor="is_required" className="text-sm font-medium text-gray-700">
              Required Task
            </label>
          </div>

          <div className="flex items-center justify-end gap-3 pt-4 border-t">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              disabled={isLoading}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              disabled={isLoading || !formData.name}
            >
              {isLoading 
                ? (isEditMode ? 'Updating...' : 'Creating...') 
                : (isEditMode ? 'Update Task Template' : 'Create Task Template')
              }
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
};

