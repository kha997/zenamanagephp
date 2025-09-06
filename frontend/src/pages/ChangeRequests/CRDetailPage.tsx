import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { 
  ArrowLeftIcon, 
  PencilIcon, 
  CheckIcon, 
  XMarkIcon,
  ClockIcon,
  CurrencyDollarIcon,
  CalendarIcon,
  UserIcon,
  DocumentTextIcon,
  ExclamationTriangleIcon
} from '@heroicons/react/24/outline';
import { Button } from '../../components/ui/Button';
import { Badge } from '../../components/ui/Badge';
import { Card } from '../../components/ui/Card';
import { Modal } from '../../components/ui/Modal';
import { Textarea } from '../../components/ui/Textarea';
import { useAuthStore } from '../../store/authStore';
import { useNotificationStore } from '../../store/notificationStore';
import { formatCurrency, formatDate } from '../../lib/utils';

// Interface cho Change Request
interface ChangeRequest {
  id: string;
  code: string;
  title: string;
  description: string;
  status: 'draft' | 'awaiting_approval' | 'approved' | 'rejected';
  impact_days: number;
  impact_cost: number;
  impact_kpi: Record<string, any>;
  project: {
    id: string;
    name: string;
  };
  created_by: {
    id: string;
    name: string;
    email: string;
  };
  decided_by?: {
    id: string;
    name: string;
    email: string;
  };
  decided_at?: string;
  decision_note?: string;
  created_at: string;
  updated_at: string;
}

