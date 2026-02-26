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
import { DatePicker } from '@/components/ui/DatePicker';
import { LoadingSpinner } from '@/components/ui/loading-spinner';
import { 
  Save, 
  X, 
  Calendar, 
  DollarSign, 
  FileText, 
  AlertCircle 
} from 'lucide-react';
import { useProjectStore } from '@/store/projects';
import { CreateProjectForm, Project } from '@/lib/types';
import { cn } from '@/lib/utils';

interface ProjectFormProps {
  project?: Project;
  onSubmit: (data: CreateProjectForm) => Promise<void>;
  onCancel: () => void;
  isLoading?: boolean;
}

const ProjectForm: React.FC<ProjectFormProps> = ({ 
  project, 
  onSubmit, 
  onCancel, 
  isLoading = false 
}) => {
  const [formData, setFormData] = useState<CreateProjectForm>({
    name: '',
    description: '',
    start_date: '',
    end_date: '',
    planned_cost: 0
  });
  
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [touched, setTouched] = useState<Record<string, boolean>>({});

  // Populate form data nếu đang edit
  useEffect(() => {
    if (project) {
      setFormData({
        name: project.name,
        description: project.description || '',
        start_date: project.start_date,
        end_date: project.end_date || '',
        planned_cost: project.planned_cost || 0
      });
    }
  }, [project]);

  // Validation rules
  const validateField = (name: string, value: any) => {
    switch (name) {
      case 'name':
        if (!value || value.trim().length < 3) {
          return 'Tên dự án phải có ít nhất 3 ký tự';
        }
        break;
      case 'start_date':
        if (!value) {
          return 'Ngày bắt đầu là bắt buộc';
        }
        break;
      case 'end_date':
        if (value && formData.start_date && new Date(value) <= new Date(formData.start_date)) {
          return 'Ngày kết thúc phải sau ngày bắt đầu';
        }
        break;
      case 'planned_cost':
        if (value && value < 0) {
          return 'Chi phí dự kiến không thể âm';
        }
        break;
    }
    return '';
  };

  // Handle input change
  const handleChange = (name: string, value: any) => {
    setFormData(prev => ({ ...prev, [name]: value }));
    
    // Validate field
    const error = validateField(name, value);
    setErrors(prev => ({ ...prev, [name]: error }));
  };

  // Handle blur
  const handleBlur = (name: string) => {
    setTouched(prev => ({ ...prev, [name]: true }));
  };

  // Validate entire form
  const validateForm = () => {
    const newErrors: Record<string, string> = {};
    
    Object.keys(formData).forEach(key => {
      const error = validateField(key, formData[key as keyof CreateProjectForm]);
      if (error) {
        newErrors[key] = error;
      }
    });
    
    setErrors(newErrors);
    setTouched({
      name: true,
      description: true,
      start_date: true,
      end_date: true,
      planned_cost: true
    });
    
    return Object.keys(newErrors).length === 0;
  };

  // Handle submit
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }
    
    try {
      await onSubmit(formData);
    } catch (error) {
      console.error('Error submitting form:', error);
    }
  };

  const isFormValid = Object.values(errors).every(error => !error) && 
                     formData.name.trim().length >= 3 && 
                     formData.start_date;

  return (
    <Card className="w-full max-w-2xl mx-auto">
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <FileText className="w-5 h-5" />
          {project ? 'Chỉnh sửa dự án' : 'Tạo dự án mới'}
        </CardTitle>
      </CardHeader>
      
      <form onSubmit={handleSubmit}>
        <CardContent className="space-y-6">
          {/* Tên dự án */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-700">
              Tên dự án <span className="text-red-500">*</span>
            </label>
            <Input
              value={formData.name}
              onChange={(e) => handleChange('name', e.target.value)}
              onBlur={() => handleBlur('name')}
              placeholder="Nhập tên dự án..."
              className={cn(
                touched.name && errors.name && 'border-red-500 focus:border-red-500'
              )}
            />
            {touched.name && errors.name && (
              <p className="text-sm text-red-600 flex items-center gap-1">
                <AlertCircle className="w-4 h-4" />
                {errors.name}
              </p>
            )}
          </div>

          {/* Mô tả */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-700">
              Mô tả dự án
            </label>
            <Textarea
              value={formData.description}
              onChange={(e) => handleChange('description', e.target.value)}
              onBlur={() => handleBlur('description')}
              placeholder="Mô tả chi tiết về dự án..."
              rows={4}
              className={cn(
                touched.description && errors.description && 'border-red-500 focus:border-red-500'
              )}
            />
            {touched.description && errors.description && (
              <p className="text-sm text-red-600 flex items-center gap-1">
                <AlertCircle className="w-4 h-4" />
                {errors.description}
              </p>
            )}
          </div>

          {/* Ngày bắt đầu và kết thúc */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-700">
                Ngày bắt đầu <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                <Input
                  type="date"
                  value={formData.start_date}
                  onChange={(e) => handleChange('start_date', e.target.value)}
                  onBlur={() => handleBlur('start_date')}
                  className={cn(
                    'pl-10',
                    touched.start_date && errors.start_date && 'border-red-500 focus:border-red-500'
                  )}
                />
              </div>
              {touched.start_date && errors.start_date && (
                <p className="text-sm text-red-600 flex items-center gap-1">
                  <AlertCircle className="w-4 h-4" />
                  {errors.start_date}
                </p>
              )}
            </div>

            <div className="space-y-2">
              <label className="text-sm font-medium text-gray-700">
                Ngày kết thúc
              </label>
              <div className="relative">
                <Calendar className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                <Input
                  type="date"
                  value={formData.end_date}
                  onChange={(e) => handleChange('end_date', e.target.value)}
                  onBlur={() => handleBlur('end_date')}
                  className={cn(
                    'pl-10',
                    touched.end_date && errors.end_date && 'border-red-500 focus:border-red-500'
                  )}
                />
              </div>
              {touched.end_date && errors.end_date && (
                <p className="text-sm text-red-600 flex items-center gap-1">
                  <AlertCircle className="w-4 h-4" />
                  {errors.end_date}
                </p>
              )}
            </div>
          </div>

          {/* Chi phí dự kiến */}
          <div className="space-y-2">
            <label className="text-sm font-medium text-gray-700">
              Chi phí dự kiến (VNĐ)
            </label>
            <div className="relative">
              <DollarSign className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
              <Input
                type="number"
                value={formData.planned_cost}
                onChange={(e) => handleChange('planned_cost', parseFloat(e.target.value) || 0)}
                onBlur={() => handleBlur('planned_cost')}
                placeholder="0"
                min="0"
                step="1000"
                className={cn(
                  'pl-10',
                  touched.planned_cost && errors.planned_cost && 'border-red-500 focus:border-red-500'
                )}
              />
            </div>
            {touched.planned_cost && errors.planned_cost && (
              <p className="text-sm text-red-600 flex items-center gap-1">
                <AlertCircle className="w-4 h-4" />
                {errors.planned_cost}
              </p>
            )}
          </div>
        </CardContent>

        <CardFooter className="flex justify-end gap-3">
          <Button
            type="button"
            variant="outline"
            onClick={onCancel}
            disabled={isLoading}
          >
            <X className="w-4 h-4 mr-2" />
            Hủy
          </Button>
          <Button
            type="submit"
            disabled={!isFormValid || isLoading}
            className="min-w-[120px]"
          >
            {isLoading ? (
              <LoadingSpinner size="sm" className="mr-2" />
            ) : (
              <Save className="w-4 h-4 mr-2" />
            )}
            {project ? 'Cập nhật' : 'Tạo mới'}
          </Button>
        </CardFooter>
      </form>
    </Card>
  );
};

export default ProjectForm;