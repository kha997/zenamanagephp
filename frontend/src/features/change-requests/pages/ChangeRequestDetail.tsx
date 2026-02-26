import React, { useEffect, useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { ArrowLeft, Edit, Trash2, CheckCircle, XCircle, Clock, FileText } from 'lucide-react';
import { useChangeRequestsStore } from '../../../store/changeRequests';
import { StatusBadge } from '../components/StatusBadge';
import { DecisionModal } from '../components/DecisionModal';
import type { ChangeRequestDecision, User } from '../../../lib/types';

export const ChangeRequestDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { 
    currentChangeRequest, 
    loading, 
    error, 
    fetchChangeRequest, 
    deleteChangeRequest,
    approveChangeRequest,
    rejectChangeRequest
  } = useChangeRequestsStore();
  
  const [showDecisionModal, setShowDecisionModal] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  const getUserLabel = (user?: User | null) => {
    if (!user) return 'N/A';
    return user.name || user.email || 'N/A';
  };

  useEffect(() => {
    if (id) {
      fetchChangeRequest(id);
    }
  }, [id, fetchChangeRequest]);

  const handleDecision = async ({ decision, decision_note }: ChangeRequestDecision) => {
    if (!currentChangeRequest) return;

    try {
      if (decision === 'approve') {
        await approveChangeRequest(currentChangeRequest.id, decision_note);
      } else {
        await rejectChangeRequest(currentChangeRequest.id, decision_note);
      }
      setShowDecisionModal(false);
    } catch (error) {
      console.error('Lỗi khi xử lý quyết định:', error);
    }
  };

  const handleDelete = async () => {
    if (!currentChangeRequest || !confirm('Bạn có chắc chắn muốn xóa Change Request này?')) {
      return;
    }

    setIsDeleting(true);
    try {
      await deleteChangeRequest(currentChangeRequest.id);
      navigate('/change-requests');
    } catch (error) {
      console.error('Lỗi khi xóa Change Request:', error);
    } finally {
      setIsDeleting(false);
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: 'VND'
    }).format(amount);
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('vi-VN', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  if (loading.isLoading) {
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
        <FileText className="mx-auto h-12 w-12 text-gray-400" />
        <h3 className="mt-2 text-sm font-medium text-gray-900">
          Không tìm thấy Change Request
        </h3>
        <Link to="/change-requests" className="text-blue-600 hover:text-blue-800 mt-2 inline-block">
          ← Quay lại danh sách
        </Link>
      </div>
    );
  }

  const canEdit = currentChangeRequest.status === 'draft';
  const canDecide = currentChangeRequest.status === 'awaiting_approval';
  const canDelete = currentChangeRequest.status === 'draft';

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
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
              {currentChangeRequest.code}
            </h1>
            <p className="text-gray-600">{currentChangeRequest.title}</p>
          </div>
        </div>
        
        <div className="flex items-center space-x-3">
          <StatusBadge status={currentChangeRequest.status} />
          
          {canEdit && (
            <Link
              to={`/change-requests/${currentChangeRequest.id}/edit`}
              className="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
            >
              <Edit className="w-4 h-4 mr-2" />
              Chỉnh sửa
            </Link>
          )}
          
          {canDecide && (
            <>
              <button
                onClick={() => {
                  setShowDecisionModal(true);
                }}
                className="inline-flex items-center px-3 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700"
              >
                <CheckCircle className="w-4 h-4 mr-2" />
                Duyệt
              </button>
              <button
                onClick={() => {
                  setShowDecisionModal(true);
                }}
                className="inline-flex items-center px-3 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700"
              >
                <XCircle className="w-4 h-4 mr-2" />
                Từ chối
              </button>
            </>
          )}
          
          {canDelete && (
            <button
              onClick={handleDelete}
              disabled={isDeleting}
              className="inline-flex items-center px-3 py-2 border border-red-300 rounded-md text-sm font-medium text-red-700 bg-white hover:bg-red-50 disabled:opacity-50"
            >
              <Trash2 className="w-4 h-4 mr-2" />
              {isDeleting ? 'Đang xóa...' : 'Xóa'}
            </button>
          )}
        </div>
      </div>

      {/* Content */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Description */}
          <div className="bg-white rounded-lg shadow-sm border p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Mô tả</h2>
            <div className="prose max-w-none">
              <p className="text-gray-700 whitespace-pre-wrap">
                {currentChangeRequest.description}
              </p>
            </div>
          </div>

          {/* Impact Analysis */}
          <div className="bg-white rounded-lg shadow-sm border p-6">
            <h2 className="text-lg font-semibold text-gray-900 mb-4">Phân tích tác động</h2>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="bg-blue-50 p-4 rounded-lg">
                <div className="flex items-center">
                  <Clock className="w-5 h-5 text-blue-600 mr-2" />
                  <span className="text-sm font-medium text-blue-900">Thời gian</span>
                </div>
                <p className="text-2xl font-bold text-blue-900 mt-2">
                  {currentChangeRequest.impact_days} ngày
                </p>
              </div>
              
              <div className="bg-green-50 p-4 rounded-lg">
                <div className="flex items-center">
                  <span className="text-sm font-medium text-green-900">Chi phí</span>
                </div>
                <p className="text-2xl font-bold text-green-900 mt-2">
                  {formatCurrency(currentChangeRequest.impact_cost)}
                </p>
              </div>
              
              <div className="bg-purple-50 p-4 rounded-lg">
                <div className="flex items-center">
                  <span className="text-sm font-medium text-purple-900">KPI</span>
                </div>
                <div className="mt-2">
                  {Object.entries(currentChangeRequest.impact_kpi).map(([key, value]) => (
                    <div key={key} className="text-sm text-purple-900">
                      <span className="font-medium">{key}:</span> {value}
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>

          {/* Decision History */}
          {(currentChangeRequest.status === 'approved' || currentChangeRequest.status === 'rejected') && (
            <div className="bg-white rounded-lg shadow-sm border p-6">
              <h2 className="text-lg font-semibold text-gray-900 mb-4">Quyết định</h2>
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-gray-600">Trạng thái:</span>
                  <StatusBadge status={currentChangeRequest.status} />
                </div>
                {currentChangeRequest.decided_by && (
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Người quyết định:</span>
                    <span className="text-sm font-medium">{getUserLabel(currentChangeRequest.decided_by)}</span>
                  </div>
                )}
                {currentChangeRequest.decided_at && (
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-600">Thời gian quyết định:</span>
                    <span className="text-sm">{formatDate(currentChangeRequest.decided_at)}</span>
                  </div>
                )}
                {currentChangeRequest.decision_note && (
                  <div>
                    <span className="text-sm text-gray-600">Ghi chú:</span>
                    <p className="text-sm mt-1 p-3 bg-gray-50 rounded">
                      {currentChangeRequest.decision_note}
                    </p>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Basic Info */}
          <div className="bg-white rounded-lg shadow-sm border p-6">
            <h3 className="text-lg font-semibold text-gray-900 mb-4">Thông tin cơ bản</h3>
            <div className="space-y-3">
              <div>
                <span className="text-sm text-gray-600">Mã CR:</span>
                <p className="font-medium">{currentChangeRequest.code}</p>
              </div>
              <div>
                <span className="text-sm text-gray-600">Dự án:</span>
                <p className="font-medium">{currentChangeRequest.project_id}</p>
              </div>
              <div>
                <span className="text-sm text-gray-600">Người tạo:</span>
                <p className="font-medium">{getUserLabel(currentChangeRequest.created_by)}</p>
              </div>
              <div>
                <span className="text-sm text-gray-600">Ngày tạo:</span>
                <p className="font-medium">{formatDate(currentChangeRequest.created_at)}</p>
              </div>
              <div>
                <span className="text-sm text-gray-600">Cập nhật cuối:</span>
                <p className="font-medium">{formatDate(currentChangeRequest.updated_at)}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Decision Modal */}
      {showDecisionModal && (
        <DecisionModal
          isOpen={showDecisionModal}
          onClose={() => setShowDecisionModal(false)}
          changeRequest={currentChangeRequest}
          onDecision={handleDecision}
        />
      )}
    </div>
  );
};
