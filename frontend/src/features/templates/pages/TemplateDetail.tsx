/**
 * TemplateDetail Page
 * Trang chi tiết template với thông tin đầy đủ và các hành động
 */
import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useTemplatesStore } from '../../../store/templates';
import { ApplyTemplateModal } from '../components/ApplyTemplateModal';
import { cn } from '../../../lib/utils/format';

export const TemplateDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { 
    currentTemplate, 
    loading, 
    fetchTemplate, 
    duplicateTemplate, 
    deleteTemplate,
    clearCurrentTemplate 
  } = useTemplatesStore();
  
  const [showApplyModal, setShowApplyModal] = useState(false);
  const [activeTab, setActiveTab] = useState<'overview' | 'tasks' | 'versions'>('overview');

  useEffect(() => {
    if (id) {
      fetchTemplate(id);
    }
    
    return () => {
      clearCurrentTemplate();
    };
  }, [id, fetchTemplate, clearCurrentTemplate]);

  const handleDuplicate = async () => {
    if (!currentTemplate) return;
    
    try {
      const newTemplate = await duplicateTemplate(currentTemplate.id);
      navigate(`/templates/${newTemplate.id}/edit`);
    } catch (error) {
      console.error('Duplicate failed:', error);
    }
  };

  const handleDelete = async () => {
    if (!currentTemplate) return;
    
    if (confirm(`Bạn có chắc chắn muốn xóa template "${currentTemplate.name}"?`)) {
      try {
        await deleteTemplate(currentTemplate.id);
        navigate('/templates');
      } catch (error) {
        console.error('Delete failed:', error);
      }
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (!currentTemplate) {
    return (
      <div className="text-center py-12">
        <h2 className="text-xl font-semibold text-gray-900 mb-2">Template không tồn tại</h2>
        <p className="text-gray-600 mb-4">Template bạn đang tìm kiếm không tồn tại hoặc đã bị xóa.</p>
        <button
          onClick={() => navigate('/templates')}
          className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
        >
          Quay lại danh sách
        </button>
      </div>
    );
  }

  const getCategoryColor = (category: string) => {
    const colors = {
      design: 'bg-purple-100 text-purple-800',
      construction: 'bg-orange-100 text-orange-800',
      qc: 'bg-green-100 text-green-800',
      inspection: 'bg-blue-100 text-blue-800'
    };
    return colors[category as keyof typeof colors] || 'bg-gray-100 text-gray-800';
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-6">
      {/* Header */}
      <div className="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
        <div className="p-6">
          <div className="flex items-start justify-between">
            <div className="flex-1">
              <div className="flex items-center space-x-3 mb-2">
                <h1 className="text-2xl font-bold text-gray-900">{currentTemplate.name}</h1>
                <span className={cn(
                  'px-2 py-1 text-xs font-medium rounded-full',
                  getCategoryColor(currentTemplate.category)
                )}>
                  {currentTemplate.category}
                </span>
                {!currentTemplate.is_active && (
                  <span className="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800">
                    Không hoạt động
                  </span>
                )}
              </div>
              
              {currentTemplate.description && (
                <p className="text-gray-600 mb-4">{currentTemplate.description}</p>
              )}
              
              <div className="flex items-center space-x-6 text-sm text-gray-500">
                <span>Phiên bản: {currentTemplate.version}</span>
                <span>Tạo: {new Date(currentTemplate.created_at).toLocaleDateString('vi-VN')}</span>
                <span>Cập nhật: {new Date(currentTemplate.updated_at).toLocaleDateString('vi-VN')}</span>
              </div>
            </div>
            
            <div className="flex items-center space-x-3">
              <button
                onClick={() => setShowApplyModal(true)}
                disabled={!currentTemplate.is_active}
                className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                Áp dụng
              </button>
              
              <button
                onClick={handleDuplicate}
                className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
              >
                Nhân bản
              </button>
              
              <button
                onClick={() => navigate(`/templates/${currentTemplate.id}/edit`)}
                className="px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors"
              >
                Chỉnh sửa
              </button>
              
              <button
                onClick={handleDelete}
                className="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
              >
                Xóa
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="bg-white rounded-lg shadow-sm border border-gray-200">
        <div className="border-b border-gray-200">
          <nav className="flex space-x-8 px-6">
            {[
              { key: 'overview', label: 'Tổng quan' },
              { key: 'tasks', label: 'Tasks' },
              { key: 'versions', label: 'Phiên bản' }
            ].map(tab => (
              <button
                key={tab.key}
                onClick={() => setActiveTab(tab.key as any)}
                className={cn(
                  'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                  activeTab === tab.key
                    ? 'border-blue-500 text-blue-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                )}
              >
                {tab.label}
              </button>
            ))}
          </nav>
        </div>
        
        <div className="p-6">
          {activeTab === 'overview' && (
            <div className="space-y-6">
              {/* Template Statistics */}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="bg-gray-50 p-4 rounded-lg">
                  <h3 className="text-sm font-medium text-gray-500 mb-1">Tổng số Tasks</h3>
                  <p className="text-2xl font-bold text-gray-900">
                    {currentTemplate.template_data?.tasks?.length || 0}
                  </p>
                </div>
                
                <div className="bg-gray-50 p-4 rounded-lg">
                  <h3 className="text-sm font-medium text-gray-500 mb-1">Conditional Tags</h3>
                  <p className="text-2xl font-bold text-gray-900">
                    {currentTemplate.conditional_tags?.length || 0}
                  </p>
                </div>
                
                <div className="bg-gray-50 p-4 rounded-lg">
                  <h3 className="text-sm font-medium text-gray-500 mb-1">Lần sử dụng</h3>
                  <p className="text-2xl font-bold text-gray-900">
                    {currentTemplate.usage_count || 0}
                  </p>
                </div>
              </div>
              
              {/* Conditional Tags */}
              {currentTemplate.conditional_tags && currentTemplate.conditional_tags.length > 0 && (
                <div>
                  <h3 className="text-lg font-medium text-gray-900 mb-3">Conditional Tags</h3>
                  <div className="flex flex-wrap gap-2">
                    {currentTemplate.conditional_tags.map(tag => (
                      <span
                        key={tag}
                        className="px-3 py-1 bg-blue-100 text-blue-800 text-sm rounded-full"
                      >
                        {tag}
                      </span>
                    ))}
                  </div>
                </div>
              )}
            </div>
          )}
          
          {activeTab === 'tasks' && (
            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-4">Danh sách Tasks</h3>
              {currentTemplate.template_data?.tasks && currentTemplate.template_data.tasks.length > 0 ? (
                <div className="space-y-3">
                  {currentTemplate.template_data.tasks.map((task, index) => (
                    <div key={index} className="border border-gray-200 rounded-lg p-4">
                      <div className="flex items-start justify-between">
                        <div className="flex-1">
                          <h4 className="font-medium text-gray-900">{task.name}</h4>
                          {task.description && (
                            <p className="text-gray-600 text-sm mt-1">{task.description}</p>
                          )}
                          
                          <div className="flex items-center space-x-4 mt-2 text-sm text-gray-500">
                            {task.duration && (
                              <span>Thời gian: {task.duration} ngày</span>
                            )}
                            {task.conditional_tag && (
                              <span className="px-2 py-1 bg-yellow-100 text-yellow-800 rounded">
                                Tag: {task.conditional_tag}
                              </span>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-gray-500 text-center py-8">Chưa có tasks nào trong template này.</p>
              )}
            </div>
          )}
          
          {activeTab === 'versions' && (
            <div>
              <h3 className="text-lg font-medium text-gray-900 mb-4">Lịch sử phiên bản</h3>
              {currentTemplate.versions && currentTemplate.versions.length > 0 ? (
                <div className="space-y-3">
                  {currentTemplate.versions.map(version => (
                    <div key={version.id} className="border border-gray-200 rounded-lg p-4">
                      <div className="flex items-center justify-between">
                        <div>
                          <h4 className="font-medium text-gray-900">Phiên bản {version.version_number}</h4>
                          {version.change_notes && (
                            <p className="text-gray-600 text-sm mt-1">{version.change_notes}</p>
                          )}
                          <p className="text-gray-500 text-sm mt-1">
                            Tạo: {new Date(version.created_at).toLocaleDateString('vi-VN')}
                          </p>
                        </div>
                        
                        {version.version_number === currentTemplate.version && (
                          <span className="px-2 py-1 bg-green-100 text-green-800 text-sm rounded">
                            Hiện tại
                          </span>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-gray-500 text-center py-8">Chưa có lịch sử phiên bản.</p>
              )}
            </div>
          )}
        </div>
      </div>
      
      {/* Apply Template Modal */}
      <ApplyTemplateModal
        isOpen={showApplyModal}
        onClose={() => setShowApplyModal(false)}
        template={currentTemplate}
        onSuccess={() => {
          // Có thể thêm thông báo thành công ở đây
          console.log('Template applied successfully');
        }}
      />
    </div>
  );
};