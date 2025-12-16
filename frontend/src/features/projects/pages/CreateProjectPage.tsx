import React, { useState, useCallback, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Input } from '../../../components/ui/primitives/Input';
import { useCreateProject } from '../hooks';
import type { Project } from '../types';
import { templatesApi, type TemplateSet, type TemplatePreviewResult } from '../../_archived/templates-2025-01/api';
import { TemplateSelectionTabs } from '../../_archived/templates-2025-01/components/TemplateSelectionTabs';
import { TemplatePreviewPanel } from '../../_archived/templates-2025-01/components/TemplatePreviewPanel';

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

type WizardStep = 'project-info' | 'template-selection' | 'review';

export const CreateProjectPage: React.FC = () => {
  const navigate = useNavigate();
  const createProject = useCreateProject();
  
  const [currentStep, setCurrentStep] = useState<WizardStep>('project-info');
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

  // Template selection state
  const [templateSets, setTemplateSets] = useState<TemplateSet[]>([]);
  const [selectedTemplateSet, setSelectedTemplateSet] = useState<TemplateSet | null>(null);
  const [selectedPreset, setSelectedPreset] = useState<string | null>(null);
  const [selectedPhases, setSelectedPhases] = useState<string[]>([]);
  const [selectedDisciplines, setSelectedDisciplines] = useState<string[]>([]);
  const [selectedTasks, setSelectedTasks] = useState<string[]>([]);
  const [templateOptions, setTemplateOptions] = useState({
    mapPhaseToKanban: false,
    autoAssignByRole: false,
    createDeliverableFolders: false,
  });
  const [preview, setPreview] = useState<TemplatePreviewResult | null>(null);
  const [isLoadingPreview, setIsLoadingPreview] = useState(false);
  const [isApplyingTemplate, setIsApplyingTemplate] = useState(false);
  const [createdProjectId, setCreatedProjectId] = useState<string | null>(null);

  // Load template sets on mount (only if feature is enabled)
  useEffect(() => {
    const loadTemplates = async () => {
      try {
        // Check if feature is enabled via API or config
        // For now, we'll try to load and handle errors gracefully
        console.log('[CreateProjectPage] Loading template sets...');
        
        // Check if token exists
        if (typeof window !== 'undefined') {
          const token = window.localStorage.getItem('auth_token');
          console.log('[CreateProjectPage] Auth token exists:', !!token);
        }
        
        const response = await templatesApi.getTemplates();
        console.log('[CreateProjectPage] Template sets loaded:', response.data?.length || 0, 'sets');
        if (response.data && response.data.length > 0) {
          console.log('[CreateProjectPage] Template sets:', response.data.map((ts: any) => ts.name || ts.code));
        }
        setTemplateSets(response.data || []);
      } catch (error: any) {
        // If feature is disabled (403), don't show template step
        const status = error?.response?.status;
        const statusText = error?.response?.statusText;
        const errorMessage = error?.message;
        
        console.error('[CreateProjectPage] Failed to load templates:', {
          status,
          statusText,
          message: errorMessage,
          response: error?.response?.data,
        });
        
        if (status === 403) {
          console.log('[CreateProjectPage] Task Templates feature is disabled (403)');
        } else if (status === 401) {
          console.log('[CreateProjectPage] Authentication failed (401) - user may not be logged in or token expired');
        } else {
          console.log('[CreateProjectPage] API error:', status, statusText);
        }
        
        setTemplateSets([]);
      }
    };
    loadTemplates();
  }, []);

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

  // Handle project info form submission
  const handleProjectInfoSubmit = useCallback(async (e: React.FormEvent) => {
    e.preventDefault();
    
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
      
      const result = await createProject.mutateAsync(payload);
      
      // Store project ID for template application
      if (result?.data?.id) {
        setCreatedProjectId(String(result.data.id));
        console.log('[CreateProjectPage] Project created, ID:', result.data.id);
        console.log('[CreateProjectPage] Available template sets:', templateSets.length);
        // Move to template selection step if templates are available
        if (templateSets.length > 0) {
          console.log('[CreateProjectPage] Moving to template selection step');
          setCurrentStep('template-selection');
        } else {
          // No templates, go directly to project
          console.log('[CreateProjectPage] No templates available, navigating to project');
          navigate(`/app/projects/${result.data.id}`);
        }
      } else {
        navigate('/app/projects');
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
        setErrors({ name: error?.message || 'Failed to create project. Please try again.' });
      }
    } finally {
      setIsSubmitting(false);
    }
  }, [formData, validateForm, createProject, navigate, templateSets]);

  // Handle preview generation
  const handlePreview = useCallback(async () => {
    if (!selectedTemplateSet || !createdProjectId) return;

    setIsLoadingPreview(true);
    try {
      const result = await templatesApi.previewTemplate({
        set_id: selectedTemplateSet.id,
        project_id: createdProjectId,
        preset_code: selectedPreset || undefined,
        selections: {
          phases: selectedPhases.length > 0 ? selectedPhases : undefined,
          disciplines: selectedDisciplines.length > 0 ? selectedDisciplines : undefined,
          tasks: selectedTasks.length > 0 ? selectedTasks : undefined,
        },
        options: templateOptions,
      });
      setPreview(result.data);
    } catch (error) {
      console.error('Failed to generate preview:', error);
      setPreview(null);
    } finally {
      setIsLoadingPreview(false);
    }
  }, [selectedTemplateSet, createdProjectId, selectedPreset, selectedPhases, selectedDisciplines, selectedTasks, templateOptions]);

  // Handle template application
  const handleApplyTemplate = useCallback(async () => {
    if (!selectedTemplateSet || !createdProjectId || !preview) return;

    setIsApplyingTemplate(true);
    try {
      await templatesApi.applyTemplate(createdProjectId, {
        set_id: selectedTemplateSet.id,
        preset_code: selectedPreset || undefined,
        selections: {
          phases: selectedPhases.length > 0 ? selectedPhases : undefined,
          disciplines: selectedDisciplines.length > 0 ? selectedDisciplines : undefined,
          tasks: selectedTasks.length > 0 ? selectedTasks : undefined,
        },
        options: {
          ...templateOptions,
          conflict_behavior: 'skip',
        },
      });
      
      // Success - redirect to project
      navigate(`/app/projects/${createdProjectId}`);
    } catch (error) {
      console.error('Failed to apply template:', error);
      alert('Failed to apply template. Project created but template was not applied.');
      navigate(`/app/projects/${createdProjectId}`);
    } finally {
      setIsApplyingTemplate(false);
    }
  }, [selectedTemplateSet, createdProjectId, selectedPreset, selectedPhases, selectedDisciplines, selectedTasks, templateOptions, preview, navigate]);

  // Skip template step
  const handleSkipTemplate = useCallback(() => {
    if (createdProjectId) {
      navigate(`/app/projects/${createdProjectId}`);
    } else {
      navigate('/app/projects');
    }
  }, [createdProjectId, navigate]);

  const handleCancel = useCallback(() => {
    navigate('/app/projects');
  }, [navigate]);

  // Render template selection step
  if (currentStep === 'template-selection') {
    return (
      <Container>
        <div className="space-y-6" data-testid="template-selection-step">
          {/* Page Header */}
          <div>
            <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]" data-testid="template-selection-heading">
              Choose Template (Optional)
            </h1>
            <p className="text-[var(--font-body-size)] text-[var(--muted)] mt-1">
              Select a template to automatically create tasks for your project
            </p>
          </div>

          {/* Template Selection */}
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div className="lg:col-span-2">
              {/* Template Set Selection */}
              <Card className="mb-6">
                <CardHeader>
                  <CardTitle>Select Template Set</CardTitle>
                </CardHeader>
                <CardContent>
                  {templateSets.length === 0 ? (
                    <p className="text-gray-500 text-center py-4">No templates available</p>
                  ) : (
                    <div className="space-y-2">
                      {templateSets.map((set) => (
                        <label
                          key={set.id}
                          className="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50"
                        >
                          <input
                            type="radio"
                            name="template"
                            checked={selectedTemplateSet?.id === set.id}
                            onChange={() => {
                              setSelectedTemplateSet(set);
                              setSelectedPreset(null);
                              setSelectedPhases([]);
                              setSelectedDisciplines([]);
                              setSelectedTasks([]);
                              setPreview(null);
                            }}
                            className="mr-3"
                          />
                          <div className="flex-1">
                            <div className="font-medium text-gray-900">{set.name}</div>
                            {set.description && (
                              <div className="text-sm text-gray-500 mt-1">{set.description}</div>
                            )}
                            <div className="text-xs text-gray-400 mt-1">
                              {set.code} • v{set.version} • {set.is_global ? 'Global' : 'Tenant'}
                            </div>
                          </div>
                        </label>
                      ))}
                    </div>
                  )}
                </CardContent>
              </Card>

              {/* Template Selection Tabs */}
              {selectedTemplateSet && (
                <TemplateSelectionTabs
                  templateSet={selectedTemplateSet}
                  selectedPreset={selectedPreset || undefined}
                  selectedPhases={selectedPhases}
                  selectedDisciplines={selectedDisciplines}
                  selectedTasks={selectedTasks}
                  onPresetChange={setSelectedPreset}
                  onPhasesChange={setSelectedPhases}
                  onDisciplinesChange={setSelectedDisciplines}
                  onTasksChange={setSelectedTasks}
                  options={templateOptions}
                  onOptionsChange={setTemplateOptions}
                />
              )}
            </div>

            {/* Preview Panel */}
            <div>
              <TemplatePreviewPanel
                preview={preview}
                isLoading={isLoadingPreview}
                onApply={handleApplyTemplate}
                isApplying={isApplyingTemplate}
              />
              {selectedTemplateSet && !preview && (
                <div className="mt-4">
                  <Button
                    variant="secondary"
                    onClick={handlePreview}
                    disabled={isLoadingPreview}
                    style={{ width: '100%' }}
                  >
                    Generate Preview
                  </Button>
                </div>
              )}
            </div>
          </div>

          {/* Actions */}
          <div className="flex items-center justify-end gap-3">
            <Button
              type="button"
              variant="secondary"
              onClick={handleSkipTemplate}
              disabled={isApplyingTemplate}
            >
              Skip Template
            </Button>
            {preview && (
              <Button
                type="button"
                variant="primary"
                onClick={handleApplyTemplate}
                disabled={isApplyingTemplate || preview.total_tasks === 0}
              >
                {isApplyingTemplate ? 'Applying...' : 'Apply Template & Continue'}
              </Button>
            )}
          </div>
        </div>
      </Container>
    );
  }

  return (
    <Container>
      <div className="space-y-6">
        {/* Page Header */}
        <div>
          <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]">
            Create New Project
          </h1>
          <p className="text-[var(--font-body-size)] text-[var(--muted)] mt-1">
            Fill in the details below to create a new project
          </p>
        </div>

        {/* Form */}
        <form onSubmit={handleProjectInfoSubmit}>
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
                  {isSubmitting ? 'Creating...' : 'Create Project'}
                </Button>
              </div>
            </CardContent>
          </Card>
        </form>
      </div>
    </Container>
  );
};

export default CreateProjectPage;
