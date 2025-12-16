import React, { useState, useEffect } from 'react';
import { useUpdateTemplate, useTemplate } from '../hooks';
import type { UpdateTemplateData, Template } from '../api';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { Select } from '../../../components/ui/Select';
import { X } from 'lucide-react';

interface TemplateEditDialogProps {
  open: boolean;
  templateId: string | number | null;
  onClose: () => void;
  onSuccess?: () => void;
}

/**
 * TemplateEditDialog
 * 
 * Round 193: Templates UX Completion
 * 
 * Dialog for editing an existing template
 */
export const TemplateEditDialog: React.FC<TemplateEditDialogProps> = ({
  open,
  templateId,
  onClose,
  onSuccess,
}) => {
  const { data: templateData, isLoading } = useTemplate(templateId);
  const template = templateData?.data;
  const updateTemplate = useUpdateTemplate();

  const [formData, setFormData] = useState<UpdateTemplateData>({
    name: '',
    type: 'project',
    description: '',
    is_active: true,
    metadata: {},
  });

  // Load template data when dialog opens
  useEffect(() => {
    if (open && template) {
      setFormData({
        name: template.name || '',
        type: (template.type || template.category) as 'project' | 'task' | 'document' | 'checklist',
        description: template.description || '',
        is_active: template.is_active ?? true,
        metadata: template.metadata || {},
      });
    }
  }, [open, template]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!templateId) return;

    try {
      await updateTemplate.mutateAsync({
        id: templateId,
        data: formData,
      });
      onSuccess?.();
      onClose();
    } catch (error) {
      console.error('Failed to update template:', error);
      // Error handling can be improved with toast notifications
    }
  };

  if (!open) return null;

  if (isLoading) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
          <p className="text-center text-gray-600">Loading template...</p>
        </div>
      </div>
    );
  }

  if (!template) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
          <p className="text-center text-red-600">Template not found</p>
          <Button onClick={onClose} className="mt-4 w-full">
            Close
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-md p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-xl font-bold text-gray-900">Edit Template</h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Name <span className="text-red-500">*</span>
            </label>
            <Input
              value={formData.name}
              onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
              placeholder="Template name"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Type <span className="text-red-500">*</span>
            </label>
            <Select
              value={formData.type}
              onChange={(e) => setFormData(prev => ({ ...prev, type: e.target.value as any }))}
              required
            >
              <option value="project">Project</option>
              <option value="task">Task</option>
              <option value="document">Document</option>
              <option value="checklist">Checklist</option>
            </Select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Description
            </label>
            <textarea
              value={formData.description}
              onChange={(e) => setFormData(prev => ({ ...prev, description: e.target.value }))}
              placeholder="Template description (optional)"
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div className="flex items-center">
            <input
              type="checkbox"
              id="is_active"
              checked={formData.is_active}
              onChange={(e) => setFormData(prev => ({ ...prev, is_active: e.target.checked }))}
              className="mr-2"
            />
            <label htmlFor="is_active" className="text-sm font-medium text-gray-700">
              Active
            </label>
          </div>

          <div className="flex items-center justify-end gap-3 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              disabled={updateTemplate.isPending}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              disabled={updateTemplate.isPending || !formData.name}
            >
              {updateTemplate.isPending ? 'Updating...' : 'Update Template'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
};

