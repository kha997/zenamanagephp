import React, { useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { ChangeRequestForm } from '../components/ChangeRequestForm';
import { useChangeRequestsStore } from '../../../store/changeRequests';
import type { UpdateChangeRequestData } from '../../../lib/types';

export const EditChangeRequest: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { 
    currentChangeRequest, 
    loading, 
    error, 
    fetchChangeRequest, 
    updateChangeRequest 
  } = useChangeRequestsStore();

  useEffect(() => {
    if (id) {
      fetchChangeRequest(id);
    }
  }, [id, fetchChangeRequest]);

  const handleSubmit = async (data: UpdateChangeRequestData) => {
    if (!currentChangeRequest) return;
    
    try {
      await updateChangeRequest(currentChangeRequest.id, data);
      navigate(`/change-requests/${currentChangeRequest.id}`);
    } catch (error) {
      console.error('Lỗi khi cập nhật Change Request:', error);
      // Error sẽ được hiển thị trong form thông qua store
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-md p-4">
        <p className="text-red-800">Lỗi: {error}</p>
        <Link to="/change-requests" className="text-blue-600 hover:text-blue-800 mt-2 inline-block">
          ← Quay lại danh sách
        </Link>
      </div>
    );
  }

  if (!currentChangeRequest) {
    return (
      <div className="text-center py-12">
        <h3 className="mt-2 text-sm font-medium text-gray-900">
          Không tìm thấy Change Request
        </h3>
        <Link to="/change-requests" className="text-blue-600 hover:text-blue-800 mt-2 inline-block">
          ← Quay lại danh sách
        </Link>
      </div>
    );
  }

  // Chỉ cho phép chỉnh sửa khi ở trạng thái draft
  if (currentChangeRequest.status !== 'draft') {
    return (
      <div className="bg-yellow-50 border border-yellow-200 rounded-md p-4">
        <p className="text-yellow-800">
          Không thể chỉnh sửa Change Request đã được gửi duyệt hoặc đã có quyết định.
        </p>
        <Link 
          to={`/change-requests/${currentChangeRequest.id}`} 
          className="text-blue-600 hover:text-blue-800 mt-2 inline-block"
        >
          ← Quay lại chi tiết
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center space-x-4">
        <Link
          to={`/change-requests/${currentChangeRequest.id}`}
          className="inline-flex items-center text-gray-600 hover:text-gray-900"
        >
          <ArrowLeft className="w-4 h-4 mr-1" />
          Quay lại
        </Link>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            Chỉnh sửa Change Request
          </h1>
          <p className="text-gray-600 mt-1">
            {currentChangeRequest.code} - {currentChangeRequest.title}
          </p>
        </div>
      </div>

      {/* Form */}
      <div className="bg-white rounded-lg shadow-sm border">
        <div className="p-6">
          <ChangeRequestForm
            initialData={{
              title: currentChangeRequest.title,
              description: currentChangeRequest.description,
              impact_days: currentChangeRequest.impact_days,
              impact_cost: currentChangeRequest.impact_cost,
              impact_kpi: currentChangeRequest.impact_kpi
            }}
            onSubmit={handleSubmit}
            submitButtonText="Cập nhật Change Request"
          />
        </div>
      </div>
    </div>
  );
};