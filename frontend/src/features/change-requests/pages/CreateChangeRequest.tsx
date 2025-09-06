import React from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { ChangeRequestForm } from '../components/ChangeRequestForm';
import { useChangeRequestsStore } from '../../../store/changeRequests';
import type { CreateChangeRequestData } from '../../../lib/types';

export const CreateChangeRequest: React.FC = () => {
  const navigate = useNavigate();
  const { createChangeRequest } = useChangeRequestsStore();

  const handleSubmit = async (data: CreateChangeRequestData) => {
    try {
      const newChangeRequest = await createChangeRequest(data);
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
            submitButtonText="Tạo Change Request"
          />
        </div>
      </div>
    </div>
  );
};