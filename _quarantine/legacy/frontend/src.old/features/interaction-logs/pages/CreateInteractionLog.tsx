import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Save, X } from 'lucide-react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { InteractionLogsApi } from '../api/interactionLogsApi';
import { useAuthGuard } from '../hooks/useAuthGuard';
import { CreateInteractionLogForm, InteractionType, VisibilityType } from '../types/interactionLog';

/**
 * CreateInteractionLog page - Trang tạo interaction log mới
 */
export const CreateInteractionLog: React.FC = () => {
  const { projectId } = useParams<{ projectId: string }>();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  
  // Auth guard
  const { hasPermission } = useAuthGuard();
  const canCreate = hasPermission('interaction_logs.create');
  
  // Form state
  const [formData, setFormData] = useState<CreateInteractionLogForm>({
    project_id: projectId ? parseInt(projectId) : 0,
    type: 'note',
    description: '',
    visibility: 'internal',
    tag_path: '',
    linked_task_id: undefined
  });
  
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Create mutation
  const createMutation = useMutation({
    mutationFn: InteractionLogsApi.create,
    onSuccess: (response) => {
      // Invalidate queries để refresh data
      queryClient.invalidateQueries({ queryKey: ['interaction-logs'] });
      
      // Navigate to detail page
      navigate(`/projects/${projectId}/interaction-logs/${response.data.id}`);
    },
    onError: (error: any) => {
      console.error('Create failed:', error);
      // Handle validation errors
      if (error.response?.data?.errors) {
        setErrors(error.response.data.errors);
      }
    }
  });

  /**
   * Xử lý thay đổi input
   */
  const handleInputChange = (field: keyof CreateInteractionLogForm, value: any) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    
    // Clear error khi user bắt đầu sửa
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  /**
   * Validate form
   */
  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};
    
    if (!formData.description.trim()) {
      newErrors.description = 'Mô tả là bắt buộc';
    }
    
    if (formData.description.length > 1000) {
      newErrors.description = 'Mô tả không được vượt quá 1000 ký tự';
    }
    
    if (formData.tag_path && formData.tag_path.length > 255) {
      newErrors.tag_path = 'Tag path không được vượt quá 255 ký tự';
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  /**
   * Xử lý submit form
   */
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!canCreate) {
      alert('Bạn không có quyền tạo interaction log');
      return;
    }
    
    if (!validateForm()) {
      return;
    }
    
    // Clean data trước khi submit
    const submitData = {
      ...formData,
      tag_path: formData.tag_path?.trim() || undefined,
      linked_task_id: formData.linked_task_id || undefined
    };
    
    createMutation.mutate(submitData);
  };

  /**
   * Xử lý quay lại
   */
  const handleGoBack = () => {
    navigate(`/projects/${projectId}/interaction-logs`);
  };

  /**
   * Xử lý hủy
   */
  const handleCancel = () => {
    if (formData.description.trim() && 
        !window.confirm('Bạn có chắc chắn muốn hủy? Dữ liệu đã nhập sẽ bị mất.')) {
      return;
    }
    handleGoBack();
  };

  if (!canCreate) {
    return (
      <div className="text-center py-12">
        <h3 className="text-lg font-medium text-gray-900 mb-2">
          Không có quyền truy cập
        </h3>
        <p className="text-gray-500 mb-6">
          Bạn không có quyền tạo interaction log mới.
        </p>
        <button
          onClick={handleGoBack}
          className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
        >
          <ArrowLeft className="h-4 w-4" />
          Quay lại danh sách
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <button
            onClick={handleGoBack}
            className="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md transition-colors"
          >
            <ArrowLeft className="h-5 w-5" />
          </button>
          
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              Tạo Interaction Log Mới
            </h1>
            <p className="text-gray-600 mt-1">
              Thêm tương tác hoặc ghi chú mới cho dự án
            </p>
          </div>
        </div>
      </div>

      {/* Form */}
      <form onSubmit={handleSubmit} className="space-y-6">
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
          <div className="space-y-6">
            {/* Type */}
            <div>
              <label htmlFor="type" className="block text-sm font-medium text-gray-700 mb-2">
                Loại tương tác *
              </label>
              <select
                id="type"
                value={formData.type}
                onChange={(e) => handleInputChange('type', e.target.value as InteractionType)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="call">Cuộc gọi</option>
                <option value="email">Email</option>
                <option value="meeting">Cuộc họp</option>
                <option value="note">Ghi chú</option>
                <option value="feedback">Phản hồi</option>
              </select>
            </div>

            {/* Description */}
            <div>
              <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-2">
                Mô tả *
              </label>
              <textarea
                id="description"
                value={formData.description}
                onChange={(e) => handleInputChange('description', e.target.value)}
                rows={6}
                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                  errors.description ? 'border-red-300' : 'border-gray-300'
                }`}
                placeholder="Nhập mô tả chi tiết về tương tác..."
              />
              {errors.description && (
                <p className="mt-1 text-sm text-red-600">{errors.description}</p>
              )}
              <p className="mt-1 text-sm text-gray-500">
                {formData.description.length}/1000 ký tự
              </p>
            </div>

            {/* Visibility */}
            <div>
              <label htmlFor="visibility" className="block text-sm font-medium text-gray-700 mb-2">
                Mức độ hiển thị *
              </label>
              <select
                id="visibility"
                value={formData.visibility}
                onChange={(e) => handleInputChange('visibility', e.target.value as VisibilityType)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="internal">Nội bộ</option>
                <option value="client">Khách hàng</option>
              </select>
              <p className="mt-1 text-sm text-gray-500">
                {formData.visibility === 'client' 
                  ? 'Khách hàng có thể xem sau khi được duyệt'
                  : 'Chỉ thành viên nội bộ có thể xem'
                }
              </p>
            </div>

            {/* Tag Path */}
            <div>
              <label htmlFor="tag_path" className="block text-sm font-medium text-gray-700 mb-2">
                Tag Path
              </label>
              <input
                type="text"
                id="tag_path"
                value={formData.tag_path || ''}
                onChange={(e) => handleInputChange('tag_path', e.target.value)}
                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent ${
                  errors.tag_path ? 'border-red-300' : 'border-gray-300'
                }`}
                placeholder="Ví dụ: Material/Flooring/Granite"
              />
              {errors.tag_path && (
                <p className="mt-1 text-sm text-red-600">{errors.tag_path}</p>
              )}
              <p className="mt-1 text-sm text-gray-500">
                Phân loại tương tác theo cấu trúc thư mục (tùy chọn)
              </p>
            </div>

            {/* Linked Task ID */}
            <div>
              <label htmlFor="linked_task_id" className="block text-sm font-medium text-gray-700 mb-2">
                Liên kết Task
              </label>
              <input
                type="number"
                id="linked_task_id"
                value={formData.linked_task_id || ''}
                onChange={(e) => handleInputChange('linked_task_id', e.target.value ? parseInt(e.target.value) : undefined)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Nhập ID của task liên quan"
                min="1"
              />
              <p className="mt-1 text-sm text-gray-500">
                Liên kết interaction log với một task cụ thể (tùy chọn)
              </p>
            </div>
          </div>
        </div>

        {/* Actions */}
        <div className="flex items-center justify-end gap-3">
          <button
            type="button"
            onClick={handleCancel}
            className="flex items-center gap-2 px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
          >
            <X className="h-4 w-4" />
            Hủy
          </button>
          
          <button
            type="submit"
            disabled={createMutation.isPending}
            className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
          >
            <Save className="h-4 w-4" />
            {createMutation.isPending ? 'Đang tạo...' : 'Tạo Interaction Log'}
          </button>
        </div>
      </form>
    </div>
  );
};