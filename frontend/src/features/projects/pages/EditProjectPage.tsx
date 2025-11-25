import React, { useState, useCallback, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { useProject, useUpdateProject } from '../hooks';
import type { Project } from '../types';

interface FormData {
  name: string;
  description: string;
  status: 'planning' | 'active' | 'on_hold' | 'completed' | 'cancelled';
  priority: 'low' | 'medium' | 'normal' | 'high' | 'urgent';
  start_date: string;
  end_date: string;
  budget_total: string;
  client_id: string;
}

interface FormErrors {
  name?: string;
  description?: string;
  start_date?: string;
  end_date?: string;
  budget_total?: string;
}

export const EditProjectPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { data: projectData, isLoading, error } = useProject(id!);
  const updateProject = useUpdateProject();
  
  const [formData, setFormData] = useState<FormData>({
    name: '',
    description: '',
    status: 'planning',
    priority: 'normal',
    start_date: '',
    end_date: '',
    budget_total: '',
    client_id: '',
  });
  
  const [errors, setErrors] = useState<FormErrors>({});
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isInitialized, setIsInitialized] = useState(false);

  // Pre-fill form with existing project data
  useEffect(() => {
    if (projectData?.data && !isInitialized) {
      const project = projectData.data;
      setFormData({
        name: project.name || '',
        description: project.description || '',
        status: project.status || 'planning',
        priority: (project.priority as any) || 'normal',
        start_date: project.start_date ? project.start_date.split('T')[0] : '',
        end_date: project.end_date ? project.end_date.split('T')[0] : '',
        budget_total: project.budget_total ? project.budget_total.toString() : '',
        client_id: project.client_id ? String(project.client_id) : '',
      });
      setIsInitialized(true);
    }
  }, [projectData, isInitialized]);

  // Validation
  const validateForm = useCallback((): boolean => {
    const newErrors: FormErrors = {};
    
    // Name is required
    if (!formData.name.trim()) {
      newErrors.name = 'Project name is required';
    } else if (formData.name.length > 255) {
      newErrors.name = 'Project name must be less than 255 characters';
    }
    
    // Description validation
    if (formData.description && formData.description.length > 1000) {
      newErrors.description = 'Description must be less than 1000 characters';
    }
    
    // Date validation
    if (formData.start_date && formData.end_date) {
      const startDate = new Date(formData.start_date);
      const endDate = new Date(formData.end_date);
      
      if (endDate < startDate) {
        newErrors.end_date = 'End date must be after start date';
      }
    }
    
    // Budget validation
    if (formData.budget_total) {
      const budget = parseFloat(formData.budget_total);
      if (isNaN(budget) || budget < 0) {
        newErrors.budget_total = 'Budget must be a positive number';
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
    
    if (!id) {
      return;
    }
    
    if (!validateForm()) {
      return;
    }
    
    setIsSubmitting(true);
    
    try {
      const payload: Partial<Project> = {
        name: formData.name.trim(),
        description: formData.description.trim() || undefined,
        status: formData.status,
        priority: formData.priority,
        start_date: formData.start_date || undefined,
        end_date: formData.end_date || undefined,
        budget_total: formData.budget_total ? parseFloat(formData.budget_total) : undefined,
      };
      
      await updateProject.mutateAsync({ id, data: payload });
      
      // Success - redirect to project detail page
      navigate(`/app/projects/${id}`);
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
        setErrors({ name: error?.message || 'Failed to update project. Please try again.' });
      }
    } finally {
      setIsSubmitting(false);
    }
  }, [id, formData, validateForm, updateProject, navigate]);

  const handleCancel = useCallback(() => {
    if (id) {
      navigate(`/app/projects/${id}`);
    } else {
      navigate('/app/projects');
    }
  }, [navigate, id]);

  if (isLoading) {
    return (
      <Container>
        <div className="space-y-6">
          <div className="animate-pulse">
            <div className="h-8 bg-[var(--muted-surface)] rounded w-1/3 mb-4"></div>
            <div className="h-4 bg-[var(--muted-surface)] rounded w-1/2"></div>
          </div>
        </div>
      </Container>
    );
  }

  if (error) {
    return (
      <Container>
        <Card>
          <CardContent className="py-8">
            <div className="text-center">
              <p className="text-[var(--muted)] mb-4">
                Error loading project: {(error as Error).message}
              </p>
              <Button variant="secondary" onClick={() => navigate('/app/projects')}>
                Back to Projects
              </Button>
            </div>
          </CardContent>
        </Card>
      </Container>
    );
  }

  if (!projectData?.data) {
    return (
      <Container>
        <Card>
          <CardContent className="py-8">
            <div className="text-center">
              <p className="text-[var(--muted)] mb-4">Project not found</p>
              <Button variant="secondary" onClick={() => navigate('/app/projects')}>
                Back to Projects
              </Button>
            </div>
          </CardContent>
        </Card>
      </Container>
    );
  }

  return (
    <Container>
      <div className="space-y-6">
        {/* Page Header */}
        <div>
          <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]">
            Edit Project
          </h1>
          <p className="text-[var(--font-body-size)] text-[var(--muted)] mt-1">
            Update project details below
          </p>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit}>
          <Card>
            <CardHeader>
              <CardTitle>Project Information</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
              {/* Project Name */}
              <div>
                <label
                  htmlFor="name"
                  className="block text-sm font-medium text-[var(--text)] mb-2"
                >
                  Project Name <span className="text-red-500">*</span>
                </label>
                <Input
                  id="name"
                  type="text"
                  value={formData.name}
                  onChange={(e) => handleInputChange('name', e.target.value)}
                  placeholder="Enter project name"
                  error={errors.name}
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
                  placeholder="Enter project description"
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
                    <option value="planning">Planning</option>
                    <option value="active">Active</option>
                    <option value="on_hold">On Hold</option>
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
                    <option value="normal">Normal</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </div>
              </div>

              {/* Dates */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {/* Start Date */}
                <div>
                  <label
                    htmlFor="start_date"
                    className="block text-sm font-medium text-[var(--text)] mb-2"
                  >
                    Start Date
                  </label>
                  <Input
                    id="start_date"
                    type="date"
                    value={formData.start_date}
                    onChange={(e) => handleInputChange('start_date', e.target.value)}
                    error={errors.start_date}
                  />
                </div>

                {/* End Date */}
                <div>
                  <label
                    htmlFor="end_date"
                    className="block text-sm font-medium text-[var(--text)] mb-2"
                  >
                    End Date
                  </label>
                  <Input
                    id="end_date"
                    type="date"
                    value={formData.end_date}
                    onChange={(e) => handleInputChange('end_date', e.target.value)}
                    error={errors.end_date}
                  />
                </div>
              </div>

              {/* Budget */}
              <div>
                <label
                  htmlFor="budget_total"
                  className="block text-sm font-medium text-[var(--text)] mb-2"
                >
                  Budget
                </label>
                <div className="relative">
                  <span
                    className="absolute left-3 top-1/2 transform -translate-y-1/2 text-[var(--muted)]"
                    style={{ fontSize: '14px' }}
                  >
                    $
                  </span>
                  <Input
                    id="budget_total"
                    type="number"
                    value={formData.budget_total}
                    onChange={(e) => handleInputChange('budget_total', e.target.value)}
                    placeholder="0.00"
                    error={errors.budget_total}
                    style={{ paddingLeft: '28px' }}
                    min="0"
                    step="0.01"
                  />
                </div>
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
                  disabled={isSubmitting || !formData.name.trim()}
                >
                  {isSubmitting ? 'Saving...' : 'Save Changes'}
                </Button>
              </div>
            </CardContent>
          </Card>
        </form>
      </div>
    </Container>
  );
};

export default EditProjectPage;

