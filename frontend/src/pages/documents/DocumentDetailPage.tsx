import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  ArrowLeftIcon,
  DocumentTextIcon,
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  ClockIcon,
  UserIcon,
  EyeIcon,
  ArrowUturnLeftIcon,
  PlusIcon
} from '@/lib/heroicons';
import { Button } from '../../components/ui/Button';
import { Badge } from '../../components/ui/Badge';
import { Card } from '../../components/ui/Card';
import { Table } from '../../components/ui/Table';
import { Modal } from '../../components/ui/Modal';
import { Textarea } from '../../components/ui/Textarea';
import { useAuthStore } from '../../store/authStore';
import { useNotificationStore } from '../../store/notificationStore';
import { formatDate, formatFileSize } from '../../lib/utils';

// Interface cho Document và Version
interface DocumentVersion {
  id: string;
  version_number: number;
  file_path: string;
  file_size: number;
  file_type: string;
  comment: string;
  created_by: {
    id: string;
    name: string;
    email: string;
  };
  reverted_from_version_number?: number;
  created_at: string;
}

interface DocumentDetail {
  id: string;
  title: string;
  linked_entity_type: 'task' | 'diary' | 'cr' | null;
  linked_entity_id: string | null;
  linked_entity_name?: string;
  current_version_id: string;
  project: {
    id: string;
    name: string;
  };
  versions: DocumentVersion[];
  created_at: string;
  updated_at: string;
}