// Component chính
export const CRDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuthStore();
  const { addNotification } = useNotificationStore();
  
  const [changeRequest, setChangeRequest] = useState<ChangeRequest | null>(null);
  const [loading, setLoading] = useState(true);
  const [showDecisionModal, setShowDecisionModal] = useState(false);
  const [decisionType, setDecisionType] = useState<'approve' | 'reject'>('approve');
  const [decisionNote, setDecisionNote] = useState('');
  const [submitting, setSubmitting] = useState(false);

  // Fetch chi tiết Change Request
  useEffect(() => {
    const fetchChangeRequest = async () => {
      try {
        setLoading(true);
        const response = await fetch(`/api/v1/change-requests/${id}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json',
          },
        });
        
        if (!response.ok) {
          throw new Error('Không thể tải thông tin Change Request');
        }
        
        const data = await response.json();
        setChangeRequest(data.data);
      } catch (error) {
        console.error('Error fetching change request:', error);
        addNotification({
          type: 'error',
          title: 'Lỗi',
          message: 'Không thể tải thông tin Change Request'
        });
      } finally {
        setLoading(false);
      }
    };

    if (id) {
      fetchChangeRequest();
    }
  }, [id, addNotification]);

  // Xử lý quyết định (approve/reject)
  const handleDecision = async () => {
    if (!changeRequest || !decisionNote.trim()) {
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Vui lòng nhập ghi chú quyết định'
      });
      return;
    }

    try {
      setSubmitting(true);
      const response = await fetch(`/api/v1/change-requests/${changeRequest.id}/decision`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          decision: decisionType,
          decision_note: decisionNote
        })
      });

      if (!response.ok) {
        throw new Error('Không thể xử lý quyết định');
      }

      const data = await response.json();
      setChangeRequest(data.data);
      setShowDecisionModal(false);
      setDecisionNote('');
      
      addNotification({
        type: 'success',
        title: 'Thành công',
        message: `Change Request đã được ${decisionType === 'approve' ? 'phê duyệt' : 'từ chối'}`
      });
    } catch (error) {
      console.error('Error making decision:', error);
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể xử lý quyết định'
      });
    } finally {
      setSubmitting(false);
    }
  };

  // Render status badge
  const renderStatusBadge = (status: string) => {
    const statusConfig = {
      draft: { color: 'gray', text: 'Nháp' },
      awaiting_approval: { color: 'yellow', text: 'Chờ phê duyệt' },
      approved: { color: 'green', text: 'Đã phê duyệt' },
      rejected: { color: 'red', text: 'Đã từ chối' }
    };
    
    const config = statusConfig[status as keyof typeof statusConfig] || statusConfig.draft;
    return <Badge color={config.color}>{config.text}</Badge>;
  };

  // Kiểm tra quyền phê duyệt
  const canApprove = user?.roles?.some(role => 
    ['project_manager', 'admin'].includes(role.code)
  ) && changeRequest?.status === 'awaiting_approval';

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (!changeRequest) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen">
        <ExclamationTriangleIcon className="h-16 w-16 text-gray-400 mb-4" />
        <h2 className="text-xl font-semibold text-gray-900 mb-2">Không tìm thấy Change Request</h2>
        <Button onClick={() => navigate('/change-requests')} variant="outline">
          Quay lại danh sách
        </Button>
      </div>
    );
  }

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <Button
              onClick={() => navigate('/change-requests')}
              variant="outline"
              size="sm"
            >
              <ArrowLeftIcon className="h-4 w-4 mr-2" />
              Quay lại
            </Button>
            <div>
              <h1 className="text-2xl font-bold text-gray-900">
                {changeRequest.code}: {changeRequest.title}
              </h1>
              <p className="text-sm text-gray-500 mt-1">
                Dự án: {changeRequest.project.name}
              </p>
            </div>
          </div>
          <div className="flex items-center space-x-3">
            {renderStatusBadge(changeRequest.status)}
            {canApprove && (
              <div className="flex space-x-2">
                <Button
                  onClick={() => {
                    setDecisionType('approve');
                    setShowDecisionModal(true);
                  }}
                  variant="primary"
                  size="sm"
                >
                  <CheckIcon className="h-4 w-4 mr-2" />
                  Phê duyệt
                </Button>
                <Button
                  onClick={() => {
                    setDecisionType('reject');
                    setShowDecisionModal(true);
                  }}
                  variant="danger"
                  size="sm"
                >
                  <XMarkIcon className="h-4 w-4 mr-2" />
                  Từ chối
                </Button>
              </div>
            )}
            {changeRequest.status === 'draft' && changeRequest.created_by.id === user?.id && (
              <Button
                onClick={() => navigate(`/change-requests/${changeRequest.id}/edit`)}
                variant="outline"
                size="sm"
              >
                <PencilIcon className="h-4 w-4 mr-2" />
                Chỉnh sửa
              </Button>
            )}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Thông tin chính */}
        <div className="lg:col-span-2 space-y-6">
          {/* Mô tả */}
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <DocumentTextIcon className="h-5 w-5 mr-2" />
                Mô tả chi tiết
              </h3>
              <div className="prose max-w-none">
                <p className="text-gray-700 whitespace-pre-wrap">
                  {changeRequest.description}
                </p>
              </div>
            </div>
          </Card>

          {/* Tác động */}
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">
                Tác động dự kiến
              </h3>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div className="bg-blue-50 p-4 rounded-lg">
                  <div className="flex items-center">
                    <CalendarIcon className="h-5 w-5 text-blue-600 mr-2" />
                    <span className="text-sm font-medium text-blue-900">Thời gian</span>
                  </div>
                  <p className="text-2xl font-bold text-blue-600 mt-2">
                    {changeRequest.impact_days > 0 ? `+${changeRequest.impact_days}` : changeRequest.impact_days} ngày
                  </p>
                </div>
                <div className="bg-green-50 p-4 rounded-lg">
                  <div className="flex items-center">
                    <CurrencyDollarIcon className="h-5 w-5 text-green-600 mr-2" />
                    <span className="text-sm font-medium text-green-900">Chi phí</span>
                  </div>
                  <p className="text-2xl font-bold text-green-600 mt-2">
                    {changeRequest.impact_cost > 0 ? '+' : ''}{formatCurrency(changeRequest.impact_cost)}
                  </p>
                </div>
                <div className="bg-purple-50 p-4 rounded-lg">
                  <div className="flex items-center">
                    <ClockIcon className="h-5 w-5 text-purple-600 mr-2" />
                    <span className="text-sm font-medium text-purple-900">KPI</span>
                  </div>
                  <p className="text-sm text-purple-600 mt-2">
                    {Object.keys(changeRequest.impact_kpi || {}).length} chỉ số
                  </p>
                </div>
              </div>
            </div>
          </Card>

          {/* Quyết định (nếu có) */}
          {changeRequest.decided_at && (
            <Card>
              <div className="p-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4">
                  Quyết định
                </h3>
                <div className="bg-gray-50 p-4 rounded-lg">
                  <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center">
                      <UserIcon className="h-5 w-5 text-gray-600 mr-2" />
                      <span className="font-medium text-gray-900">
                        {changeRequest.decided_by?.name}
                      </span>
                    </div>
                    <span className="text-sm text-gray-500">
                      {formatDate(changeRequest.decided_at)}
                    </span>
                  </div>
                  {changeRequest.decision_note && (
                    <p className="text-gray-700 whitespace-pre-wrap">
                      {changeRequest.decision_note}
                    </p>
                  )}
                </div>
              </div>
            </Card>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Thông tin cơ bản */}
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">
                Thông tin cơ bản
              </h3>
              <dl className="space-y-3">
                <div>
                  <dt className="text-sm font-medium text-gray-500">Mã CR</dt>
                  <dd className="text-sm text-gray-900 font-mono">{changeRequest.code}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Trạng thái</dt>
                  <dd className="text-sm text-gray-900">
                    {renderStatusBadge(changeRequest.status)}
                  </dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Người tạo</dt>
                  <dd className="text-sm text-gray-900">{changeRequest.created_by.name}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Ngày tạo</dt>
                  <dd className="text-sm text-gray-900">
                    {formatDate(changeRequest.created_at)}
                  </dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Cập nhật cuối</dt>
                  <dd className="text-sm text-gray-900">
                    {formatDate(changeRequest.updated_at)}
                  </dd>
                </div>
              </dl>
            </div>
          </Card>

          {/* Timeline */}
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">
                Lịch sử thay đổi
              </h3>
              <div className="space-y-4">
                <div className="flex items-start">
                  <div className="flex-shrink-0">
                    <div className="h-8 w-8 bg-blue-100 rounded-full flex items-center justify-center">
                      <UserIcon className="h-4 w-4 text-blue-600" />
                    </div>
                  </div>
                  <div className="ml-3">
                    <p className="text-sm font-medium text-gray-900">
                      Tạo Change Request
                    </p>
                    <p className="text-sm text-gray-500">
                      {changeRequest.created_by.name} • {formatDate(changeRequest.created_at)}
                    </p>
                  </div>
                </div>
                
                {changeRequest.decided_at && (
                  <div className="flex items-start">
                    <div className="flex-shrink-0">
                      <div className={`h-8 w-8 rounded-full flex items-center justify-center ${
                        changeRequest.status === 'approved' 
                          ? 'bg-green-100' 
                          : 'bg-red-100'
                      }`}>
                        {changeRequest.status === 'approved' ? (
                          <CheckIcon className="h-4 w-4 text-green-600" />
                        ) : (
                          <XMarkIcon className="h-4 w-4 text-red-600" />
                        )}
                      </div>
                    </div>
                    <div className="ml-3">
                      <p className="text-sm font-medium text-gray-900">
                        {changeRequest.status === 'approved' ? 'Phê duyệt' : 'Từ chối'}
                      </p>
                      <p className="text-sm text-gray-500">
                        {changeRequest.decided_by?.name} • {formatDate(changeRequest.decided_at)}
                      </p>
                    </div>
                  </div>
                )}
              </div>
            </div>
          </Card>
        </div>
      </div>

      {/* Modal quyết định */}
      <Modal
        isOpen={showDecisionModal}
        onClose={() => setShowDecisionModal(false)}
        title={`${decisionType === 'approve' ? 'Phê duyệt' : 'Từ chối'} Change Request`}
      >
        <div className="space-y-4">
          <p className="text-sm text-gray-600">
            Bạn có chắc chắn muốn {decisionType === 'approve' ? 'phê duyệt' : 'từ chối'} Change Request này?
          </p>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Ghi chú quyết định *
            </label>
            <Textarea
              value={decisionNote}
              onChange={(e) => setDecisionNote(e.target.value)}
              placeholder="Nhập lý do và ghi chú cho quyết định này..."
              rows={4}
              required
            />
          </div>
          
          <div className="flex justify-end space-x-3">
            <Button
              onClick={() => setShowDecisionModal(false)}
              variant="outline"
              disabled={submitting}
            >
              Hủy
            </Button>
            <Button
              onClick={handleDecision}
              variant={decisionType === 'approve' ? 'primary' : 'danger'}
              loading={submitting}
              disabled={!decisionNote.trim()}
            >
              {decisionType === 'approve' ? 'Phê duyệt' : 'Từ chối'}
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
};