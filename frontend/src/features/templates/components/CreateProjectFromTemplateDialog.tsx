import React, { useState } from 'react';
import { useCreateProjectFromTemplate } from '../hooks';
import type { Template } from '../api';
import { Button } from '../../../components/ui/Button';
import { Input } from '../../../components/ui/Input';
import { Select } from '../../../components/ui/Select';
import { X } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

interface CreateProjectFromTemplateDialogProps {
  open: boolean;
  onClose: () => void;
  template: Template;
  onSuccess?: () => void;
}

/**
 * CreateProjectFromTemplateDialog
 * 
 * Round 194: Project from Template
 * 
 * Dialog for creating a project from a template
 */
export const CreateProjectFromTemplateDialog: React.FC<CreateProjectFromTemplateDialogProps> = ({
  open,
  onClose,
  template,
  onSuccess,
}) => {
  const navigate = useNavigate();
  const [formData, setFormData] = useState({
    name: template.name || '',
    description: template.description || '',
    code: '',
    status: 'planning',
    priority: 'normal',
    start_date: '',
    end_date: '',
    budget_total: '',
  });

  const createProject = useCreateProjectFromTemplate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    try {
      const payload: any = {
        name: formData.name,
        description: formData.description || undefined,
        code: formData.code || undefined,
        status: formData.status,
        priority: formData.priority,
      };

      if (formData.start_date) {
        payload.start_date = formData.start_date;
      }
      if (formData.end_date) {
        payload.end_date = formData.end_date;
      }
      if (formData.budget_total) {
        payload.budget_total = parseFloat(formData.budget_total);
      }

      const result = await createProject.mutateAsync({
        templateId: template.id,
        data: payload,
      });

      onSuccess?.();
      onClose();

      // Navigate to the new project if ID is available
      if (result.data?.id) {
        navigate(`/app/projects/${result.data.id}`);
      }
    } catch (error) {
      console.error('Failed to create project from template:', error);
      // Error handling is done by the mutation
    }
  };

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between p-6 border-b">
          <h2 className="text-xl font-semibold">Create Project from Template</h2>
          <Button variant="ghost" size="sm" onClick={onClose}>
            <X className="w-4 h-4" />
          </Button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Template
            </label>
            <Input
              value={template.name}
              disabled
              className="bg-gray-50"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Project Name <span className="text-red-500">*</span>
            </label>
            <Input
              value={formData.name}
              onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              required
              placeholder="Enter project name"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Description
            </label>
            <textarea
              value={formData.description}
              onChange={(e) => setFormData({ ...formData, description: e.target.value })}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              rows={3}
              placeholder="Enter project description"
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Code
              </label>
              <Input
                value={formData.code}
                onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                placeholder="Auto-generated if empty"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Status
              </label>
              <Select
                value={formData.status}
                onChange={(e) => setFormData({ ...formData, status: e.target.value })}
              >
                <option value="planning">Planning</option>
                <option value="active">Active</option>
                <option value="on_hold">On Hold</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </Select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Priority
              </label>
              <Select
                value={formData.priority}
                onChange={(e) => setFormData({ ...formData, priority: e.target.value })}
              >
                <option value="low">Low</option>
                <option value="normal">Normal</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </Select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Budget Total
              </label>
              <Input
                type="number"
                step="0.01"
                min="0"
                value={formData.budget_total}
                onChange={(e) => setFormData({ ...formData, budget_total: e.target.value })}
                placeholder="0.00"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Start Date
              </label>
              <Input
                type="date"
                value={formData.start_date}
                onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                End Date
              </label>
              <Input
                type="date"
                value={formData.end_date}
                onChange={(e) => setFormData({ ...formData, end_date: e.target.value })}
                min={formData.start_date}
              />
            </div>
          </div>

          <div className="flex items-center justify-end gap-3 pt-4 border-t">
            <Button type="button" variant="outline" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" disabled={createProject.isPending}>
              {createProject.isPending ? 'Creating...' : 'Create Project'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
};

