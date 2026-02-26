import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { 
  Card, 
  CardHeader, 
  CardTitle, 
  CardContent, 
  CardFooter 
} from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Textarea } from '@/components/ui/Textarea';
import { Select } from '@/components/ui/Select';
import { LoadingSpinner } from '@/components/ui/loading-spinner';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
  Save, 
  X, 
  Building2, 
  DollarSign, 
  Percent, 
  AlertCircle,
  TreePine
} from 'lucide-react';
import { Component, CreateComponentRequest, UpdateComponentRequest } from '../types/component';
import { useComponents } from '../hooks/useComponents';
import { validateCreateComponent, validateUpdateComponent } from '../validations/componentValidation';
import { cn } from '@/lib/utils';
import { formatCurrency } from '@/lib/utils';

interface ComponentFormProps {
  projectId: string;
  component?: Component;
  parentComponent?: Component;
  onSubmit?: (data: CreateComponentRequest | UpdateComponentRequest) => Promise<void>;
  onCancel?: () => void;
  isLoading?: boolean;
  mode?: 'create' | 'edit';
  className?: string;
}

export const ComponentForm: React.FC<ComponentFormProps> = ({ 
  projectId,
  component, 
  parentComponent,
  onSubmit, 
  onCancel, 
  isLoading = false,
  mode = component ? 'edit' : 'create',
  className
}) => {
  const navigate = useNavigate();
  const { 
    createComponent, 
    updateComponent,
    getComponentsList 
  } = useComponents(projectId);

  // Form state
  const [formData, setFormData] = useState<CreateComponentRequest | UpdateComponentRequest>({
    name: component?.name || '',
    parent_component_id: parentComponent?.id || component?.parent_component_id || undefined,
    planned_cost: component?.planned_cost || 0,
    progress_percent: component?.progress_percent || 0,
    ...(mode === 'edit' && { actual_cost: component?.actual_cost || 0 })
  });
  
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [touched, setTouched] = useState<Record<string, boolean>>({});
  const [submitError, setSubmitError] = useState<string>('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Get available parent components for selection
  const { data: componentsData } = getComponentsList({
    filters: {},
    enabled: true
  });

  const availableParents = componentsData?.data?.filter(comp => 
    comp.id !== component?.id && // Exclude self
    !isDescendant(comp.id, component?.id) // Exclude descendants to prevent circular reference
  ) || [];

  // Helper function to check if a component is a descendant of another
  const isDescendant = (potentialParentId: string, componentId?: string): boolean => {
    if (!componentId || !componentsData?.data) return false;
    
    const findDescendants = (parentId: string): string[] => {
      const children = componentsData.data.filter(comp => comp.parent_component_id === parentId);
      let descendants = children.map(child => child.id);
      
      children.forEach(child => {
        descendants = [...descendants, ...findDescendants(child.id)];
      });
      
      return descendants;
    };
    
    return findDescendants(componentId).includes(potentialParentId);
  };

  // Validation
  const validateForm = () => {
    try {
      if (mode === 'create') {
        validateCreateComponent(formData as CreateComponentRequest);
      } else {
        validateUpdateComponent(formData as UpdateComponentRequest);
      }
      setErrors({});
      return true;
    } catch (error: any) {
      if (error.errors) {
        const formattedErrors: Record<string, string> = {};
        error.errors.forEach((err: any) => {
          const field = err.path[0];
          formattedErrors[field] = err.message;
        });
        setErrors(formattedErrors);
      }
      return false;
    }
  };

  // Handle input changes
  const handleInputChange = (field: string, value: any) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    setTouched(prev => ({ ...prev, [field]: true }));
    
    // Clear error for this field
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  // Handle form submission
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }

    setIsSubmitting(true);
    setSubmitError('');

    try {
      if (onSubmit) {
        await onSubmit(formData);
      } else {
        if (mode === 'create') {
          await createComponent.mutateAsync({
            projectId,
            data: formData as CreateComponentRequest
          });
        } else if (component) {
          await updateComponent.mutateAsync({
            projectId,
            componentId: component.id,
            data: formData as UpdateComponentRequest
          });
        }
        
        // Navigate back or call onCancel
        if (onCancel) {
          onCancel();
        } else {
          navigate(`/app/projects/${projectId}/components`);
        }
      }
    } catch (error: any) {
      setSubmitError(
        error.response?.data?.message || 
        error.message || 
        'Có lỗi xảy ra khi lưu component'
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  // Handle cancel
  const handleCancel = () => {
    if (onCancel) {
      onCancel();
    } else {
      navigate(`/app/projects/${projectId}/components`);
    }
  };

  const isFormLoading = isLoading || isSubmitting;

  return (
    <Card className={cn('w-full max-w-2xl mx-auto', className)}>
      <CardHeader>
        <CardTitle className="flex items-center space-x-2">
          <Building2 className="h-5 w-5" />
          <span>
            {mode === 'create' ? 'Tạo Component Mới' : 'Chỉnh Sửa Component'}
          </span>
        </CardTitle>
        {parentComponent && (
          <p className="text-sm text-gray-600 flex items-center space-x-1">
            <TreePine className="h-4 w-4" />
            <span>Component con của: <strong>{parentComponent.name}</strong></span>
          </p>
        )}
      </CardHeader>

      <form onSubmit={handleSubmit}>
        <CardContent className="space-y-6">
          {/* Submit Error Alert */}
          {submitError && (
            <Alert variant="destructive">
              <AlertCircle className="h-4 w-4" />
              <AlertDescription>{submitError}</AlertDescription>
            </Alert>
          )}

          {/* Component Name */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-700">
              Tên Component *
            </label>
            <Input
              value={formData.name}
              onChange={(e) => handleInputChange('name', e.target.value)}
              placeholder="Nhập tên component..."
              error={touched.name && errors.name}
              disabled={isFormLoading}
            />
            {touched.name && errors.name && (
              <p className="text-sm text-red-600">{errors.name}</p>
            )}
          </div>

          {/* Parent Component Selection */}
          {mode === 'create' && (
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-700">
                Component Cha
              </label>
              <Select
                value={formData.parent_component_id || ''}
                onValueChange={(value) => 
                  handleInputChange('parent_component_id', value || undefined)
                }
                disabled={isFormLoading || !!parentComponent}
              >
                <option value="">-- Không có component cha --</option>
                {availableParents.map((comp) => (
                  <option key={comp.id} value={comp.id}>
                    {comp.name} ({formatCurrency(comp.planned_cost)})
                  </option>
                ))}
              </Select>
              {touched.parent_component_id && errors.parent_component_id && (
                <p className="text-sm text-red-600">{errors.parent_component_id}</p>
              )}
            </div>
          )}

          {/* Planned Cost */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-700 flex items-center space-x-1">
              <DollarSign className="h-4 w-4" />
              <span>Chi Phí Kế Hoạch *</span>
            </label>
            <Input
              type="number"
              value={formData.planned_cost}
              onChange={(e) => handleInputChange('planned_cost', parseFloat(e.target.value) || 0)}
              placeholder="0"
              min="0"
              step="0.01"
              error={touched.planned_cost && errors.planned_cost}
              disabled={isFormLoading}
            />
            {touched.planned_cost && errors.planned_cost && (
              <p className="text-sm text-red-600">{errors.planned_cost}</p>
            )}
          </div>

          {/* Progress Percent */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-700 flex items-center space-x-1">
              <Percent className="h-4 w-4" />
              <span>Tiến Độ (%)</span>
            </label>
            <Input
              type="number"
              value={formData.progress_percent}
              onChange={(e) => handleInputChange('progress_percent', parseFloat(e.target.value) || 0)}
              placeholder="0"
              min="0"
              max="100"
              step="0.1"
              error={touched.progress_percent && errors.progress_percent}
              disabled={isFormLoading}
            />
            {touched.progress_percent && errors.progress_percent && (
              <p className="text-sm text-red-600">{errors.progress_percent}</p>
            )}
          </div>

          {/* Actual Cost (Edit mode only) */}
          {mode === 'edit' && (
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-700 flex items-center space-x-1">
                <DollarSign className="h-4 w-4" />
                <span>Chi Phí Thực Tế</span>
              </label>
              <Input
                type="number"
                value={(formData as UpdateComponentRequest).actual_cost || 0}
                onChange={(e) => handleInputChange('actual_cost', parseFloat(e.target.value) || 0)}
                placeholder="0"
                min="0"
                step="0.01"
                error={touched.actual_cost && errors.actual_cost}
                disabled={isFormLoading}
              />
              {touched.actual_cost && errors.actual_cost && (
                <p className="text-sm text-red-600">{errors.actual_cost}</p>
              )}
            </div>
          )}

          {/* Cost Summary */}
          {formData.planned_cost > 0 && (
            <div className="bg-gray-50 rounded-lg p-4 space-y-2">
              <h4 className="font-medium text-gray-700">Tóm tắt chi phí</h4>
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span className="text-gray-600">Kế hoạch:</span>
                  <span className="ml-2 font-semibold">
                    {formatCurrency(formData.planned_cost)}
                  </span>
                </div>
                {mode === 'edit' && (formData as UpdateComponentRequest).actual_cost !== undefined && (
                  <div>
                    <span className="text-gray-600">Thực tế:</span>
                    <span className="ml-2 font-semibold">
                      {formatCurrency((formData as UpdateComponentRequest).actual_cost || 0)}
                    </span>
                  </div>
                )}
                <div>
                  <span className="text-gray-600">Tiến độ:</span>
                  <span className="ml-2 font-semibold">
                    {formData.progress_percent}%
                  </span>
                </div>
                {mode === 'edit' && (formData as UpdateComponentRequest).actual_cost !== undefined && (
                  <div>
                    <span className="text-gray-600">Chênh lệch:</span>
                    <span className={cn(
                      'ml-2 font-semibold',
                      ((formData as UpdateComponentRequest).actual_cost || 0) > formData.planned_cost
                        ? 'text-red-600'
                        : 'text-green-600'
                    )}>
                      {formatCurrency(
                        Math.abs(((formData as UpdateComponentRequest).actual_cost || 0) - formData.planned_cost)
                      )}
                    </span>
                  </div>
                )}
              </div>
            </div>
          )}
        </CardContent>

        <CardFooter className="flex justify-end space-x-3">
          <Button
            type="button"
            variant="outline"
            onClick={handleCancel}
            disabled={isFormLoading}
          >
            <X className="h-4 w-4 mr-2" />
            Hủy
          </Button>
          <Button
            type="submit"
            disabled={isFormLoading}
            className="min-w-[120px]"
          >
            {isFormLoading ? (
              <LoadingSpinner size="sm" />
            ) : (
              <>
                <Save className="h-4 w-4 mr-2" />
                {mode === 'create' ? 'Tạo Component' : 'Cập Nhật'}
              </>
            )}
          </Button>
        </CardFooter>
      </form>
    </Card>
  );
};