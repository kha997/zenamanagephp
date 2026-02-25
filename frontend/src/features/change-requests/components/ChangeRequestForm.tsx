import React, { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { 
  CreateChangeRequestData, 
  UpdateChangeRequestData, 
  ChangeRequest 
} from '../types/changeRequest';
import { 
  createChangeRequestSchema, 
  updateChangeRequestSchema 
} from '../validations/changeRequestValidation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/Input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
  PlusIcon, 
  MinusIcon, 
  InformationCircleIcon,
  CurrencyDollarIcon,
  ClockIcon,
  ChartBarIcon
} from '@heroicons/react/24/outline';
import { formatCurrency } from '@/lib/utils';

interface ChangeRequestFormProps {
  initialData?: ChangeRequest;
  onSubmit: (data: CreateChangeRequestData | UpdateChangeRequestData) => Promise<void>;
  onCancel: () => void;
  isLoading?: boolean;
  mode?: 'create' | 'edit';
  projectId: string;
}

export const ChangeRequestForm: React.FC<ChangeRequestFormProps> = ({
  initialData,
  onSubmit,
  onCancel,
  isLoading = false,
  mode = initialData ? 'edit' : 'create',
  projectId
}) => {
  const [kpiEntries, setKpiEntries] = useState<Array<{ key: string; value: string; id: string }>>([]);
  const [submitError, setSubmitError] = useState<string | null>(null);

  // Chọn schema phù hợp dựa trên mode
  const schema = mode === 'edit' ? updateChangeRequestSchema : createChangeRequestSchema;
  
  const {
    register,
    handleSubmit,
    formState: { errors, isValid, isDirty },
    setValue,
    watch,
    reset,
    clearErrors
  } = useForm<CreateChangeRequestData | UpdateChangeRequestData>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: {
      title: initialData?.title || '',
      description: initialData?.description || '',
      impact_days: initialData?.impact_days || 0,
      impact_cost: initialData?.impact_cost || 0,
      impact_kpi: initialData?.impact_kpi || {}
    }
  });

  const watchedValues = watch();

  // Khởi tạo KPI entries từ dữ liệu ban đầu
  useEffect(() => {
    if (initialData?.impact_kpi) {
      const entries = Object.entries(initialData.impact_kpi).map(([key, value], index) => ({
        id: `kpi-${index}`,
        key,
        value: String(value)
      }));
      setKpiEntries(entries);
    }
  }, [initialData]);

  // Thêm KPI entry mới
  const addKpiEntry = () => {
    const newId = `kpi-${Date.now()}`;
    setKpiEntries(prev => [...prev, { id: newId, key: '', value: '' }]);
  };

  // Xóa KPI entry
  const removeKpiEntry = (id: string) => {
    const newEntries = kpiEntries.filter(entry => entry.id !== id);
    setKpiEntries(newEntries);
    updateKpiValue(newEntries);
  };

  // Cập nhật KPI entry
  const updateKpiEntry = (id: string, field: 'key' | 'value', value: string) => {
    const newEntries = kpiEntries.map(entry => 
      entry.id === id ? { ...entry, [field]: value } : entry
    );
    setKpiEntries(newEntries);
    updateKpiValue(newEntries);
  };

  // Cập nhật giá trị impact_kpi trong form
  const updateKpiValue = (entries: Array<{ key: string; value: string; id: string }>) => {
    const kpiObject = entries.reduce((acc, entry) => {
      if (entry.key.trim() && entry.value.trim()) {
        acc[entry.key.trim()] = entry.value.trim();
      }
      return acc;
    }, {} as Record<string, string>);
    setValue('impact_kpi', kpiObject, { shouldValidate: true });
  };

  const onFormSubmit = async (data: CreateChangeRequestData | UpdateChangeRequestData) => {
    try {
      setSubmitError(null);
      await onSubmit(data);
    } catch (error) {
      console.error('Error submitting form:', error);
      setSubmitError(
        error instanceof Error 
          ? error.message 
          : 'Có lỗi xảy ra khi xử lý yêu cầu. Vui lòng thử lại.'
      );
    }
  };

  const handleReset = () => {
    reset();
    setKpiEntries([]);
    setSubmitError(null);
    clearErrors();
  };

  return (
    <Card className="w-full max-w-4xl mx-auto shadow-lg">
      <CardHeader className="bg-gradient-to-r from-blue-50 to-indigo-50 border-b">
        <CardTitle className="flex items-center gap-2 text-xl">
          <ChartBarIcon className="h-6 w-6 text-blue-600" />
          {mode === 'edit' ? 'Chỉnh sửa Change Request' : 'Tạo Change Request mới'}
        </CardTitle>
        {mode === 'create' && (
          <p className="text-sm text-gray-600 mt-2">
            Tạo yêu cầu thay đổi mới cho dự án. Vui lòng điền đầy đủ thông tin bên dưới.
          </p>
        )}
      </CardHeader>
      
      <CardContent className="p-6">
        {submitError && (
          <Alert className="mb-6 border-red-200 bg-red-50">
            <InformationCircleIcon className="h-4 w-4 text-red-600" />
            <AlertDescription className="text-red-800">
              {submitError}
            </AlertDescription>
          </Alert>
        )}

        <form onSubmit={handleSubmit(onFormSubmit)} className="space-y-8">
          {/* Thông tin cơ bản */}
          <div className="space-y-6">
            <h3 className="text-lg font-semibold text-gray-900 border-b pb-2">
              Thông tin cơ bản
            </h3>
            
            {/* Tiêu đề */}
            <div className="space-y-2">
              <Label htmlFor="title" className="text-sm font-medium text-gray-700">
                Tiêu đề <span className="text-red-500">*</span>
              </Label>
              <Input
                id="title"
                {...register('title')}
                placeholder="Nhập tiêu đề ngắn gọn và mô tả rõ ràng..."
                className={`transition-colors ${
                  errors.title 
                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500' 
                    : 'focus:border-blue-500 focus:ring-blue-500'
                }`}
                aria-describedby={errors.title ? 'title-error' : undefined}
              />
              {errors.title && (
                <p id="title-error" className="text-sm text-red-600 flex items-center gap-1">
                  <InformationCircleIcon className="h-4 w-4" />
                  {errors.title.message}
                </p>
              )}
            </div>

            {/* Mô tả */}
            <div className="space-y-2">
              <Label htmlFor="description" className="text-sm font-medium text-gray-700">
                Mô tả chi tiết <span className="text-red-500">*</span>
              </Label>
              <Textarea
                id="description"
                {...register('description')}
                placeholder="Mô tả chi tiết về change request, lý do thay đổi, và tác động dự kiến..."
                rows={5}
                className={`transition-colors resize-none ${
                  errors.description 
                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500' 
                    : 'focus:border-blue-500 focus:ring-blue-500'
                }`}
                aria-describedby={errors.description ? 'description-error' : undefined}
              />
              {errors.description && (
                <p id="description-error" className="text-sm text-red-600 flex items-center gap-1">
                  <InformationCircleIcon className="h-4 w-4" />
                  {errors.description.message}
                </p>
              )}
            </div>
          </div>

          {/* Tác động dự án */}
          <div className="space-y-6">
            <h3 className="text-lg font-semibold text-gray-900 border-b pb-2">
              Tác động dự án
            </h3>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Số ngày tác động */}
              <div className="space-y-2">
                <Label htmlFor="impact_days" className="text-sm font-medium text-gray-700 flex items-center gap-2">
                  <ClockIcon className="h-4 w-4 text-orange-500" />
                  Số ngày tác động
                </Label>
                <Input
                  id="impact_days"
                  type="number"
                  min="0"
                  step="1"
                  {...register('impact_days', { valueAsNumber: true })}
                  placeholder="0"
                  className={`transition-colors ${
                    errors.impact_days 
                      ? 'border-red-500 focus:border-red-500 focus:ring-red-500' 
                      : 'focus:border-blue-500 focus:ring-blue-500'
                  }`}
                  aria-describedby={errors.impact_days ? 'impact-days-error' : 'impact-days-help'}
                />
                {errors.impact_days ? (
                  <p id="impact-days-error" className="text-sm text-red-600 flex items-center gap-1">
                    <InformationCircleIcon className="h-4 w-4" />
                    {errors.impact_days.message}
                  </p>
                ) : (
                  <p id="impact-days-help" className="text-xs text-gray-500">
                    Số ngày dự kiến sẽ bị ảnh hưởng bởi thay đổi này
                  </p>
                )}
              </div>

              {/* Chi phí tác động */}
              <div className="space-y-2">
                <Label htmlFor="impact_cost" className="text-sm font-medium text-gray-700 flex items-center gap-2">
                  <CurrencyDollarIcon className="h-4 w-4 text-green-500" />
                  Chi phí tác động (VND)
                </Label>
                <Input
                  id="impact_cost"
                  type="number"
                  min="0"
                  step="1000"
                  {...register('impact_cost', { valueAsNumber: true })}
                  placeholder="0"
                  className={`transition-colors ${
                    errors.impact_cost 
                      ? 'border-red-500 focus:border-red-500 focus:ring-red-500' 
                      : 'focus:border-blue-500 focus:ring-blue-500'
                  }`}
                  aria-describedby={errors.impact_cost ? 'impact-cost-error' : 'impact-cost-help'}
                />
                {errors.impact_cost ? (
                  <p id="impact-cost-error" className="text-sm text-red-600 flex items-center gap-1">
                    <InformationCircleIcon className="h-4 w-4" />
                    {errors.impact_cost.message}
                  </p>
                ) : (
                  <div className="space-y-1">
                    <p className="text-xs text-gray-500">
                      Chi phí dự kiến sẽ phát sinh từ thay đổi này
                    </p>
                    {watchedValues.impact_cost > 0 && (
                      <p className="text-xs text-blue-600 font-medium">
                        ≈ {formatCurrency(watchedValues.impact_cost)}
                      </p>
                    )}
                  </div>
                )}
              </div>
            </div>
          </div>

          {/* Tác động KPI */}
          <div className="space-y-6">
            <div className="flex items-center justify-between">
              <h3 className="text-lg font-semibold text-gray-900 border-b pb-2 flex-1">
                Tác động KPI
              </h3>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={addKpiEntry}
                className="flex items-center gap-2 hover:bg-blue-50 hover:border-blue-300"
              >
                <PlusIcon className="h-4 w-4" />
                Thêm KPI
              </Button>
            </div>

            {kpiEntries.length === 0 ? (
              <div className="text-center py-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
                <ChartBarIcon className="h-12 w-12 text-gray-400 mx-auto mb-3" />
                <p className="text-gray-500 text-sm">
                  Chưa có KPI nào được thêm. Nhấn "Thêm KPI" để bắt đầu.
                </p>
              </div>
            ) : (
              <div className="space-y-4">
                {kpiEntries.map((entry) => (
                  <div key={entry.id} className="flex items-center gap-3 p-4 bg-gray-50 rounded-lg border">
                    <div className="flex-1">
                      <Input
                        placeholder="Tên KPI (ví dụ: Chất lượng, Hiệu suất...)"
                        value={entry.key}
                        onChange={(e) => updateKpiEntry(entry.id, 'key', e.target.value)}
                        className="bg-white"
                      />
                    </div>
                    <div className="flex-1">
                      <Input
                        placeholder="Mức độ tác động (ví dụ: +10%, -5 điểm...)"
                        value={entry.value}
                        onChange={(e) => updateKpiEntry(entry.id, 'value', e.target.value)}
                        className="bg-white"
                      />
                    </div>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => removeKpiEntry(entry.id)}
                      className="text-red-600 hover:text-red-700 hover:bg-red-50 hover:border-red-300"
                      aria-label={`Xóa KPI ${entry.key || 'này'}`}
                    >
                      <MinusIcon className="h-4 w-4" />
                    </Button>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Form Actions */}
          <div className="flex flex-col sm:flex-row justify-end gap-3 pt-6 border-t">
            {mode === 'edit' && isDirty && (
              <Button
                type="button"
                variant="ghost"
                onClick={handleReset}
                disabled={isLoading}
                className="text-gray-600 hover:text-gray-800"
              >
                Khôi phục
              </Button>
            )}
            
            <Button
              type="button"
              variant="outline"
              onClick={onCancel}
              disabled={isLoading}
              className="sm:w-auto w-full"
            >
              Hủy
            </Button>
            
            <Button
              type="submit"
              disabled={isLoading || !isValid}
              className="bg-blue-600 hover:bg-blue-700 sm:w-auto w-full"
            >
              {isLoading ? (
                <div className="flex items-center gap-2">
                  <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                  Đang xử lý...
                </div>
              ) : (
                mode === 'edit' ? 'Cập nhật' : 'Tạo mới'
              )}
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
};