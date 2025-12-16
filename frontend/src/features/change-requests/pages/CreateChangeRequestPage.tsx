import React, { useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { useCreateChangeRequest } from '../hooks';
import { useProjects } from '../../projects/hooks';
import type { ChangeRequest } from '../api';

interface FormData {
  title: string;
  description: string;
  project_id: string;
  change_type: string;
  priority: 'low' | 'medium' | 'high' | 'urgent';
  estimated_cost: string;
  estimated_days: string;
  due_date: string;
}

interface FormErrors {
  title?: string;
  description?: string;
  project_id?: string;
  change_type?: string;
  priority?: string;
}

export const CreateChangeRequestPage: React.FC = () => {
  const navigate = useNavigate();
  const createChangeRequest = useCreateChangeRequest();
  const { data: projectsData } = useProjects();
  
  const [formData, setFormData] = useState<FormData>({
    title: '',
    description: '',
    project_id: '',
    change_type: 'scope',
    priority: 'medium',
    estimated_cost: '',
    estimated_days: '',
    due_date: '',
  });
  
  const [errors, setErrors] = useState<FormErrors>({});
  
  const handleInputChange = useCallback((field: keyof FormData, value: string) => {
    setFormData(prev => ({ ...prev, [field]: value }));
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
    
    const newErrors: FormErrors = {};
    if (!formData.title.trim()) {
      newErrors.title = 'Title is required';
    }
    if (!formData.description.trim()) {
      newErrors.description = 'Description is required';
    }
    if (!formData.project_id) {
      newErrors.project_id = 'Project is required';
    }
    
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }
    
    try {
      const payload: Partial<ChangeRequest> = {
        title: formData.title,
        description: formData.description,
        project_id: formData.project_id,
        change_type: formData.change_type,
        priority: formData.priority,
        status: 'draft',
      };
      
      if (formData.estimated_cost) {
        payload.estimated_cost = parseFloat(formData.estimated_cost);
      }
      if (formData.estimated_days) {
        payload.estimated_days = parseInt(formData.estimated_days, 10);
      }
      if (formData.due_date) {
        payload.due_date = formData.due_date;
      }
      
      const result = await createChangeRequest.mutateAsync(payload);
      navigate(`/app/change-requests/${result.data.id}`);
    } catch (error: any) {
      console.error('Failed to create change request:', error);
      alert(error?.message || 'Failed to create change request. Please try again.');
    }
  }, [formData, createChangeRequest, navigate]);
  
  return (
    <Container>
      <Card>
        <CardHeader>
          <CardTitle>Create Change Request</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-1">
                Title <span className="text-red-500">*</span>
              </label>
              <Input
                value={formData.title}
                onChange={(e) => handleInputChange('title', e.target.value)}
                placeholder="Change request title"
                error={errors.title}
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-[var(--text)] mb-1">
                Description <span className="text-red-500">*</span>
              </label>
              <textarea
                value={formData.description}
                onChange={(e) => handleInputChange('description', e.target.value)}
                placeholder="Describe the change request..."
                rows={5}
                className={`w-full px-3 py-2 border rounded-md ${
                  errors.description ? 'border-red-500' : 'border-[var(--border)]'
                } bg-[var(--surface)] text-[var(--text)]`}
              />
              {errors.description && (
                <p className="text-sm text-red-500 mt-1">{errors.description}</p>
              )}
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-[var(--text)] mb-1">
                  Project <span className="text-red-500">*</span>
                </label>
                <select
                  value={formData.project_id}
                  onChange={(e) => handleInputChange('project_id', e.target.value)}
                  className={`w-full px-3 py-2 border rounded-md ${
                    errors.project_id ? 'border-red-500' : 'border-[var(--border)]'
                  } bg-[var(--surface)] text-[var(--text)]`}
                >
                  <option value="">Select a project</option>
                  {projectsData?.data?.map((project: any) => (
                    <option key={project.id} value={project.id}>
                      {project.name}
                    </option>
                  ))}
                </select>
                {errors.project_id && (
                  <p className="text-sm text-red-500 mt-1">{errors.project_id}</p>
                )}
              </div>
              
              <div>
                <label className="block text-sm font-medium text-[var(--text)] mb-1">
                  Change Type
                </label>
                <select
                  value={formData.change_type}
                  onChange={(e) => handleInputChange('change_type', e.target.value)}
                  className="w-full px-3 py-2 border border-[var(--border)] rounded-md bg-[var(--surface)] text-[var(--text)]"
                >
                  <option value="scope">Scope</option>
                  <option value="schedule">Schedule</option>
                  <option value="budget">Budget</option>
                  <option value="quality">Quality</option>
                  <option value="other">Other</option>
                </select>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-[var(--text)] mb-1">
                  Priority
                </label>
                <select
                  value={formData.priority}
                  onChange={(e) => handleInputChange('priority', e.target.value as any)}
                  className="w-full px-3 py-2 border border-[var(--border)] rounded-md bg-[var(--surface)] text-[var(--text)]"
                >
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
              
              <div>
                <label className="block text-sm font-medium text-[var(--text)] mb-1">
                  Due Date
                </label>
                <Input
                  type="date"
                  value={formData.due_date}
                  onChange={(e) => handleInputChange('due_date', e.target.value)}
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-[var(--text)] mb-1">
                  Estimated Cost
                </label>
                <Input
                  type="number"
                  step="0.01"
                  value={formData.estimated_cost}
                  onChange={(e) => handleInputChange('estimated_cost', e.target.value)}
                  placeholder="0.00"
                />
              </div>
              
              <div>
                <label className="block text-sm font-medium text-[var(--text)] mb-1">
                  Estimated Days
                </label>
                <Input
                  type="number"
                  value={formData.estimated_days}
                  onChange={(e) => handleInputChange('estimated_days', e.target.value)}
                  placeholder="0"
                />
              </div>
            </div>
            
            <div className="flex items-center gap-3 pt-4">
              <Button type="submit" disabled={createChangeRequest.isPending}>
                {createChangeRequest.isPending ? 'Creating...' : 'Create Change Request'}
              </Button>
              <Button variant="secondary" type="button" onClick={() => navigate('/app/change-requests')}>
                Cancel
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </Container>
  );
};

export default CreateChangeRequestPage;

