import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { 
  PlusIcon, 
  MagnifyingGlassIcon,
  FunnelIcon,
  EyeIcon,
  PencilIcon,
  CheckCircleIcon,
  XCircleIcon,
  ClockIcon,
  DocumentTextIcon
} from '@heroicons/react/24/outline';
import { Button } from '../../components/ui/Button';
import { Input } from '../../components/ui/Input';
import { Badge } from '../../components/ui/Badge';
import { Card } from '../../components/ui/Card';
import { Table } from '../../components/ui/Table';
import { Select } from '../../components/ui/Select';
import { useAuthStore } from '../../shared/auth/store';
import toast from 'react-hot-toast';
import { formatCurrency, formatDate } from '../../lib/utils/format';

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
  project_id: string;
  project_name: string;
  created_by: string;
  created_by_name: string;
  decided_by?: string;
  decided_by_name?: string;
  decided_at?: string;
  decision_note?: string;
  created_at: string;
  updated_at: string;
}

// Component chính
export const CRListPage: React.FC = () => {
  const { user } = useAuthStore();
  
  // State quản lý dữ liệu và UI
  const [changeRequests, setChangeRequests] = useState<ChangeRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [projectFilter, setProjectFilter] = useState<string>('all');
  const [sortBy, setSortBy] = useState<string>('created_at');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('desc');
  const [currentPage, setCurrentPage] = useState(1);
  const [itemsPerPage] = useState(10);

  // Fetch dữ liệu Change Requests
  useEffect(() => {
    fetchChangeRequests();
  }, [statusFilter, projectFilter, sortBy, sortOrder, currentPage]);

  const fetchChangeRequests = async () => {
    try {
      setLoading(true);
      
      // Mock API call - thay thế bằng API thực tế
      const mockData: ChangeRequest[] = [
        {
          id: '1',
          code: 'CR-2024-001',
          title: 'Thay đổi vật liệu sàn từ gỗ sang granite',
          description: 'Khách hàng yêu cầu thay đổi vật liệu sàn phòng khách từ gỗ công nghiệp sang granite tự nhiên',
          status: 'awaiting_approval',
          impact_days: 5,
          impact_cost: 50000000,
          impact_kpi: { quality: '+10%', cost: '+15%' },
          project_id: '1',
          project_name: 'Dự án Villa Thảo Điền',
          created_by: '1',
          created_by_name: 'Nguyễn Văn A',
          created_at: '2024-01-15T08:00:00Z',
          updated_at: '2024-01-15T08:00:00Z'
        },
        {
          id: '2',
          code: 'CR-2024-002',
          title: 'Bổ sung hệ thống điều hòa trung tâm',
          description: 'Thêm hệ thống điều hòa trung tâm cho toàn bộ tòa nhà thay vì điều hòa riêng lẻ',
          status: 'approved',
          impact_days: 10,
          impact_cost: 120000000,
          impact_kpi: { comfort: '+20%', energy: '-15%' },
          project_id: '2',
          project_name: 'Dự án Chung cư Landmark',
          created_by: '2',
          created_by_name: 'Trần Thị B',
          decided_by: '1',
          decided_by_name: 'Lê Văn C',
          decided_at: '2024-01-10T14:30:00Z',
          decision_note: 'Phê duyệt với điều kiện đảm bảo tiến độ tổng thể',
          created_at: '2024-01-08T09:15:00Z',
          updated_at: '2024-01-10T14:30:00Z'
        },
        {
          id: '3',
          code: 'CR-2024-003',
          title: 'Thay đổi thiết kế mặt tiền',
          description: 'Điều chỉnh thiết kế mặt tiền theo yêu cầu của ban quản lý khu đô thị',
          status: 'rejected',
          impact_days: 15,
          impact_cost: 80000000,
          impact_kpi: { aesthetic: '+25%', cost: '+20%' },
          project_id: '1',
          project_name: 'Dự án Villa Thảo Điền',
          created_by: '3',
          created_by_name: 'Phạm Văn D',
          decided_by: '1',
          decided_by_name: 'Lê Văn C',
          decided_at: '2024-01-12T16:45:00Z',
          decision_note: 'Từ chối do ảnh hưởng quá lớn đến ngân sách và tiến độ',
          created_at: '2024-01-05T11:20:00Z',
          updated_at: '2024-01-12T16:45:00Z'
        }
      ];
      
      setChangeRequests(mockData);
    } catch (error) {
      console.error('Error fetching change requests:', error);
      toast.error('Không thể tải danh sách yêu cầu thay đổi');
    } finally {
      setLoading(false);
    }
  };

  // Lọc và sắp xếp dữ liệu
  const filteredAndSortedCRs = React.useMemo(() => {
    let filtered = changeRequests.filter(cr => {
      const matchesSearch = cr.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
                           cr.code.toLowerCase().includes(searchTerm.toLowerCase()) ||
                           cr.description.toLowerCase().includes(searchTerm.toLowerCase());
      const matchesStatus = statusFilter === 'all' || cr.status === statusFilter;
      const matchesProject = projectFilter === 'all' || cr.project_id === projectFilter;
      
      return matchesSearch && matchesStatus && matchesProject;
    });

    // Sắp xếp
    filtered.sort((a, b) => {
      let aValue = a[sortBy as keyof ChangeRequest];
      let bValue = b[sortBy as keyof ChangeRequest];
      
      if (typeof aValue === 'string') {
        aValue = aValue.toLowerCase();
        bValue = (bValue as string).toLowerCase();
      }
      
      if (sortOrder === 'asc') {
        return aValue < bValue ? -1 : aValue > bValue ? 1 : 0;
      } else {
        return aValue > bValue ? -1 : aValue < bValue ? 1 : 0;
      }
    });

    return filtered;
  }, [changeRequests, searchTerm, statusFilter, projectFilter, sortBy, sortOrder]);

  // Phân trang
  const totalPages = Math.ceil(filteredAndSortedCRs.length / itemsPerPage);
  const paginatedCRs = filteredAndSortedCRs.slice(
    (currentPage - 1) * itemsPerPage,
    currentPage * itemsPerPage
  );

  // Hàm lấy màu badge theo status
  const getStatusBadge = (status: ChangeRequest['status']) => {
    const statusConfig = {
      draft: { label: 'Nháp', variant: 'secondary' as const, icon: DocumentTextIcon },
      awaiting_approval: { label: 'Chờ phê duyệt', variant: 'warning' as const, icon: ClockIcon },
      approved: { label: 'Đã phê duyệt', variant: 'success' as const, icon: CheckCircleIcon },
      rejected: { label: 'Từ chối', variant: 'destructive' as const, icon: XCircleIcon }
    };
    
    const config = statusConfig[status];
    const IconComponent = config.icon;
    
    return (
      <Badge variant={config.variant} className="flex items-center gap-1">
        <IconComponent className="h-3 w-3" />
        {config.label}
      </Badge>
    );
  };

  // Hàm lấy danh sách projects unique để filter
  const uniqueProjects = React.useMemo(() => {
    const projects = changeRequests.map(cr => ({ id: cr.project_id, name: cr.project_name }));
    return projects.filter((project, index, self) => 
      index === self.findIndex(p => p.id === project.id)
    );
  }, [changeRequests]);

  // Thống kê tổng quan
  const stats = React.useMemo(() => {
    const total = changeRequests.length;
    const draft = changeRequests.filter(cr => cr.status === 'draft').length;
    const pending = changeRequests.filter(cr => cr.status === 'awaiting_approval').length;
    const approved = changeRequests.filter(cr => cr.status === 'approved').length;
    const rejected = changeRequests.filter(cr => cr.status === 'rejected').length;
    
    return { total, draft, pending, approved, rejected };
  }, [changeRequests]);

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Yêu cầu thay đổi</h1>
          <p className="text-gray-600 mt-1">Quản lý các yêu cầu thay đổi trong dự án</p>
        </div>
        <Link to="/change-requests/create">
          <Button className="flex items-center gap-2">
            <PlusIcon className="h-4 w-4" />
            Tạo yêu cầu mới
          </Button>
        </Link>
      </div>

      {/* Thống kê tổng quan */}
      <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
        <Card className="p-4">
          <div className="text-center">
            <div className="text-2xl font-bold text-gray-900">{stats.total}</div>
            <div className="text-sm text-gray-600">Tổng số</div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="text-center">
            <div className="text-2xl font-bold text-gray-500">{stats.draft}</div>
            <div className="text-sm text-gray-600">Nháp</div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="text-center">
            <div className="text-2xl font-bold text-yellow-600">{stats.pending}</div>
            <div className="text-sm text-gray-600">Chờ duyệt</div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="text-center">
            <div className="text-2xl font-bold text-green-600">{stats.approved}</div>
            <div className="text-sm text-gray-600">Đã duyệt</div>
          </div>
        </Card>
        <Card className="p-4">
          <div className="text-center">
            <div className="text-2xl font-bold text-red-600">{stats.rejected}</div>
            <div className="text-sm text-gray-600">Từ chối</div>
          </div>
        </Card>
      </div>

      {/* Filters và Search */}
      <Card className="p-4">
        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div className="relative">
            <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
            <Input
              placeholder="Tìm kiếm theo mã, tiêu đề..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="pl-10"
            />
          </div>
          
          <Select value={statusFilter} onValueChange={setStatusFilter}>
            <option value="all">Tất cả trạng thái</option>
            <option value="draft">Nháp</option>
            <option value="awaiting_approval">Chờ phê duyệt</option>
            <option value="approved">Đã phê duyệt</option>
            <option value="rejected">Từ chối</option>
          </Select>
          
          <Select value={projectFilter} onValueChange={setProjectFilter}>
            <option value="all">Tất cả dự án</option>
            {uniqueProjects.map(project => (
              <option key={project.id} value={project.id}>{project.name}</option>
            ))}
          </Select>
          
          <Select value={`${sortBy}-${sortOrder}`} onValueChange={(value) => {
            const [field, order] = value.split('-');
            setSortBy(field);
            setSortOrder(order as 'asc' | 'desc');
          }}>
            <option value="created_at-desc">Mới nhất</option>
            <option value="created_at-asc">Cũ nhất</option>
            <option value="title-asc">Tiêu đề A-Z</option>
            <option value="title-desc">Tiêu đề Z-A</option>
            <option value="impact_cost-desc">Chi phí cao nhất</option>
            <option value="impact_cost-asc">Chi phí thấp nhất</option>
          </Select>
        </div>
      </Card>

      {/* Bảng danh sách */}
      <Card>
        <Table>
          <thead>
            <tr>
              <th>Mã CR</th>
              <th>Tiêu đề</th>
              <th>Dự án</th>
              <th>Trạng thái</th>
              <th>Tác động chi phí</th>
              <th>Tác động thời gian</th>
              <th>Người tạo</th>
              <th>Ngày tạo</th>
              <th>Thao tác</th>
            </tr>
          </thead>
          <tbody>
            {paginatedCRs.map((cr) => (
              <tr key={cr.id}>
                <td className="font-mono text-sm">{cr.code}</td>
                <td>
                  <div className="max-w-xs">
                    <div className="font-medium text-gray-900 truncate">{cr.title}</div>
                    <div className="text-sm text-gray-500 truncate">{cr.description}</div>
                  </div>
                </td>
                <td className="text-sm text-gray-600">{cr.project_name}</td>
                <td>{getStatusBadge(cr.status)}</td>
                <td className="text-right">
                  <span className={`font-medium ${
                    cr.impact_cost > 0 ? 'text-red-600' : cr.impact_cost < 0 ? 'text-green-600' : 'text-gray-600'
                  }`}>
                    {cr.impact_cost > 0 ? '+' : ''}{formatCurrency(cr.impact_cost)}
                  </span>
                </td>
                <td className="text-center">
                  <span className={`font-medium ${
                    cr.impact_days > 0 ? 'text-red-600' : cr.impact_days < 0 ? 'text-green-600' : 'text-gray-600'
                  }`}>
                    {cr.impact_days > 0 ? '+' : ''}{cr.impact_days} ngày
                  </span>
                </td>
                <td className="text-sm text-gray-600">{cr.created_by_name}</td>
                <td className="text-sm text-gray-600">{formatDate(cr.created_at)}</td>
                <td>
                  <div className="flex items-center gap-2">
                    <Link to={`/change-requests/${cr.id}`}>
                      <Button variant="ghost" size="sm">
                        <EyeIcon className="h-4 w-4" />
                      </Button>
                    </Link>
                    {(cr.status === 'draft' && cr.created_by === user?.id) && (
                      <Link to={`/change-requests/${cr.id}/edit`}>
                        <Button variant="ghost" size="sm">
                          <PencilIcon className="h-4 w-4" />
                        </Button>
                      </Link>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </Table>
        
        {paginatedCRs.length === 0 && (
          <div className="text-center py-8 text-gray-500">
            Không tìm thấy yêu cầu thay đổi nào
          </div>
        )}
      </Card>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="flex justify-center items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
            disabled={currentPage === 1}
          >
            Trước
          </Button>
          
          <span className="text-sm text-gray-600">
            Trang {currentPage} / {totalPages}
          </span>
          
          <Button
            variant="outline"
            size="sm"
            onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
            disabled={currentPage === totalPages}
          >
            Sau
          </Button>
        </div>
      )}
    </div>
  );
};