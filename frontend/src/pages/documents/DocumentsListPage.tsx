import React, { useState, useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import {
  DocumentTextIcon,
  PlusIcon,
  MagnifyingGlassIcon,
  FunnelIcon,
  ArrowUpTrayIcon,
  EyeIcon,
  PencilIcon,
  TrashIcon,
  ClockIcon,
  UserIcon,
  FolderIcon
} from '@heroicons/react/24/outline';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Select } from '../../components/ui/Select';
import { Table } from '../../components/ui/Table';
import { Badge } from '../../components/ui/Badge';
import { Card } from '../../components/ui/Card';
import { Pagination } from '../../components/ui/Pagination';
import { useAuthStore } from '../../store/authStore';
import { useNotificationStore } from '../../store/notificationStore';
import { formatDate, formatFileSize } from '../../lib/utils';

// Interface cho Document
interface Document {
  id: string;
  title: string;
  linked_entity_type: 'task' | 'diary' | 'cr' | null;
  linked_entity_id: string | null;
  linked_entity_name?: string;
  current_version: {
    id: string;
    version_number: number;
    file_path: string;
    file_size: number;
    file_type: string;
    comment: string;
    created_by: {
      id: string;
      name: string;
    };
    created_at: string;
  };
  project: {
    id: string;
    name: string;
  };
  total_versions: number;
  created_at: string;
  updated_at: string;
}

