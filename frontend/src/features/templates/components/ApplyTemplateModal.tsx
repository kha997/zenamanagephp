/**
 * ApplyTemplateModal Component
 * Modal để áp dụng template vào project với các tùy chọn cấu hình
 */
import React, { useState, useEffect } from 'react';
import { Modal, ModalBody, ModalFooter } from '../../../components/ui/Modal';
import { useTemplatesStore } from '../../../store/templates';
import { useProjectsStore } from '../../../store/projects';
import { WorkTemplate, ApplyTemplateForm, Project } from '../../../lib/types';
import { cn } from '../../../lib/utils/format';

interface ApplyTemplateModalProps {
  isOpen: boolean;
  onClose: () => void;
  template: WorkTemplate | null;
  onSuccess?: () => void;
}

export const ApplyTemplateModal: React.FC<ApplyTemplateModalProps> = ({
  isOpen,
  onClose,
  template,
  onSuccess
}) => {
  const { applyTemplate, previewTemplateApplication, templatePreview, loading } = useTemplatesStore();
  const { projects, fetchProjects } = useProjectsStore();
  
  const [formData, setFormData] = useState<ApplyTemplateForm>({
    project_id: '',
    mode: 'full',
    conditional_tags: [],
    selected_phases: [],
    selected_tasks: [],
    phase_mapping: [],
    notify_assignees: true,
    notification_message: ''
  });
  
  const [showPreview, setShowPreview] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Load projects khi modal mở
  useEffect(() => {
    if (isOpen && projects.length === 0) {
      fetchProjects();
    }
  }, [isOpen, projects.length, fetchProjects]);

  // Reset form khi đóng modal
  useEffect(() => {
    if (!isOpen) {
      setFormData({
        project_id: '',
        mode: 'full',
        conditional_tags: [],
        selected_phases: [],
        selected_tasks: [],
        phase_mapping: [],
        notify_assignees: true,
        notification_message: ''
      });
      setShowPreview(false);
      setErrors({});
    }
  }, [isOpen]);

  const handleInputChange = (field: keyof ApplyTemplateForm, value: any) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    // Clear error khi user thay đổi input
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};
    
    if (!formData.project_id) {
      newErrors.project_id = 'Vui lòng chọn dự án';
    }
    
    if (formData.mode === 'partial' && formData.selected_phases.length === 0 && formData.selected_tasks.length === 0) {
      newErrors.selection = 'Vui lòng chọn ít nhất một phase hoặc task khi sử dụng chế độ partial';
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handlePreview = async () => {
    if (!validateForm() || !template) return;
    
    try {
      await previewTemplateApplication(template.id, formData);
      setShowPreview(true);
    } catch (error) {
      console.error('Preview failed:', error);
    }
  };

  const handleApply = async () => {
    if (!validateForm() || !template) return;
    
    try {
      await applyTemplate(template.id, formData);
      onSuccess?.();
      onClose();
    } catch (error) {
      console.error('Apply template failed:', error);
    }
  };

  const selectedProject = projects.find(p => p.id === formData.project_id);

  if (!template) return null;

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={`Áp dụng Template: ${template.name}`}
      size="lg"
    >
      <ModalBody>
        {!showPreview ? (
          <div className="space-y-6">
            {/* Project Selection */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Chọn dự án <span className="text-red-500">*</span>
              </label>
              <select
                value={formData.project_id}
                onChange={(e) => handleInputChange('project_id', e.target.value)}
                className={cn(
                  'w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500',
                  errors.project_id ? 'border-red-500' : 'border-gray-300'
                )}
              >
                <option value="">-- Chọn dự án --</option>
                {projects.map(project => (
                  <option key={project.id} value={project.id}>
                    {project.name}
                  </option>
                ))}
              </select>
              {errors.project_id && (
                <p className="mt-1 text-sm text-red-600">{errors.project_id}</p>
              )}
            </div>

            {/* Apply Mode */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Chế độ áp dụng
              </label>
              <div className="space-y-2">
                <label className="flex items-center">
                  <input
                    type="radio"
                    value="full"
                    checked={formData.mode === 'full'}
                    onChange={(e) => handleInputChange('mode', e.target.value)}
                    className="mr-2"
                  />
                  <span>Áp dụng toàn bộ template</span>
                </label>
                <label className="flex items-center">
                  <input
                    type="radio"
                    value="partial"
                    checked={formData.mode === 'partial'}
                    onChange={(e) => handleInputChange('mode', e.target.value)}
                    className="mr-2"
                  />
                  <span>Áp dụng một phần (chọn phases/tasks cụ thể)</span>
                </label>
                <label className="flex items-center">
                  <input
                    type="radio"
                    value="merge"
                    checked={formData.mode === 'merge'}
                    onChange={(e) => handleInputChange('mode', e.target.value)}
                    className="mr-2"
                  />
                  <span>Merge với dữ liệu hiện tại</span>
                </label>
              </div>
              {errors.selection && (
                <p className="mt-1 text-sm text-red-600">{errors.selection}</p>
              )}
            </div>

            {/* Conditional Tags */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Conditional Tags
              </label>
              <div className="space-y-2">
                {template.conditional_tags?.map(tag => (
                  <label key={tag} className="flex items-center">
                    <input
                      type="checkbox"
                      checked={formData.conditional_tags.includes(tag)}
                      onChange={(e) => {
                        const newTags = e.target.checked
                          ? [...formData.conditional_tags, tag]
                          : formData.conditional_tags.filter(t => t !== tag);
                        handleInputChange('conditional_tags', newTags);
                      }}
                      className="mr-2"
                    />
                    <span>{tag}</span>
                  </label>
                ))}
              </div>
            </div>

            {/* Notification Settings */}
            <div>
              <label className="flex items-center">
                <input
                  type="checkbox"
                  checked={formData.notify_assignees}
                  onChange={(e) => handleInputChange('notify_assignees', e.target.checked)}
                  className="mr-2"
                />
                <span className="text-sm font-medium text-gray-700">
                  Thông báo cho người được phân công
                </span>
              </label>
              
              {formData.notify_assignees && (
                <div className="mt-2">
                  <textarea
                    value={formData.notification_message}
                    onChange={(e) => handleInputChange('notification_message', e.target.value)}
                    placeholder="Tin nhắn thông báo (tùy chọn)"
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    rows={3}
                  />
                </div>
              )}
            </div>
          </div>
        ) : (
          /* Preview Section */
          <div className="space-y-4">
            <div className="bg-blue-50 p-4 rounded-lg">
              <h4 className="font-medium text-blue-900 mb-2">Xem trước áp dụng template</h4>
              <div className="text-sm text-blue-800">
                <p><strong>Template:</strong> {template.name}</p>
                <p><strong>Dự án:</strong> {selectedProject?.name}</p>
                <p><strong>Chế độ:</strong> {formData.mode}</p>
              </div>
            </div>
            
            {templatePreview && (
              <div className="space-y-4">
                <div>
                  <h5 className="font-medium text-gray-900 mb-2">Tóm tắt</h5>
                  <div className="bg-gray-50 p-3 rounded text-sm">
                    <p>Sẽ tạo {templatePreview.summary.visible_tasks} tasks</p>
                    <p>Ẩn {templatePreview.summary.hidden_tasks} tasks (do conditional tags)</p>
                    <p>Tổng thời gian ước tính: {templatePreview.summary.estimated_duration} ngày</p>
                  </div>
                </div>
                
                <div>
                  <h5 className="font-medium text-gray-900 mb-2">Tasks sẽ được tạo</h5>
                  <div className="max-h-60 overflow-y-auto space-y-2">
                    {templatePreview.tasks_preview.map((task, index) => (
                      <div key={index} className="bg-white p-3 border rounded text-sm">
                        <p className="font-medium">{task.name}</p>
                        <p className="text-gray-600">Thời gian: {task.duration} ngày</p>
                        {task.is_hidden && (
                          <span className="inline-block bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">
                            Sẽ bị ẩn
                          </span>
                        )}
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            )}
          </div>
        )}
      </ModalBody>
      
      <ModalFooter>
        {!showPreview ? (
          <>
            <button
              onClick={onClose}
              className="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
            >
              Hủy
            </button>
            <button
              onClick={handlePreview}
              disabled={loading || !formData.project_id}
              className="px-4 py-2 bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-md transition-colors"
            >
              {loading ? 'Đang xử lý...' : 'Xem trước'}
            </button>
          </>
        ) : (
          <>
            <button
              onClick={() => setShowPreview(false)}
              className="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
            >
              Quay lại
            </button>
            <button
              onClick={handleApply}
              disabled={loading}
              className="px-4 py-2 bg-green-600 text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-md transition-colors"
            >
              {loading ? 'Đang áp dụng...' : 'Áp dụng Template'}
            </button>
          </>
        )}
      </ModalFooter>
    </Modal>
  );
};