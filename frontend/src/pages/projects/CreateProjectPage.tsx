import React from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/Card';
import { Button } from '@/components/Button';
import { Input } from '@/components/Input';
import { Select } from '@/components/Select';
import { Loading } from '@/components/Loading';
import { Toast } from '@/components/Toast';
import { apiClient } from '@/lib/api-client';
import { ArrowLeft, Save } from 'lucide-react';

// Schema validation cho form tạo dự án
const createProjectSchema = z.object({
  name: z.string().min(1, 'Tên dự án là bắt buộc').max(255, 'Tên dự án quá dài'),
  description: z.string().optional(),
  start_date: z.string().min(1, 'Ngày bắt đầu là bắt buộc'),
  end_date: z.string().min(1, 'Ngày kết thúc là bắt buộc'),
  status: z.enum(['planning', 'in_progress', 'on_hold', 'completed', 'cancelled'])
}).refine((data) => {
  const startDate = new Date(data.start_date);
  const endDate = new Date(data.end_date);
  return endDate >= startDate;
}, {
  message: 'Ngày kết thúc phải sau ngày bắt đầu',
  path: ['end_date']
});

type CreateProjectFormData = z.infer<typeof createProjectSchema>;

const CreateProjectPage: React.FC = () => {
  const navigate = useNavigate();
  const queryClient = useQueryClient();

  const {
    register,
    handleSubmit,
    formState: { errors },
    setValue,
    watch
  } = useForm<CreateProjectFormData>({
    resolver: zodResolver(createProjectSchema),
    defaultValues: {
      status: 'planning'
    }
  });

  const createProjectMutation = useMutation({
    mutationFn: async (data: CreateProjectFormData) => {
      const response = await apiClient.post('/projects', data);
      return response.data;
    },
    onSuccess: (data) => {
      // Invalidate và refetch projects list
      queryClient.invalidateQueries({ queryKey: ['projects'] });
      
      // Show success toast
      Toast.success('Tạo dự án thành công!');
      
      // Navigate to project detail
      navigate(`/projects/${data.id}`);
    },
    onError: (error: any) => {
      Toast.error(error.response?.data?.message || 'Có lỗi xảy ra khi tạo dự án');
    }
  });

  const onSubmit = (data: CreateProjectFormData) => {
    createProjectMutation.mutate(data);
  };

  const statusOptions = [
    { value: 'planning', label: 'Lên kế hoạch' },
    { value: 'in_progress', label: 'Đang thực hiện' },
    { value: 'on_hold', label: 'Tạm dừng' },
    { value: 'completed', label: 'Hoàn thành' },
    { value: 'cancelled', label: 'Đã hủy' }
  ];

  return (
    <div className="max-w-4xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Button variant="outline" asChild>
          <Link to="/projects">
            <ArrowLeft className="w-4 h-4 mr-2" />
            Quay lại
          </Link>
        </Button>
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Tạo dự án mới</h1>
          <p className="text-gray-600 mt-1">
            Điền thông tin để tạo dự án mới trong hệ thống
          </p>
        </div>
      </div>

      {/* Form */}
      <Card>
        <CardHeader>
          <CardTitle>Thông tin dự án</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            {/* Project Name */}
            <div>
              <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-2">
                Tên dự án *
              </label>
              <Input
                id="name"
                placeholder="Nhập tên dự án..."
                {...register('name')}
                error={errors.name?.message}
                disabled={createProjectMutation.isPending}
              />
            </div>

            {/* Description */}
            <div>
              <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-2">
                Mô tả dự án
              </label>
              <textarea
                id="description"
                rows={4}
                placeholder="Mô tả chi tiết về dự án..."
                {...register('description')}
                disabled={createProjectMutation.isPending}
                className="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-50 disabled:text-gray-500"
              />
              {errors.description && (
                <p className="mt-1 text-sm text-red-600">{errors.description.message}</p>
              )}
            </div>

            {/* Date Range */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Start Date */}
              <div>
                <label htmlFor="start_date" className="block text-sm font-medium text-gray-700 mb-2">
                  Ngày bắt đầu *
                </label>
                <Input
                  id="start_date"
                  type="date"
                  {...register('start_date')}
                  error={errors.start_date?.message}
                  disabled={createProjectMutation.isPending}
                />
              </div>

              {/* End Date */}
              <div>
                <label htmlFor="end_date" className="block text-sm font-medium text-gray-700 mb-2">
                  Ngày kết thúc *
                </label>
                <Input
                  id="end_date"
                  type="date"
                  {...register('end_date')}
                  error={errors.end_date?.message}
                  disabled={createProjectMutation.isPending}
                />
              </div>
            </div>

            {/* Status */}
            <div>
              <label htmlFor="status" className="block text-sm font-medium text-gray-700 mb-2">
                Trạng thái ban đầu
              </label>
              <Select
                value={watch('status')}
                onValueChange={(value) => setValue('status', value as any)}
                options={statusOptions}
                disabled={createProjectMutation.isPending}
              />
              {errors.status && (
                <p className="mt-1 text-sm text-red-600">{errors.status.message}</p>
              )}
            </div>

            {/* Form Actions */}
            <div className="flex items-center justify-end gap-4 pt-6 border-t border-gray-200">
              <Button
                type="button"
                variant="outline"
                onClick={() => navigate('/projects')}
                disabled={createProjectMutation.isPending}
              >
                Hủy
              </Button>
              <Button
                type="submit"
                disabled={createProjectMutation.isPending}
              >
                {createProjectMutation.isPending ? (
                  <>
                    <Loading size="sm" className="mr-2" />
                    Đang tạo...
                  </>
                ) : (
                  <>
                    <Save className="w-4 h-4 mr-2" />
                    Tạo dự án
                  </>
                )}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
};

export default CreateProjectPage;