// Component chính
export const DocumentDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuthStore();
  const { addNotification } = useNotificationStore();
  
  const [document, setDocument] = useState<DocumentDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [showRevertModal, setShowRevertModal] = useState(false);
  const [selectedVersion, setSelectedVersion] = useState<DocumentVersion | null>(null);
  const [uploadFile, setUploadFile] = useState<File | null>(null);
  const [uploadComment, setUploadComment] = useState('');
  const [revertComment, setRevertComment] = useState('');
  const [uploading, setUploading] = useState(false);

  // Fetch chi tiết document
  useEffect(() => {
    const fetchDocument = async () => {
      try {
        setLoading(true);
        const response = await fetch(`/api/v1/documents/${id}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'Content-Type': 'application/json',
          },
        });
        
        if (!response.ok) {
          throw new Error('Không thể tải thông tin tài liệu');
        }
        
        const data = await response.json();
        setDocument(data.data);
      } catch (error) {
        console.error('Error fetching document:', error);
        addNotification({
          type: 'error',
          title: 'Lỗi',
          message: 'Không thể tải thông tin tài liệu'
        });
      } finally {
        setLoading(false);
      }
    };

    if (id) {
      fetchDocument();
    }
  }, [id, addNotification]);

  // Xử lý upload phiên bản mới
  const handleUploadNewVersion = async () => {
    if (!uploadFile || !uploadComment.trim()) {
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Vui lòng chọn file và nhập ghi chú'
      });
      return;
    }

    try {
      setUploading(true);
      const formData = new FormData();
      formData.append('file', uploadFile);
      formData.append('comment', uploadComment);
      
      const response = await fetch(`/api/v1/documents/${id}/versions`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
        body: formData
      });

      if (!response.ok) {
        throw new Error('Không thể tải lên phiên bản mới');
      }

      const data = await response.json();
      setDocument(data.data);
      setShowUploadModal(false);
      setUploadFile(null);
      setUploadComment('');
      
      addNotification({
        type: 'success',
        title: 'Thành công',
        message: 'Đã tải lên phiên bản mới'
      });
    } catch (error) {
      console.error('Error uploading version:', error);
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể tải lên phiên bản mới'
      });
    } finally {
      setUploading(false);
    }
  };

  // Xử lý revert về phiên bản cũ
  const handleRevertVersion = async () => {
    if (!selectedVersion || !revertComment.trim()) {
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Vui lòng nhập ghi chú cho việc revert'
      });
      return;
    }

    try {
      setUploading(true);
      const response = await fetch(`/api/v1/documents/${id}/revert`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          version_id: selectedVersion.id,
          comment: revertComment
        })
      });

      if (!response.ok) {
        throw new Error('Không thể revert về phiên bản này');
      }

      const data = await response.json();
      setDocument(data.data);
      setShowRevertModal(false);
      setSelectedVersion(null);
      setRevertComment('');
      
      addNotification({
        type: 'success',
        title: 'Thành công',
        message: `Đã revert về phiên bản ${selectedVersion.version_number}`
      });
    } catch (error) {
      console.error('Error reverting version:', error);
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể revert về phiên bản này'
      });
    } finally {
      setUploading(false);
    }
  };

  // Xử lý download file
  const handleDownload = async (version: DocumentVersion) => {
    try {
      const response = await fetch(`/api/v1/documents/${id}/versions/${version.id}/download`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
      });
      
      if (!response.ok) {
        throw new Error('Không thể tải xuống file');
      }
      
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${document?.title}_v${version.version_number}`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
    } catch (error) {
      console.error('Error downloading file:', error);
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể tải xuống file'
      });
    }
  };

  // Render type badge
  const renderTypeBadge = (type: string | null) => {
    if (!type) return <Badge color="gray">Chung</Badge>;
    
    const typeConfig = {
      task: { color: 'blue', text: 'Nhiệm vụ' },
      diary: { color: 'green', text: 'Nhật ký' },
      cr: { color: 'purple', text: 'Change Request' }
    };
    
    const config = typeConfig[type as keyof typeof typeConfig];
    return <Badge color={config.color}>{config.text}</Badge>;
  };

  // Columns cho bảng versions
  const versionColumns = [
    {
      key: 'version',
      label: 'Phiên bản',
      render: (version: DocumentVersion) => (
        <div className="flex items-center space-x-2">
          <span className="font-mono font-medium">v{version.version_number}</span>
          {version.id === document?.current_version_id && (
            <Badge color="green" size="sm">Hiện tại</Badge>
          )}
          {version.reverted_from_version_number && (
            <Badge color="yellow" size="sm">
              Revert từ v{version.reverted_from_version_number}
            </Badge>
          )}
        </div>
      )
    },
    {
      key: 'file_info',
      label: 'Thông tin file',
      render: (version: DocumentVersion) => (
        <div className="text-sm">
          <div className="font-medium text-gray-900">{version.file_type}</div>
          <div className="text-gray-500">{formatFileSize(version.file_size)}</div>
        </div>
      )
    },
    {
      key: 'comment',
      label: 'Ghi chú',
      render: (version: DocumentVersion) => (
        <div className="text-sm text-gray-700 max-w-xs truncate" title={version.comment}>
          {version.comment}
        </div>
      )
    },
    {
      key: 'created_by',
      label: 'Người tạo',
      render: (version: DocumentVersion) => (
        <div className="text-sm">
          <div className="font-medium text-gray-900">{version.created_by.name}</div>
          <div className="text-gray-500">{formatDate(version.created_at)}</div>
        </div>
      )
    },
    {
      key: 'actions',
      label: 'Thao tác',
      render: (version: DocumentVersion) => (
        <div className="flex items-center space-x-2">
          <Button
            onClick={() => handleDownload(version)}
            variant="outline"
            size="sm"
          >
            <ArrowDownTrayIcon className="h-4 w-4" />
          </Button>
          {version.id !== document?.current_version_id && (
            <Button
              onClick={() => {
                setSelectedVersion(version);
                setShowRevertModal(true);
              }}
              variant="outline"
              size="sm"
            >
              <ArrowUturnLeftIcon className="h-4 w-4" />
            </Button>
          )}
        </div>
      )
    }
  ];

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (!document) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen">
        <DocumentTextIcon className="h-16 w-16 text-gray-400 mb-4" />
        <h2 className="text-xl font-semibold text-gray-900 mb-2">Không tìm thấy tài liệu</h2>
        <Button onClick={() => navigate('/documents')} variant="outline">
          Quay lại danh sách
        </Button>
      </div>
    );
  }

  const currentVersion = document.versions.find(v => v.id === document.current_version_id);

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <Button
              onClick={() => navigate('/documents')}
              variant="outline"
              size="sm"
            >
              <ArrowLeftIcon className="h-4 w-4 mr-2" />
              Quay lại
            </Button>
            <div>
              <h1 className="text-2xl font-bold text-gray-900">{document.title}</h1>
              <p className="text-sm text-gray-500 mt-1">
                Dự án: {document.project.name}
              </p>
            </div>
          </div>
          <div className="flex items-center space-x-3">
            {renderTypeBadge(document.linked_entity_type)}
            <Button
              onClick={() => setShowUploadModal(true)}
              variant="primary"
            >
              <ArrowUpTrayIcon className="h-4 w-4 mr-2" />
              Tải lên phiên bản mới
            </Button>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Thông tin chính */}
        <div className="lg:col-span-2 space-y-6">
          {/* Phiên bản hiện tại */}
          {currentVersion && (
            <Card>
              <div className="p-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                  <DocumentTextIcon className="h-5 w-5 mr-2" />
                  Phiên bản hiện tại (v{currentVersion.version_number})
                </h3>
                <div className="bg-gray-50 p-4 rounded-lg">
                  <div className="grid grid-cols-2 gap-4 mb-4">
                    <div>
                      <span className="text-sm font-medium text-gray-500">Loại file:</span>
                      <p className="text-sm text-gray-900">{currentVersion.file_type}</p>
                    </div>
                    <div>
                      <span className="text-sm font-medium text-gray-500">Kích thước:</span>
                      <p className="text-sm text-gray-900">{formatFileSize(currentVersion.file_size)}</p>
                    </div>
                    <div>
                      <span className="text-sm font-medium text-gray-500">Người tạo:</span>
                      <p className="text-sm text-gray-900">{currentVersion.created_by.name}</p>
                    </div>
                    <div>
                      <span className="text-sm font-medium text-gray-500">Ngày tạo:</span>
                      <p className="text-sm text-gray-900">{formatDate(currentVersion.created_at)}</p>
                    </div>
                  </div>
                  <div className="mb-4">
                    <span className="text-sm font-medium text-gray-500">Ghi chú:</span>
                    <p className="text-sm text-gray-900 mt-1">{currentVersion.comment}</p>
                  </div>
                  <Button
                    onClick={() => handleDownload(currentVersion)}
                    variant="primary"
                    size="sm"
                  >
                    <ArrowDownTrayIcon className="h-4 w-4 mr-2" />
                    Tải xuống
                  </Button>
                </div>
              </div>
            </Card>
          )}

          {/* Lịch sử phiên bản */}
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <ClockIcon className="h-5 w-5 mr-2" />
                Lịch sử phiên bản ({document.versions.length})
              </h3>
              <Table
                data={document.versions}
                columns={versionColumns}
                emptyMessage="Không có phiên bản nào"
              />
            </div>
          </Card>
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
                  <dt className="text-sm font-medium text-gray-500">Tiêu đề</dt>
                  <dd className="text-sm text-gray-900">{document.title}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Loại</dt>
                  <dd className="text-sm text-gray-900">
                    {renderTypeBadge(document.linked_entity_type)}
                  </dd>
                </div>
                {document.linked_entity_name && (
                  <div>
                    <dt className="text-sm font-medium text-gray-500">Liên kết</dt>
                    <dd className="text-sm text-gray-900">{document.linked_entity_name}</dd>
                  </div>
                )}
                <div>
                  <dt className="text-sm font-medium text-gray-500">Dự án</dt>
                  <dd className="text-sm text-gray-900">{document.project.name}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Tổng phiên bản</dt>
                  <dd className="text-sm text-gray-900">{document.versions.length}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Ngày tạo</dt>
                  <dd className="text-sm text-gray-900">{formatDate(document.created_at)}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Cập nhật cuối</dt>
                  <dd className="text-sm text-gray-900">{formatDate(document.updated_at)}</dd>
                </div>
              </dl>
            </div>
          </Card>
        </div>
      </div>

      {/* Modal upload phiên bản mới */}
      <Modal
        isOpen={showUploadModal}
        onClose={() => setShowUploadModal(false)}
        title="Tải lên phiên bản mới"
      >
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Chọn file *
            </label>
            <input
              type="file"
              onChange={(e) => setUploadFile(e.target.files?.[0] || null)}
              className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
              required
            />
          </div>
          
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">
              Ghi chú thay đổi *
            </label>
            <Textarea
              value={uploadComment}
              onChange={(e) => setUploadComment(e.target.value)}
              placeholder="Mô tả những thay đổi trong phiên bản này..."
              rows={3}
              required
            />
          </div>
          
          <div className="flex justify-end space-x-3">
            <Button
              onClick={() => setShowUploadModal(false)}
              variant="outline"
              disabled={uploading}
            >
              Hủy
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
};