// Component chính
export const DocumentsListPage: React.FC = () => {
  const navigate = useNavigate();
  const [searchParams, setSearchParams] = useSearchParams();
  const { user } = useAuthStore();
  const { addNotification } = useNotificationStore();
  
  const [documents, setDocuments] = useState<Document[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState(searchParams.get('search') || '');
  const [selectedProject, setSelectedProject] = useState(searchParams.get('project') || '');
  const [selectedType, setSelectedType] = useState(searchParams.get('type') || '');
  const [currentPage, setCurrentPage] = useState(parseInt(searchParams.get('page') || '1'));
  const [totalPages, setTotalPages] = useState(1);
  const [projects, setProjects] = useState<Array<{id: string, name: string}>>([]);

  // Fetch danh sách documents
  const fetchDocuments = async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams({
        page: currentPage.toString(),
        ...(searchTerm && { search: searchTerm }),
        ...(selectedProject && { project_id: selectedProject }),
        ...(selectedType && { linked_entity_type: selectedType })
      });
      
      const response = await fetch(`/api/v1/documents?${params}`, {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json',
        },
      });
      
      if (!response.ok) {
        throw new Error('Không thể tải danh sách tài liệu');
      }
      
      const data = await response.json();
      setDocuments(data.data.data);
      setTotalPages(data.data.last_page);
    } catch (error) {
      console.error('Error fetching documents:', error);
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể tải danh sách tài liệu'
      });
    } finally {
      setLoading(false);
    }
  };

  // Fetch danh sách projects
  const fetchProjects = async () => {
    try {
      const response = await fetch('/api/v1/projects?per_page=100', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
          'Content-Type': 'application/json',
        },
      });
      
      if (response.ok) {
        const data = await response.json();
        setProjects(data.data.data);
      }
    } catch (error) {
      console.error('Error fetching projects:', error);
    }
  };

  useEffect(() => {
    fetchDocuments();
    fetchProjects();
  }, [currentPage, searchTerm, selectedProject, selectedType]);

  // Cập nhật URL params
  useEffect(() => {
    const params = new URLSearchParams();
    if (searchTerm) params.set('search', searchTerm);
    if (selectedProject) params.set('project', selectedProject);
    if (selectedType) params.set('type', selectedType);
    if (currentPage > 1) params.set('page', currentPage.toString());
    
    setSearchParams(params);
  }, [searchTerm, selectedProject, selectedType, currentPage, setSearchParams]);

  // Xử lý tìm kiếm
  const handleSearch = (value: string) => {
    setSearchTerm(value);
    setCurrentPage(1);
  };

  // Xử lý filter
  const handleFilter = (key: string, value: string) => {
    if (key === 'project') setSelectedProject(value);
    if (key === 'type') setSelectedType(value);
    setCurrentPage(1);
  };

  // Xử lý xóa document
  const handleDelete = async (documentId: string) => {
    if (!confirm('Bạn có chắc chắn muốn xóa tài liệu này?')) return;
    
    try {
      const response = await fetch(`/api/v1/documents/${documentId}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('token')}`,
        },
      });
      
      if (!response.ok) {
        throw new Error('Không thể xóa tài liệu');
      }
      
      addNotification({
        type: 'success',
        title: 'Thành công',
        message: 'Đã xóa tài liệu'
      });
      
      fetchDocuments();
    } catch (error) {
      console.error('Error deleting document:', error);
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể xóa tài liệu'
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

  // Render file type icon
  const renderFileIcon = (fileType: string) => {
    if (fileType.includes('pdf')) {
      return <DocumentTextIcon className="h-5 w-5 text-red-500" />;
    }
    if (fileType.includes('word') || fileType.includes('doc')) {
      return <DocumentTextIcon className="h-5 w-5 text-blue-500" />;
    }
    if (fileType.includes('excel') || fileType.includes('sheet')) {
      return <DocumentTextIcon className="h-5 w-5 text-green-500" />;
    }
    if (fileType.includes('image')) {
      return <DocumentTextIcon className="h-5 w-5 text-purple-500" />;
    }
    return <DocumentTextIcon className="h-5 w-5 text-gray-500" />;
  };

  // Columns cho table
  const columns = [
    {
      key: 'title',
      label: 'Tài liệu',
      render: (document: Document) => (
        <div className="flex items-center space-x-3">
          {renderFileIcon(document.current_version.file_type)}
          <div>
            <div className="font-medium text-gray-900">{document.title}</div>
            <div className="text-sm text-gray-500">
              v{document.current_version.version_number} • {formatFileSize(document.current_version.file_size)}
            </div>
          </div>
        </div>
      )
    },
    {
      key: 'project',
      label: 'Dự án',
      render: (document: Document) => (
        <div className="text-sm">
          <div className="font-medium text-gray-900">{document.project.name}</div>
          {document.linked_entity_name && (
            <div className="text-gray-500">Liên kết: {document.linked_entity_name}</div>
          )}
        </div>
      )
    },
    {
      key: 'type',
      label: 'Loại',
      render: (document: Document) => renderTypeBadge(document.linked_entity_type)
    },
    {
      key: 'versions',
      label: 'Phiên bản',
      render: (document: Document) => (
        <div className="text-center">
          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
            {document.total_versions}
          </span>
        </div>
      )
    },
    {
      key: 'updated_by',
      label: 'Cập nhật',
      render: (document: Document) => (
        <div className="text-sm">
          <div className="font-medium text-gray-900">{document.current_version.created_by.name}</div>
          <div className="text-gray-500">{formatDate(document.current_version.created_at)}</div>
        </div>
      )
    },
    {
      key: 'actions',
      label: 'Thao tác',
      render: (document: Document) => (
        <div className="flex items-center space-x-2">
          <Button
            onClick={() => navigate(`/documents/${document.id}`)}
            variant="outline"
            size="sm"
          >
            <EyeIcon className="h-4 w-4" />
          </Button>
          <Button
            onClick={() => navigate(`/documents/${document.id}/edit`)}
            variant="outline"
            size="sm"
          >
            <PencilIcon className="h-4 w-4" />
          </Button>
          <Button
            onClick={() => handleDelete(document.id)}
            variant="outline"
            size="sm"
            className="text-red-600 hover:text-red-700"
          >
            <TrashIcon className="h-4 w-4" />
          </Button>
        </div>
      )
    }
  ];

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Quản lý Tài liệu</h1>
            <p className="text-gray-600 mt-1">
              Quản lý và theo dõi phiên bản tài liệu dự án
            </p>
          </div>
          <div className="flex space-x-3">
            <Button
              onClick={() => navigate('/documents/upload')}
              variant="outline"
            >
              <ArrowUpTrayIcon className="h-4 w-4 mr-2" />
              Tải lên
            </Button>
            <Button
              onClick={() => navigate('/documents/create')}
              variant="primary"
            >
              <PlusIcon className="h-4 w-4 mr-2" />
              Tạo mới
            </Button>
          </div>
        </div>
      </div>

      {/* Filters */}
      <Card className="mb-6">
        <div className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="md:col-span-2">
              <Input
                placeholder="Tìm kiếm tài liệu..."
                value={searchTerm}
                onChange={(e) => handleSearch(e.target.value)}
                icon={MagnifyingGlassIcon}
              />
            </div>
            <Select
              value={selectedProject}
              onChange={(value) => handleFilter('project', value)}
              placeholder="Chọn dự án"
            >
              <option value="">Tất cả dự án</option>
              {projects.map(project => (
                <option key={project.id} value={project.id}>
                  {project.name}
                </option>
              ))}
            </Select>
            <Select
              value={selectedType}
              onChange={(value) => handleFilter('type', value)}
              placeholder="Loại tài liệu"
            >
              <option value="">Tất cả loại</option>
              <option value="task">Nhiệm vụ</option>
              <option value="diary">Nhật ký</option>
              <option value="cr">Change Request</option>
            </Select>
          </div>
        </div>
      </Card>

      {/* Documents Table */}
      <Card>
        <Table
          data={documents}
          columns={columns}
          loading={loading}
          emptyMessage="Không có tài liệu nào"
        />
        
        {totalPages > 1 && (
          <div className="px-6 py-4 border-t border-gray-200">
            <Pagination
              currentPage={currentPage}
              totalPages={totalPages}
              onPageChange={setCurrentPage}
            />
          </div>
        )}
      </Card>
    </div>
  );
};