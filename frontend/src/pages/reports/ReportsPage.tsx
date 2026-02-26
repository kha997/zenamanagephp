import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import {
  ChartBarIcon,
  ChartPieIcon,
  CalendarIcon,
  CurrencyDollarIcon,
  ClockIcon,
  UserGroupIcon,
  DocumentChartBarIcon,
  ArrowDownTrayIcon,
  FunnelIcon,
  ArrowPathIcon
} from '@/lib/heroicons';
import { Button } from '../../components/ui/Button';
import { Card } from '../../components/ui/Card';
import { Select } from '../../components/ui/Select';
import { DatePicker } from '../../components/ui/DatePicker';
import { Badge } from '../../components/ui/Badge';
import { useAuthStore } from '../../store/authStore';
import { useNotificationStore } from '../../store/notificationStore';
import { formatCurrency, formatDate, formatPercent } from '../../lib/utils';

// Interfaces cho dữ liệu báo cáo
interface ProjectSummary {
  total_projects: number;
  active_projects: number;
  completed_projects: number;
  overdue_projects: number;
  total_budget: number;
  spent_budget: number;
  avg_progress: number;
}

interface TaskMetrics {
  total_tasks: number;
  completed_tasks: number;
  in_progress_tasks: number;
  overdue_tasks: number;
  avg_completion_time: number;
}

interface BudgetTrend {
  month: string;
  planned: number;
  actual: number;
  variance: number;
}

interface ProjectProgress {
  project_id: string;
  project_name: string;
  progress: number;
  planned_progress: number;
  status: string;
  budget_utilization: number;
}

interface TeamPerformance {
  user_id: string;
  user_name: string;
  completed_tasks: number;
  avg_task_duration: number;
  efficiency_score: number;
  active_projects: number;
}

interface ReportFilters {
  project_id?: string;
  date_from?: string;
  date_to?: string;
  status?: string;
  team_member?: string;
}

