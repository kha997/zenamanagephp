/**
 * CreateTemplate Page
 * Trang tạo mới template với form builder
 */
import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTemplatesStore } from '../../../store/templates';
import { CreateWorkTemplateForm, TemplateTask } from '../../../lib/types';
import { cn } from '../../../lib/utils';

export const CreateTemplate: React.FC = () => {
  const navigate = useNavigate();
  const { id } = useParams<{ id: string }>();
  const isEditMode = !!id;
  
  const { 
    currentTemplate, 
    loading, 
    createTemplate, 
    updateTemplate, 
    fetchTemplate,
    getCategories,
    clearCurrentTemplate 
  } = useTemplatesStore();
  
  const [formData, setFormData] = useState<CreateWorkTemplateForm>({
    name: '',
    description: '',
    category: 'design',
    is_active: true,
    conditional_tags: [],
    template_data: {
      tasks: [],
      phases: []
    }
  });
  
  const [tasks, setTasks] = useState<TemplateTask[]>([]);
  const [newTask, setNewTask] = useState<Partial<TemplateTask>>({});
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [categories] = useState([
    { value: 'design', label: 'Thiết kế' },
    { value: 'construction', label: 'Thi công' },
    { value: 'qc', label: 'Kiểm soát chất lượng' },
    { value: 'inspection', label: 'Nghiệm thu' }
  ]);

  // Load template data nếu đang edit
  useEffect(() => {
    if (isEditMode && id) {
      fetchTemplate(id);
    }
    
    return () => {
      clearCurrentTemplate();
    };
  }, [isEditMode, id, fetchTemplate, clearCurrentTemplate]);

  // Populate form data khi load template
  useEffect(() => {
    if (isEditMode && currentTemplate) {
      setFormData({
        name: currentTemplate.name,
        description: currentTemplate.description || '',
        category: currentTemplate.category,
        is_active: currentTemplate.is_active,
        conditional_tags: currentTemplate.conditional_tags || [],
        template_data: currentTemplate.template_data || { tasks: [], phases: [] }
      });
      setTasks(currentTemplate.template_data?.tasks || []);
    }
  }, [isEditMode, currentTemplate]);

  const handleInputChange = (field: keyof CreateWorkTemplateForm, value: any) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    // Clear error khi user thay đổi input
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  const handleAddTask = () => {
    if (!newTask.name?.trim()) {
      alert('Vui lòng nhập tên task');
      return;
    }
    
    const task: TemplateTask = {
      id: Date.now().toString(), // Temporary ID
      name: newTask.name,
      description: newTask.description || '',
      duration: newTask.duration || 1,
      conditional_tag: newTask.conditional_tag || '',
      dependencies: newTask.dependencies || [],
      assignee_role: newTask.assignee_role || '',
      priority: newTask.priority || 'medium'
    };
    
    setTasks(prev => [...prev, task]);
    setNewTask({});
  };

  const handleRemoveTask = (taskId: string) => {
    setTasks(prev => prev.filter(task => task.id !== taskId));
  };

  const handleTaskChange = (taskId: string, field: keyof TemplateTask, value: any) => {
    setTasks(prev => prev.map(task => 
      task.id === taskId ? { ...task, [field]: value } : task
    ));
  };

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};
    
    if (!formData.name.trim()) {
      newErrors.name = 'Tên template là bắt buộc';
    }
    
    if (!formData.category) {
      newErrors.category = 'Danh mục là bắt buộc';
    }
    
    if (tasks.length === 0) {
      newErrors.tasks = 'Template phải có ít nhất một task';
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!validateForm()) return;
    
    const templateData = {
      ...formData,
      template_data: {
        ...formData.template_data,
        tasks
      }
    };
    
    try {
      if (isEditMode && id) {
        await updateTemplate(id, templateData);
      } else {
        await createTemplate(templateData);
      }
      navigate('/templates');
    } catch (error) {
      console.error('Save template failed:', error);
    }
  };

  const handleAddConditionalTag = (tag: string) => {
    if (tag.trim() && !formData.conditional_tags.includes(tag.trim())) {
      handleInputChange('conditional_tags', [...formData.conditional_tags, tag.trim()]);
    }
  };

  const handleRemoveConditionalTag = (tag: string) => {
    handleInputChange('conditional_tags', formData.conditional_tags.filter(t => t !== tag));
  };

  return (
    <div className="max-w-4xl mx-auto px-4 py-6">
      <div className="bg-white rounded-lg shadow-sm border border-gray-200">
        <div className="p-6 border-b border-gray-200">
          <h1 className="text-2xl font-bold text-gray-900">
            {isEditMode ? 'Chỉnh sửa Template' : 'Tạo Template mới'}
          </h1>
        </div>
        
        <form onSubmit={handleSubmit} className="p-6 space-y-6">
          {/* Basic Information */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Tên template <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={formData.name}
                onChange={(e) => handleInputChange('name', e.target.value)}
                className={cn(
                  'w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500',
                  errors.name ? 'border-red-500' : 'border-gray-300'
                )}
                placeholder="Nhập tên template"
              />
              {errors.name && (
                <p className="mt-1 text-sm text-red-600">{errors.name}</p>
              )}
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Danh mục <span className="text-red-500">*</span>
              </label>
              <select
                value={formData.category}
                onChange={(e) => handleInputChange('category', e.target.value)}
                className={cn(
                  'w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500',
                  errors.category ? 'border-red-500' : 'border-gray-300'
                )}
              >
                {categories.map(cat => (
                  <option key={cat.value} value={cat.value}>
                    {cat.label}
                  </option>
                ))}
              </select>
              {errors.category && (
                <p className="mt-1 text-sm text-red-600">{errors.category}</p>
              )}
            </div>
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Mô tả
            </label>
            <textarea
              value={formData.description}
              onChange={(e) => handleInputChange('description', e.target.value)}
              className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
              rows={3}
              placeholder="Mô tả template"
            />
          </div>
          
          <div>
            <label className="flex items-center">
              <input
                type="checkbox"
                checked={formData.is_active}
                onChange={(e) => handleInputChange('is_active', e.target.checked)}
                className="mr-2"
              />
              <span className="text-sm font-medium text-gray-700">
                Template hoạt động
              </span>
            </label>
          </div>

          {/* Conditional Tags */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Conditional Tags
            </label>
            <div className="flex flex-wrap gap-2 mb-2">
              {formData.conditional_tags.map(tag => (
                <span
                  key={tag}
                  className="inline-flex items-center px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full"
                >
                  {tag}
                  <button
                    type="button"
                    onClick={() => handleRemoveConditionalTag(tag)}
                    className="ml-2 text-blue-600 hover:text-blue-800"
                  >
                    ×
                  </button>
                </span>
              ))}
            </div>
            <div className="flex space-x-2">
              <input
                type="text"
                placeholder="Nhập tag mới"
                className="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                onKeyPress={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    handleAddConditionalTag((e.target as HTMLInputElement).value);
                    (e.target as HTMLInputElement).value = '';
                  }
                }}
              />
              <button
                type="button"
                onClick={(e) => {
                  const input = (e.target as HTMLButtonElement).previousElementSibling as HTMLInputElement;
                  handleAddConditionalTag(input.value);
                  input.value = '';
                }}
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              >
                Thêm
              </button>
            </div>
          </div>

          {/* Tasks Section */}
          <div>
            <h3 className="text-lg font-medium text-gray-900 mb-4">Tasks</h3>
            
            {/* Add New Task */}
            <div className="bg-gray-50 p-4 rounded-lg mb-4">
              <h4 className="font-medium text-gray-900 mb-3">Thêm task mới</h4>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input
                  type="text"
                  value={newTask.name || ''}
                  onChange={(e) => setNewTask(prev => ({ ...prev, name: e.target.value }))}
                  placeholder="Tên task"
                  className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <input
                  type="number"
                  value={newTask.duration || ''}
                  onChange={(e) => setNewTask(prev => ({ ...prev, duration: parseInt(e.target.value) || 1 }))}
                  placeholder="Thời gian (ngày)"
                  min="1"
                  className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <input
                  type="text"
                  value={newTask.conditional_tag || ''}
                  onChange={(e) => setNewTask(prev => ({ ...prev, conditional_tag: e.target.value }))}
                  placeholder="Conditional tag (tùy chọn)"
                  className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <select
                  value={newTask.priority || 'medium'}
                  onChange={(e) => setNewTask(prev => ({ ...prev, priority: e.target.value as any }))}
                  className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="low">Thấp</option>
                  <option value="medium">Trung bình</option>
                  <option value="high">Cao</option>
                  <option value="critical">Khẩn cấp</option>
                </select>
              </div>
              <div className="mt-3">
                <textarea
                  value={newTask.description || ''}
                  onChange={(e) => setNewTask(prev => ({ ...prev, description: e.target.value }))}
                  placeholder="Mô tả task"
                  className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                  rows={2}
                />
              </div>
              <div className="mt-3">
                <button
                  type="button"
                  onClick={handleAddTask}
                  className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors"
                >
                  Thêm Task
                </button>
              </div>
            </div>
            
            {/* Tasks List */}
            {tasks.length > 0 ? (
              <div className="space-y-3">
                {tasks.map((task, index) => (
                  <div key={task.id} className="border border-gray-200 rounded-lg p-4">
                    <div className="flex items-start justify-between">
                      <div className="flex-1 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Tên task</label>
                          <input
                            type="text"
                            value={task.name}
                            onChange={(e) => handleTaskChange(task.id, 'name', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                          />
                        </div>
                        
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Thời gian (ngày)</label>
                          <input
                            type="number"
                            value={task.duration}
                            onChange={(e) => handleTaskChange(task.id, 'duration', parseInt(e.target.value) || 1)}
                            min="1"
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                          />
                        </div>
                        
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Độ ưu tiên</label>
                          <select
                            value={task.priority}
                            onChange={(e) => handleTaskChange(task.id, 'priority', e.target.value)}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                          >
                            <option value="low">Thấp</option>
                            <option value="medium">Trung bình</option>
                            <option value="high">Cao</option>
                            <option value="critical">Khẩn cấp</option>
                          </select>
                        </div>
                      </div>
                      
                      <button
                        type="button"
                        onClick={() => handleRemoveTask(task.id)}
                        className="ml-4 text-red-600 hover:text-red-800"
                      >
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                      </button>
                    </div>
                    
                    <div className="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Conditional Tag</label>
                        <input
                          type="text"
                          value={task.conditional_tag}
                          onChange={(e) => handleTaskChange(task.id, 'conditional_tag', e.target.value)}
                          placeholder="Tùy chọn"
                          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                      </div>
                      
                      <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Vai trò phân công</label>
                        <input
                          type="text"
                          value={task.assignee_role}
                          onChange={(e) => handleTaskChange(task.id, 'assignee_role', e.target.value)}
                          placeholder="VD: Project Manager, Developer"
                          className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                      </div>
                    </div>
                    
                    <div className="mt-3">
                      <label className="block text-xs font-medium text-gray-500 mb-1">Mô tả</label>
                      <textarea
                        value={task.description}
                        onChange={(e) => handleTaskChange(task.id, 'description', e.target.value)}
                        className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        rows={2}
                      />
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <div className="text-center py-8 text-gray-500">
                <p>Chưa có tasks nào. Thêm task đầu tiên để bắt đầu.</p>
              </div>
            )}
            
            {errors.tasks && (
              <p className="mt-2 text-sm text-red-600">{errors.tasks}</p>
            )}
          </div>

          {/* Form Actions */}
          <div className="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
            <button
              type="button"
              onClick={() => navigate('/templates')}
              className="px-4 py-2 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-md transition-colors"
            >
              Hủy
            </button>
            
            <button
              type="submit"
              disabled={loading}
              className="px-6 py-2 bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-md transition-colors"
            >
              {loading ? 'Đang lưu...' : (isEditMode ? 'Cập nhật' : 'Tạo Template')}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};