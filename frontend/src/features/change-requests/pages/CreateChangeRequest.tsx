import React from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { ChangeRequestForm } from '../components/ChangeRequestForm';
import { useChangeRequestsStore } from '../../../store/changeRequests';
import type { CreateChangeRequestForm, UpdateChangeRequestForm } from '../../../lib/types';

export const CreateChangeRequest: React.FC = () => {
  const navigate = useNavigate();
  const { createChangeRequest, loading } = useChangeRequestsStore();

  const handleSubmit = async (data: CreateChangeRequestForm | UpdateChangeRequestForm) => {
    try {
      const createPayload = data as CreateChangeRequestForm & { project_id?: string };
      if (!createPayload.project_id) {
        throw new Error('Thiếu project_id để tạo change request');
      }

      const { project_id, ...payload } = createPayload;
      const newChangeRequest = await createChangeRequest(project_id, payload as CreateChangeRequestForm);
      navigate(`/change-requests/${newChangeRequest.id}`);
    } catch (error) {
      console.error('Lỗi khi tạo Change Request:', error);
      // Error sẽ được hiển thị trong form thông qua store
    }
  };

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center space-x-4">
        <Link
          to="/change-requests"
          className="inline-flex items-center text-gray-600 hover:text-gray-900"
        >
          <ArrowLeft className="w-4 h-4 mr-1" />
          Quay lại
        </Link>
        <div>
          <h1 className="text-2xl font-bold text-gray-900">
            Tạo Change Request mới
          </h1>
          <p className="text-gray-600 mt-1">
            Tạo yêu cầu thay đổi cho dự án
          </p>
        </div>
      </div>

      {/* Form */}
      <div className="bg-white rounded-lg shadow-sm border">
        <div className="p-6">
          <ChangeRequestForm
            onSubmit={handleSubmit}
            mode="create"
            projectId=""
            isLoading={loading.isLoading}
            onCancel={() => navigate('/change-requests')}
          />
        </div>
      </div>
    </div>
  );
};
