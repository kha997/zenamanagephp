import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  ArrowLeftIcon,
  PlusIcon,
  TrashIcon,
  DocumentArrowUpIcon
} from '@heroicons/react/24/outline';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Textarea } from '../../components/ui/Textarea';
import { Select } from '../../components/ui/Select';
import { Card } from '../../components/ui/Card';
import { useAuthStore } from '../../shared/auth/store';
import toast from 'react-hot-toast';
import { formatCurrency } from '../../lib/utils/format';

// Interface cho form data
interface CRFormData {
  title: string;
  description: string;
  project_id: string;
  impact_days: number;
  impact_cost: number;
  impact_kpi: Array<{ key: string; value: string }>;
  attachments: File[];
}

// Interface cho project option
interface ProjectOption {
  id: string;
  name: string;
}

export const CRCreatePage: React.FC = () => {
  const navigate = useNavigate();
  const { user } = useAuthStore();
  
  const [formData, setFormData] = useState<CRFormData>({
    title: '',
    description: '',
    project_id: '',
    impact_days: 0,
    impact_cost: 0,
    impact_kpi: [{ key: '', value: '' }],
    attachments: []
  });
  
  const [projects, setProjects] = useState<ProjectOption[]>([]);
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  useEffect(() => {
    fetchProjects();
  }, []);

  const fetchProjects = async () => {
    try {
      // Mock API call - thay thế bằng API thực tế
      const mockProjects: ProjectOption[] = [
        { id: '1', name: 'Dự án Villa Thảo Điền' },
        { id: '2', name: 'Dự án Chung cư Landmark' },
        { id: '3', name: 'Dự án Nhà phố Quận 7' }
      ];
      
      setProjects(mockProjects);
    } catch (error) {
      console.error('Error fetching projects:', error);
      toast.error('Không thể tải danh sách dự án');
    }
  };

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {};
    
    if (!formData.title.trim()) {
      newErrors.title = 'Tiêu đề là bắt buộc';
    }
    
    if (!formData.description.trim()) {
      newErrors.description = 'Mô tả là bắt buộc';
    }
    
    if (!formData.project_id) {
      newErrors.project_id = 'Vui lòng chọn dự án';
    }
    
    // Validate KPI entries
    const validKPIs = formData.impact_kpi.filter(kpi => kpi.key.trim() && kpi.value.trim());
    if (validKPIs.length === 0) {
      newErrors.impact_kpi = 'Vui lòng thêm ít nhất một chỉ số KPI';
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent, isDraft: boolean = false) => {
    e.preventDefault();
    
    if (!isDraft && !validateForm()) {
      return;
    }
    
    try {
      setLoading(true);
      
      // Prepare form data for submission
      const submitData = {
        ...formData,
        impact_kpi: Object.fromEntries(
          formData.impact_kpi
            .filter(kpi => kpi.key.trim() && kpi.value.trim())
            .map(kpi => [kpi.key.trim(), kpi.value.trim()])
        ),
        status: isDraft ? 'draft' : 'awaiting_approval'
      };
      
      // Mock API call
      const response = await fetch('/api/v1/change-requests', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${localStorage.getItem('token')}`
        },
        body: JSON.stringify(submitData)
      });
      
      if (response.ok) {
        const result = await response.json();
        toast.success(`Đã ${isDraft ? 'lưu nháp' : 'tạo'} yêu cầu thay đổi`);
        navigate(`/change-requests/${result.data.id}`);
      } else {
        throw new Error('API call failed');
      }
    } catch (error) {
      console.error('Error creating change request:', error);
      toast.error('Không thể tạo yêu cầu thay đổi');
    } finally {
      setLoading(false);
    }
  };

  const handleInputChange = (field: keyof CRFormData, value: any) => {
    setFormData(prev => ({ ...prev, [field]: value }));
    
    // Clear error when user starts typing
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: '' }));
    }
  };

  const addKPIField = () => {
    setFormData(prev => ({
      ...prev,
      impact_kpi: [...prev.impact_kpi, { key: '', value: '' }]
    }));
  };

  const removeKPIField = (index: number) => {
    setFormData(prev => ({
      ...prev,
      impact_kpi: prev.impact_kpi.filter((_, i) => i !== index)
    }));
  };

  const updateKPIField = (index: number, field: 'key' | 'value', value: string) => {
    setFormData(prev => ({
      ...prev,
      impact_kpi: prev.impact_kpi.map((kpi, i) => 
        i === index ? { ...kpi, [field]: value } : kpi
      )
    }));
  };

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files || []);
    setFormData(prev => ({
      ...prev,
      attachments: [...prev.attachments, ...files]
    }));
  };

  const removeFile = (index: number) => {
    setFormData(prev => ({
      ...prev,
      attachments: prev.attachments.filter((_, i) => i !== index)
    }));
  };

  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center gap-4">
        <Button
          variant="ghost"
          size="sm"
          onClick={() => navigate('/change-requests')}
          className="flex items-center gap-2"
        >
          <ArrowLeftIcon className="h-4 w-4" />
          Quay lại
        </Button>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Tạo yêu cầu thay đổi</h1>
          <p className="text-gray-600">Tạo yêu cầu thay đổi mới cho dự án</p>
        </div>
      </div>

      <form onSubmit={(e) => handleSubmit(e, false)} className="space-y-6">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Form chính */}
          <div className="lg:col-span-2 space-y-6">
            {/* Thông tin cơ bản */}
            <Card className="p-6">
              <h2 className="text-lg font-semibold mb-4">Thông tin cơ bản</h2>
              
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Tiêu đề <span className="text-red-500">*</span>
                  </label>
                  <Input
                    value={formData.title}
                    onChange={(e) => handleInputChange('title', e.target.value)}
                    placeholder="Nhập tiêu đề yêu cầu thay đổi"
                    error={errors.title}
                  />
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Dự án <span className="text-red-500">*</span>
                  </label>
                  <Select
                    value={formData.project_id}
                    onValueChange={(value) => handleInputChange('project_id', value)}
                    error={errors.project_id}
                  >
                    <option value="">Chọn dự án</option>
                    {projects.map(project => (
                      <option key={project.id} value={project.id}>
                        {project.name}
                      </option>
                    ))}
                  </Select>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Mô tả chi tiết <span className="text-red-500">*</span>
                  </label>
                  <Textarea
                    value={formData.description}
                    onChange={(e) => handleInputChange('description', e.target.value)}
                    placeholder="Mô tả chi tiết về yêu cầu thay đổi, lý do và các yêu cầu kỹ thuật"
                    rows={4}
                    error={errors.description}
                  />
                </div>
              </div>
            </Card>

            {/* Tác động */}
            <Card className="p-6">
              <h2 className="text-lg font-semibold mb-4">Tác động dự kiến</h2>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Tác động thời gian (ngày)
                  </label>
                  <Input
                    type="number"
                    value={formData.impact_days}
                    onChange={(e) => handleInputChange('impact_days', parseInt(e.target.value) || 0)}
                    placeholder="0"
                    className="text-right"
                  />
                  <p className="text-xs text-gray-500 mt-1">
                    Số ngày dương: tăng thời gian, số âm: giảm thời gian
                  </p>
                </div>
                
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-1">
                    Tác động chi phí (VNĐ)
                  </label>
                  <Input
                    type="number"
                    value={formData.impact_cost}
                    onChange={(e) => handleInputChange('impact_cost', parseInt(e.target.value) || 0)}
                    placeholder="0"
                    className="text-right"
                  />
                  <p className="text-xs text-gray-500 mt-1">
                    {formData.impact_cost !== 0 && (
                      <span className={formData.impact_cost > 0 ? 'text-red-600' : 'text-green-600'}>
                        {formatCurrency(formData.impact_cost)}
                      </span>
                    )}
                  </p>
                </div>
              </div>
              
              <div>
                <div className="flex items-center justify-between mb-3">
                  <label className="block text-sm font-medium text-gray-700">
                    Tác động KPI <span className="text-red-500">*</span>
                  </label>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={addKPIField}
                    className="flex items-center gap-1"
                  >
                    <PlusIcon className="h-4 w-4" />
                    Thêm KPI
                  </Button>
                </div>
                
                <div className="space-y-2">
                  {formData.impact_kpi.map((entry, index) => (
                    <div key={index} className="flex items-center gap-2">
                      <Input
                        placeholder="Tên KPI (vd: quality, cost, timeline)"
                        value={entry.key}
                        onChange={(e) => updateKPIField(index, 'key', e.target.value)}
                        className="flex-1"
                      />
                      <Input
                        placeholder="Giá trị (vd: +10%, -5%)"
                        value={entry.value}
                        onChange={(e) => updateKPIField(index, 'value', e.target.value)}
                        className="flex-1"
                      />
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => removeKPIField(index)}
                        className="text-red-600 hover:text-red-800"
                      >
                        <TrashIcon className="h-4 w-4" />
                      </Button>
                    </div>
                  ))}
                  
                  {formData.impact_kpi.filter(kpi => kpi.key.trim() && kpi.value.trim()).length === 0 && (
                    <p className="text-gray-500 text-sm italic">
                      Chưa có KPI nào được thêm. Nhấn "Thêm KPI" để bổ sung.
                    </p>
                  )}
                </div>
              </div>
            </Card>

            {/* Attachments */}
            <Card className="p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Tài liệu đính kèm</h2>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Chọn file (tối đa 10MB mỗi file)
                  </label>
                  <div className="flex items-center justify-center w-full">
                    <label className="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                      <div className="flex flex-col items-center justify-center pt-5 pb-6">
                        <DocumentArrowUpIcon className="w-8 h-8 mb-4 text-gray-500" />
                        <p className="mb-2 text-sm text-gray-500">
                          <span className="font-semibold">Nhấn để chọn file</span> hoặc kéo thả
                        </p>
                        <p className="text-xs text-gray-500">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG</p>
                      </div>
                      <input
                        type="file"
                        multiple
                        onChange={handleFileUpload}
                        className="hidden"
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                      />
                    </label>
                  </div>
                </div>

                {/* Uploaded Files */}
                {formData.attachments.length > 0 && (
                  <div className="space-y-2">
                    <h3 className="text-sm font-medium text-gray-700">File đã chọn:</h3>
                    {formData.attachments.map((attachment, index) => (
                      <div key={index} className="flex items-center justify-between p-3 border border-gray-200 rounded-lg">
                        <div className="flex items-center gap-3">
                          <DocumentArrowUpIcon className="h-6 w-6 text-gray-400" />
                          <div>
                            <p className="font-medium text-gray-900">{attachment.name}</p>
                            <p className="text-sm text-gray-500">{formatFileSize(attachment.size)}</p>
                          </div>
                        </div>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => removeFile(index)}
                          className="text-red-600 hover:text-red-800"
                        >
                          <TrashIcon className="h-4 w-4" />
                        </Button>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </Card>
          </div>

          {/* Sidebar */}
          <div className="lg:col-span-1 space-y-6">
            {/* Actions */}
            <Card className="p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Thao tác</h2>
              <div className="space-y-3">
                <Button
                  type="button"
                  onClick={(e) => handleSubmit(e, true)}
                  disabled={loading}
                  variant="outline"
                  className="w-full"
                >
                  {loading ? 'Đang xử lý...' : 'Lưu nháp'}
                </Button>
                
                <Button
                  type="submit"
                  disabled={loading}
                  className="w-full"
                >
                  {loading ? 'Đang xử lý...' : 'Gửi phê duyệt'}
                </Button>
              </div>
              
              <div className="mt-4 p-3 bg-blue-50 rounded-lg">
                <p className="text-sm text-blue-800">
                  <strong>Lưu ý:</strong> Sau khi gửi phê duyệt, bạn sẽ không thể chỉnh sửa yêu cầu này.
                </p>
              </div>
            </Card>

            {/* Guidelines */}
            <Card className="p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Hướng dẫn</h2>
              <div className="space-y-3 text-sm text-gray-600">
                <div>
                  <h3 className="font-medium text-gray-900">Tiêu đề:</h3>
                  <p className="text-gray-600">Nhập tiêu đề rõ ràng, ngắn gọn về yêu cầu thay đổi</p>
                </div>
                <div>
                  <h3 className="font-medium text-gray-900">Mô tả:</h3>
                  <p className="text-gray-600">Mô tả chi tiết về yêu cầu thay đổi, lý do và các yêu cầu kỹ thuật</p>
                </div>
                <div>
                  <h3 className="font-medium text-gray-900">Tác động:</h3>
                  <p className="text-gray-600">Ước tính tác động về thời gian, chi phí và các KPI liên quan</p>
                </div>
              </div>
            </Card>
          </div>
        </div>
      </form>
    </div>
  );
};