// Component chính
export const ReportsPage: React.FC = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const { user } = useAuthStore();
  const { addNotification } = useNotificationStore();
  
  // States cho dữ liệu
  const [loading, setLoading] = useState(true);
  const [projectSummary, setProjectSummary] = useState<ProjectSummary | null>(null);
  const [taskMetrics, setTaskMetrics] = useState<TaskMetrics | null>(null);
  const [budgetTrends, setBudgetTrends] = useState<BudgetTrend[]>([]);
  const [projectProgress, setProjectProgress] = useState<ProjectProgress[]>([]);
  const [teamPerformance, setTeamPerformance] = useState<TeamPerformance[]>([]);
  
  // States cho filters
  const [filters, setFilters] = useState<ReportFilters>({
    project_id: searchParams.get('project_id') || undefined,
    date_from: searchParams.get('date_from') || undefined,
    date_to: searchParams.get('date_to') || undefined,
    status: searchParams.get('status') || undefined,
    team_member: searchParams.get('team_member') || undefined
  });
  
  // States cho UI
  const [activeTab, setActiveTab] = useState('overview');
  const [refreshing, setRefreshing] = useState(false);
  const [projects, setProjects] = useState<Array<{id: string, name: string}>>([]);
  const [teamMembers, setTeamMembers] = useState<Array<{id: string, name: string}>>([]);

  // Fetch dữ liệu báo cáo
  const fetchReportsData = async () => {
    try {
      setLoading(true);
      
      const queryParams = new URLSearchParams();
      Object.entries(filters).forEach(([key, value]) => {
        if (value) queryParams.append(key, value);
      });
      
      const [summaryRes, metricsRes, trendsRes, progressRes, performanceRes] = await Promise.all([
        fetch(`/api/v1/reports/project-summary?${queryParams}`, {
          headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
        }),
        fetch(`/api/v1/reports/task-metrics?${queryParams}`, {
          headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
        }),
        fetch(`/api/v1/reports/budget-trends?${queryParams}`, {
          headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
        }),
        fetch(`/api/v1/reports/project-progress?${queryParams}`, {
          headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
        }),
        fetch(`/api/v1/reports/team-performance?${queryParams}`, {
          headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
        })
      ]);
      
      if (summaryRes.ok) {
        const data = await summaryRes.json();
        setProjectSummary(data.data);
      }
      
      if (metricsRes.ok) {
        const data = await metricsRes.json();
        setTaskMetrics(data.data);
      }
      
      if (trendsRes.ok) {
        const data = await trendsRes.json();
        setBudgetTrends(data.data);
      }
      
      if (progressRes.ok) {
        const data = await progressRes.json();
        setProjectProgress(data.data);
      }
      
      if (performanceRes.ok) {
        const data = await performanceRes.json();
        setTeamPerformance(data.data);
      }
      
    } catch (error) {
      console.error('Error fetching reports data:', error);
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể tải dữ liệu báo cáo'
      });
    } finally {
      setLoading(false);
    }
  };

  // Fetch danh sách projects và team members cho filters
  const fetchFilterOptions = async () => {
    try {
      const [projectsRes, membersRes] = await Promise.all([
        fetch('/api/v1/projects?per_page=100', {
          headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
        }),
        fetch('/api/v1/users?per_page=100', {
          headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
        })
      ]);
      
      if (projectsRes.ok) {
        const data = await projectsRes.json();
        setProjects(data.data.map((p: any) => ({ id: p.id, name: p.name })));
      }
      
      if (membersRes.ok) {
        const data = await membersRes.json();
        setTeamMembers(data.data.map((u: any) => ({ id: u.id, name: u.name })));
      }
    } catch (error) {
      console.error('Error fetching filter options:', error);
    }
  };

  // Xử lý thay đổi filters
  const handleFilterChange = (key: keyof ReportFilters, value: string | undefined) => {
    const newFilters = { ...filters, [key]: value };
    setFilters(newFilters);
    
    // Cập nhật URL params
    const newSearchParams = new URLSearchParams();
    Object.entries(newFilters).forEach(([k, v]) => {
      if (v) newSearchParams.set(k, v);
    });
    setSearchParams(newSearchParams);
  };

  // Xử lý refresh dữ liệu
  const handleRefresh = async () => {
    setRefreshing(true);
    await fetchReportsData();
    setRefreshing(false);
    
    addNotification({
      type: 'success',
      title: 'Thành công',
      message: 'Đã cập nhật dữ liệu báo cáo'
    });
  };

  // Xử lý export báo cáo
  const handleExport = async (format: 'pdf' | 'excel') => {
    try {
      const queryParams = new URLSearchParams();
      Object.entries(filters).forEach(([key, value]) => {
        if (value) queryParams.append(key, value);
      });
      queryParams.append('format', format);
      
      const response = await fetch(`/api/v1/reports/export?${queryParams}`, {
        headers: { 'Authorization': `Bearer ${localStorage.getItem('token')}` }
      });
      
      if (!response.ok) {
        throw new Error('Không thể export báo cáo');
      }
      
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `report_${new Date().toISOString().split('T')[0]}.${format === 'pdf' ? 'pdf' : 'xlsx'}`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      
      addNotification({
        type: 'success',
        title: 'Thành công',
        message: `Đã export báo cáo ${format.toUpperCase()}`
      });
    } catch (error) {
      console.error('Error exporting report:', error);
      addNotification({
        type: 'error',
        title: 'Lỗi',
        message: 'Không thể export báo cáo'
      });
    }
  };

  // Effects
  useEffect(() => {
    fetchFilterOptions();
  }, []);

  useEffect(() => {
    fetchReportsData();
  }, [filters]);

  // Render overview cards
  const renderOverviewCards = () => {
    if (!projectSummary || !taskMetrics) return null;
    
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        {/* Tổng quan dự án */}
        <Card>
          <div className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-500">Tổng dự án</p>
                <p className="text-2xl font-bold text-gray-900">{projectSummary.total_projects}</p>
                <p className="text-sm text-green-600">
                  {projectSummary.active_projects} đang thực hiện
                </p>
              </div>
              <div className="p-3 bg-blue-100 rounded-lg">
                <DocumentChartBarIcon className="h-6 w-6 text-blue-600" />
              </div>
            </div>
          </div>
        </Card>
        
        {/* Tiến độ trung bình */}
        <Card>
          <div className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-500">Tiến độ TB</p>
                <p className="text-2xl font-bold text-gray-900">
                  {formatPercent(projectSummary.avg_progress)}
                </p>
                <p className="text-sm text-gray-600">
                  {projectSummary.completed_projects} hoàn thành
                </p>
              </div>
              <div className="p-3 bg-green-100 rounded-lg">
                <ChartBarIcon className="h-6 w-6 text-green-600" />
              </div>
            </div>
          </div>
        </Card>
        
        {/* Ngân sách */}
        <Card>
          <div className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-500">Ngân sách</p>
                <p className="text-2xl font-bold text-gray-900">
                  {formatCurrency(projectSummary.spent_budget)}
                </p>
                <p className="text-sm text-gray-600">
                  / {formatCurrency(projectSummary.total_budget)}
                </p>
              </div>
              <div className="p-3 bg-yellow-100 rounded-lg">
                <CurrencyDollarIcon className="h-6 w-6 text-yellow-600" />
              </div>
            </div>
          </div>
        </Card>
        
        {/* Nhiệm vụ */}
        <Card>
          <div className="p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-500">Nhiệm vụ</p>
                <p className="text-2xl font-bold text-gray-900">{taskMetrics.total_tasks}</p>
                <p className="text-sm text-red-600">
                  {taskMetrics.overdue_tasks} quá hạn
                </p>
              </div>
              <div className="p-3 bg-purple-100 rounded-lg">
                <ClockIcon className="h-6 w-6 text-purple-600" />
              </div>
            </div>
          </div>
        </Card>
      </div>
    );
  };

  // Render budget trend chart (simplified)
  const renderBudgetTrendChart = () => {
    if (budgetTrends.length === 0) return null;
    
    return (
      <Card>
        <div className="p-6">
          <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
            <ChartBarIcon className="h-5 w-5 mr-2" />
            Xu hướng ngân sách
          </h3>
          <div className="space-y-4">
            {budgetTrends.slice(0, 6).map((trend, index) => {
              const variance = ((trend.actual - trend.planned) / trend.planned) * 100;
              return (
                <div key={index} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                  <div>
                    <p className="font-medium text-gray-900">{trend.month}</p>
                    <p className="text-sm text-gray-500">
                      Kế hoạch: {formatCurrency(trend.planned)}
                    </p>
                  </div>
                  <div className="text-right">
                    <p className="font-medium text-gray-900">
                      {formatCurrency(trend.actual)}
                    </p>
                    <Badge 
                      color={variance > 0 ? 'red' : variance < -5 ? 'green' : 'yellow'}
                      size="sm"
                    >
                      {variance > 0 ? '+' : ''}{variance.toFixed(1)}%
                    </Badge>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      </Card>
    );
  };

  // Render project progress table
  const renderProjectProgress = () => {
    if (projectProgress.length === 0) return null;
    
    return (
      <Card>
        <div className="p-6">
          <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
            <ChartPieIcon className="h-5 w-5 mr-2" />
            Tiến độ dự án
          </h3>
          <div className="overflow-x-auto">
            <table className="min-w-full divide-y divide-gray-200">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Dự án
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Tiến độ
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Trạng thái
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Ngân sách
                  </th>
                </tr>
              </thead>
              <tbody className="bg-white divide-y divide-gray-200">
                {projectProgress.slice(0, 10).map((project) => (
                  <tr key={project.project_id}>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="text-sm font-medium text-gray-900">
                        {project.project_name}
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <div className="flex items-center">
                        <div className="w-16 bg-gray-200 rounded-full h-2 mr-3">
                          <div 
                            className="bg-blue-600 h-2 rounded-full" 
                            style={{ width: `${project.progress}%` }}
                          ></div>
                        </div>
                        <span className="text-sm text-gray-900">
                          {formatPercent(project.progress)}
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <Badge 
                        color={
                          project.status === 'completed' ? 'green' :
                          project.status === 'in_progress' ? 'blue' :
                          project.status === 'on_hold' ? 'yellow' : 'red'
                        }
                      >
                        {project.status === 'completed' ? 'Hoàn thành' :
                         project.status === 'in_progress' ? 'Đang thực hiện' :
                         project.status === 'on_hold' ? 'Tạm dừng' : 'Quá hạn'}
                      </Badge>
                    </td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                      {formatPercent(project.budget_utilization)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </Card>
    );
  };

  // Render team performance
  const renderTeamPerformance = () => {
    if (teamPerformance.length === 0) return null;
    
    return (
      <Card>
        <div className="p-6">
          <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
            <UserGroupIcon className="h-5 w-5 mr-2" />
            Hiệu suất nhóm
          </h3>
          <div className="space-y-4">
            {teamPerformance.slice(0, 8).map((member) => (
              <div key={member.user_id} className="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div>
                  <p className="font-medium text-gray-900">{member.user_name}</p>
                  <p className="text-sm text-gray-500">
                    {member.completed_tasks} nhiệm vụ • {member.active_projects} dự án
                  </p>
                </div>
                <div className="text-right">
                  <p className="font-medium text-gray-900">
                    {member.efficiency_score.toFixed(1)}/10
                  </p>
                  <p className="text-sm text-gray-500">
                    {member.avg_task_duration.toFixed(1)} ngày/task
                  </p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </Card>
    );
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Báo cáo & Phân tích</h1>
            <p className="text-sm text-gray-500 mt-1">
              Tổng quan hiệu suất và tiến độ dự án
            </p>
          </div>
          <div className="flex items-center space-x-3">
            <Button
              onClick={handleRefresh}
              variant="outline"
              disabled={refreshing}
            >
              <ArrowPathIcon className={`h-4 w-4 mr-2 ${refreshing ? 'animate-spin' : ''}`} />
              Làm mới
            </Button>
            <Button
              onClick={() => handleExport('excel')}
              variant="outline"
            >
              <ArrowDownTrayIcon className="h-4 w-4 mr-2" />
              Excel
            </Button>
            <Button
              onClick={() => handleExport('pdf')}
              variant="primary"
            >
              <ArrowDownTrayIcon className="h-4 w-4 mr-2" />
              PDF
            </Button>
          </div>
        </div>
      </div>

      {/* Filters */}
      <Card className="mb-8">
        <div className="p-6">
          <div className="flex items-center space-x-4 mb-4">
            <FunnelIcon className="h-5 w-5 text-gray-400" />
            <h3 className="text-lg font-medium text-gray-900">Bộ lọc</h3>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <Select
              value={filters.project_id || ''}
              onChange={(value) => handleFilterChange('project_id', value || undefined)}
              placeholder="Chọn dự án"
            >
              <option value="">Tất cả dự án</option>
              {projects.map(project => (
                <option key={project.id} value={project.id}>
                  {project.name}
                </option>
              ))}
            </Select>
            
            <DatePicker
              value={filters.date_from || ''}
              onChange={(value) => handleFilterChange('date_from', value || undefined)}
              placeholder="Từ ngày"
            />
            
            <DatePicker
              value={filters.date_to || ''}
              onChange={(value) => handleFilterChange('date_to', value || undefined)}
              placeholder="Đến ngày"
            />
            
            <Select
              value={filters.status || ''}
              onChange={(value) => handleFilterChange('status', value || undefined)}
              placeholder="Trạng thái"
            >
              <option value="">Tất cả trạng thái</option>
              <option value="planning">Lập kế hoạch</option>
              <option value="in_progress">Đang thực hiện</option>
              <option value="on_hold">Tạm dừng</option>
              <option value="completed">Hoàn thành</option>
              <option value="cancelled">Đã hủy</option>
            </Select>
            
            <Select
              value={filters.team_member || ''}
              onChange={(value) => handleFilterChange('team_member', value || undefined)}
              placeholder="Thành viên"
            >
              <option value="">Tất cả thành viên</option>
              {teamMembers.map(member => (
                <option key={member.id} value={member.id}>
                  {member.name}
                </option>
              ))}
            </Select>
          </div>
        </div>
      </Card>

      {/* Tabs */}
      <div className="mb-8">
        <div className="border-b border-gray-200">
          <nav className="-mb-px flex space-x-8">
            {[
              { id: 'overview', name: 'Tổng quan', icon: ChartBarIcon },
              { id: 'projects', name: 'Dự án', icon: DocumentChartBarIcon },
              { id: 'budget', name: 'Ngân sách', icon: CurrencyDollarIcon },
              { id: 'team', name: 'Nhóm', icon: UserGroupIcon }
            ].map((tab) => {
              const Icon = tab.icon;
              return (
                <button
                  key={tab.id}
                  onClick={() => setActiveTab(tab.id)}
                  className={`flex items-center py-2 px-1 border-b-2 font-medium text-sm ${
                    activeTab === tab.id
                      ? 'border-blue-500 text-blue-600'
                      : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                  }`}
                >
                  <Icon className="h-4 w-4 mr-2" />
                  {tab.name}
                </button>
              );
            })}
          </nav>
        </div>
      </div>

      {/* Content */}
      <div className="space-y-8">
        {activeTab === 'overview' && (
          <>
            {renderOverviewCards()}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
              {renderBudgetTrendChart()}
              {renderTeamPerformance()}
            </div>
          </>
        )}
        
        {activeTab === 'projects' && (
          <div className="space-y-8">
            {renderProjectProgress()}
          </div>
        )}
        
        {activeTab === 'budget' && (
          <div className="space-y-8">
            {renderBudgetTrendChart()}
          </div>
        )}
        
        {activeTab === 'team' && (
          <div className="space-y-8">
            {renderTeamPerformance()}
          </div>
        )}
      </div>
    </div>
  );
};