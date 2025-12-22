import React, { useState, useCallback, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { useCreateTask } from '../hooks';
import { useProjects } from '../../projects/hooks';
import { useUsers } from '../../users/hooks';
import type { Task } from '../types';

interface FormData {
  title: string;
  description: string;
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
  priority: 'low' | 'medium' | 'high' | 'urgent';
  project_id: string;
  assignee_id: string;
  due_date: string;
}

interface FormErrors {
  title?: string;
  description?: string;
  due_date?: string;
}

export const CreateTaskPage: React.FC = () => {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const createTask = useCreateTask();
  const { data: projectsData } = useProjects();
  const { data: usersData } = useUsers();
  
  // Pre-fill project_id from URL if provided
  const projectIdFromUrl = searchParams.get('project_id');
  
  const [formData, setFormData] = useState<FormData>({
    title: '',
    description: '',
    status: 'pending',
    priority: 'medium',
    project_id: projectIdFromUrl || '',
    assignee_id: '',
    due_date: '',
  });
  
  const [errors, setErrors] = useState<FormErrors>({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Validation
  const validateForm = useCallback((): boolean => {
    const newErrors: FormErrors = {};
    
    // Title is required
    if (!formData.title.trim()) {
      newErrors.title = 'Task title is required';
    } else if (formData.title.length > 255) {
      newErrors.title = 'Task title must be less than 255 characters';
    }
    
    // Description validation
    if (formData.description && formData.description.length > 1000) {
      newErrors.description = 'Description must be less than 1000 characters';
    }
    
    // Due date validation (if provided, should be in the future or today)
    if (formData.due_date) {
      const dueDate = new Date(formData.due_date);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      if (dueDate < today) {
        newErrors.due_date = 'Due date cannot be in the past';
      }
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  }, [formData]);

  const handleInputChange = useCallback((field: keyof FormData, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    // Clear error when user starts typing
    if (errors[field as keyof FormErrors]) {
      setErrors(prev => {
        const newErrors = { ...prev };
        delete newErrors[field as keyof FormErrors];
        return newErrors;
      });
    }
  }, [errors]);

  const handleSubmit = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }
    
    setIsSubmitting(true);
    
    try {
      const payload: Partial<Task> = {
        title: formData.title.trim(),
        description: formData.description.trim() || undefined,
        status: formData.status,
        priority: formData.priority,
        project_id: formData.project_id ? parseInt(formData.project_id) : undefined,
        assignee_id: formData.assignee_id ? parseInt(formData.assignee_id) : undefined,
        due_date: formData.due_date || undefined,
      };
      
      const result = await createTask.mutateAsync(payload);
      
      // Success - redirect to task detail page or project if created from project
      if (result?.data?.id) {
        if (formData.project_id) {
          navigate(`/app/projects/${formData.project_id}`);
        } else {
          navigate(`/app/tasks/${result.data.id}`);
        }
      } else {
        navigate('/app/tasks');
      }
    } catch (error: any) {
      // Handle API validation errors
      if (error?.response?.data?.error?.details) {
        const apiErrors = error.response.data.error.details;
        const newErrors: FormErrors = {};
        
        Object.entries(apiErrors).forEach(([field, messages]) => {
          if (Array.isArray(messages) && messages.length > 0) {
            newErrors[field as keyof FormErrors] = messages[0] as string;
          }
        });
        
        setErrors(newErrors);
      } else {
        // Generic error
        setErrors({ title: error?.message || 'Failed to create task. Please try again.' });
      }
    } finally {
      setIsSubmitting(false);
    }
  }, [formData, validateForm, createTask, navigate]);

  const handleCancel = useCallback(() => {
    if (formData.project_id) {
      navigate(`/app/projects/${formData.project_id}`);
    } else {
      navigate('/app/tasks');
    }
  }, [navigate, formData.project_id]);

  return (
    <Container>
      <div className="space-y-6">
        {/* Page Header */}
        <div>
          <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]">
            Create New Task
          </h1>
          <p className="text-[var(--font-body-size)] text-[var(--muted)] mt-1">
            Fill in the details below to create a new task
          </p>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit}>
          <Card>
            <CardHeader>
              <CardTitle>Task Information</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Task Title */}
              <div>
                <label
                  htmlFor="title"
                  className="block text-sm font-medium text-[var(--text)] mb-2"
                >
                  Task Title <span className="text-red-500">*</span>
                </label>
                <Input
                  id="title"
                  type="text"
                  value={formData.title}
                  onChange={(e) => handleInputChange('title', e.target.value)}
                  placeholder="Enter task title"
                  error={errors.title}
                  required
                  autoFocus
                />
              </div>

              {/* Description */}
              <div>
                <label
                  htmlFor="description"
                  className="block text-sm font-medium text-[var(--text)] mb-2"
                >
                  Description
                </label>
                <textarea
                  id="description"
                  value={formData.description}
                  onChange={(e) => handleInputChange('description', e.target.value)}
                  placeholder="Enter task description"
                  rows={4}
                  className="w-full px-3 py-2 border border-[var(--border)] rounded-lg focus:ring-2 focus:ring-[var(--accent)] focus:border-[var(--accent)] outline-none resize-none"
                  style={{
                    backgroundColor: 'var(--surface)',
                    color: 'var(--text)',
                  }}
                />
                {errors.description && (
                  <div className="mt-1 text-sm text-red-600">{errors.description}</div>
                )}
                <div className="mt-1 text-xs text-[var(--muted)]">
                  {formData.description.length}/1000 characters
                </div>
              </div>

              {/* Status and Priority */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {/* Status */}
                <div>
                  <label
                    htmlFor="status"
                    className="block text-sm font-medium text-[var(--text)] mb-2"
                  >
                    Status
                  </label>
                  <select
                    id="status"
                    value={formData.status}
                    onChange={(e) => handleInputChange('status', e.target.value)}
                    className="w-full px-3 py-2 border border-[var(--border)] rounded-lg focus:ring-2 focus:ring-[var(--accent)] focus:border-[var(--accent)] outline-none"
                    style={{
                      backgroundColor: 'var(--surface)',
                      color: 'var(--text)',
                      height: '40px',
                    }}
                  >
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                </div>

                {/* Priority */}
                <div>
                  <label
                    htmlFor="priority"
                    className="block text-sm font-medium text-[var(--text)] mb-2"
                  >
                    Priority
                  </label>
                  <select
                    id="priority"
                    value={formData.priority}
                    onChange={(e) => handleInputChange('priority', e.target.value)}
                    className="w-full px-3 py-2 border border-[var(--border)] rounded-lg focus:ring-2 focus:ring-[var(--accent)] focus:border-[var(--accent)] outline-none"
                    style={{
                      backgroundColor: 'var(--surface)',
                      color: 'var(--text)',
                      height: '40px',
                    }}
                  >
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </div>
              </div>

              {/* Project and Assignee */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {/* Project */}
                <div>
                  <label
                    htmlFor="project_id"
                    className="block text-sm font-medium text-[var(--text)] mb-2"
                  >
                    Project
                  </label>
                  <select
                    id="project_id"
                    value={formData.project_id}
                    onChange={(e) => handleInputChange('project_id', e.target.value)}
                    className="w-full px-3 py-2 border border-[var(--border)] rounded-lg focus:ring-2 focus:ring-[var(--accent)] focus:border-[var(--accent)] outline-none"
                    style={{
                      backgroundColor: 'var(--surface)',
                      color: 'var(--text)',
                      height: '40px',
                    }}
                  >
                    <option value="">Select a project (optional)</option>
                    {projectsData?.data?.map((project) => (
                      <option key={project.id} value={project.id}>
                        {project.name}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Assignee */}
                <div>
                  <label
                    htmlFor="assignee_id"
                    className="block text-sm font-medium text-[var(--text)] mb-2"
                  >
                    Assignee
                  </label>
                  <select
                    id="assignee_id"
                    value={formData.assignee_id}
                    onChange={(e) => handleInputChange('assignee_id', e.target.value)}
                    className="w-full px-3 py-2 border border-[var(--border)] rounded-lg focus:ring-2 focus:ring-[var(--accent)] focus:border-[var(--accent)] outline-none"
                    style={{
                      backgroundColor: 'var(--surface)',
                      color: 'var(--text)',
                      height: '40px',
                    }}
                  >
                    <option value="">Unassigned</option>
                    {usersData?.data?.map((user) => (
                      <option key={user.id} value={user.id}>
                        {user.name} ({user.email})
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              {/* Due Date */}
              <div>
                <label
                  htmlFor="due_date"
                  className="block text-sm font-medium text-[var(--text)] mb-2"
                >
                  Due Date
                </label>
                <Input
                  id="due_date"
                  type="date"
                  value={formData.due_date}
                  onChange={(e) => handleInputChange('due_date', e.target.value)}
                  error={errors.due_date}
                  min={new Date().toISOString().split('T')[0]}
                />
              </div>

              {/* Form Actions */}
              <div className="flex items-center justify-end gap-3 pt-4 border-t border-[var(--border)]">
                <Button
                  type="button"
                  variant="secondary"
                  onClick={handleCancel}
                  disabled={isSubmitting}
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  disabled={isSubmitting || !formData.title.trim()}
                >
                  {isSubmitting ? 'Creating...' : 'Create Task'}
                </Button>
              </div>
            </CardContent>
          </Card>
        </form>
      </div>
    </Container>
  );
};

export default CreateTaskPage;
