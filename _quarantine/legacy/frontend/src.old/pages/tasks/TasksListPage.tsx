import React, { useState, useEffect, Suspense, useTransition } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useTasksStore } from '@/store/tasks';
import { useProjectsStore } from '@/store/projects';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Table } from '@/components/ui/Table';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card';
import { Loading } from '@/components/ui/Loading';
import { TasksKpiStrip } from '../../components/tasks/TasksKpiStrip';
import { AlertBar } from '../../components/shared/AlertBar';
import { ActivityFeed } from '../../components/shared/ActivityFeed';
import { VisibilitySection } from '../../components/perf/VisibilitySection';
import { useTasksKpis, useTasksAlerts, useTasksActivity, tasksKeys } from '../../entities/app/tasks/hooks';
import { useI18n } from '../../app/i18n-context';
import { useQueryClient } from '@tanstack/react-query';
import { 
  Plus, 
  Search, 
  Filter, 
  Calendar,
  User,
  Clock,
  CheckCircle,
  AlertTriangle,
  MoreHorizontal,
  Eye,
  Edit,
  Trash2,
  Kanban
} from 'lucide-react';
import type { Task } from '@/lib/types';

/**
 * Trang danh sách nhiệm vụ với tính năng tìm kiếm, lọc và quản lý
 * Hỗ trợ view theo list và kanban board
 */
export const TasksListPage: React.FC = () => {
  const { t } = useI18n();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const [isPending, startTransition] = useTransition();
  
  const { 
    tasks, 
    isLoading, 
    pagination,
    fetchTasks,
    deleteTask 
  } = useTasksStore();
  
  const { projects } = useProjectsStore();
  
  // Fetch Tasks KPIs, Alerts, and Activity
  const { data: kpisData, isLoading: kpisLoading, error: kpisError } = useTasksKpis();
  const { data: alertsData, isLoading: alertsLoading, error: alertsError } = useTasksAlerts();
  const { data: activityData, isLoading: activityLoading, error: activityError } = useTasksActivity(10);
  
  const [filters, setFilters] = useState({
    search: '',
    status: '',
    project_id: '',
    assigned_to: '',
    priority: '',
    sortBy: 'created_at',
    sortOrder: 'desc' as 'asc' | 'desc'
  });

  const [viewMode, setViewMode] = useState<'list' | 'board'>('list');
  
  // Handle refresh
  const handleRefresh = () => {
    startTransition(() => {
      Promise.resolve().then(() => {
        queryClient.invalidateQueries({ queryKey: tasksKeys.all });
      });
    });
  };

  // Load dữ liệu khi component mount
  useEffect(() => {
    fetchTasks(filters);
  }, [filters, fetchTasks]);

  // Xử lý tìm kiếm
  const handleSearch = (value: string) => {
    setFilters(prev => ({ ...prev, search: value }));
  };

  // Xử lý lọc
  const handleFilter = (field: string, value: string) => {
    setFilters(prev => ({ ...prev, [field]: value }));
  };

  // Xử lý sắp xếp
  const handleSort = (field: string) => {
    setFilters(prev => ({
      ...prev,
      sortBy: field,
      sortOrder: prev.sortBy === field && prev.sortOrder === 'asc' ? 'desc' : 'asc'
    }));
  };

  // Xử lý xóa nhiệm vụ
  const handleDelete = async (taskId: string) => {
    if (window.confirm('Bạn có chắc chắn muốn xóa nhiệm vụ này?')) {
      await deleteTask(taskId);
    }
  };

  // Tính toán thống kê
  const stats = React.useMemo(() => {
    const total = tasks.length;
    const pending = tasks.filter(t => t.status === 'pending').length;
    const inProgress = tasks.filter(t => t.status === 'in_progress').length;
    const completed = tasks.filter(t => t.status === 'completed').length;
    const overdue = tasks.filter(t => 
      t.status !== 'completed' && new Date(t.end_date) < new Date()
    ).length;

    return { total, pending, inProgress, completed, overdue };
  }, [tasks]);

  // Định nghĩa cột cho bảng
  const columns = [
    {
      key: 'name',
      title: 'Tên nhiệm vụ',
      sortable: true,
      render: (task: Task) => (
        <div>
          <Link 
            to={`/tasks/${task.id}`}
            className="font-medium text-blue-600 hover:text-blue-500"
          >
            {task.name}
          </Link>
          <p className="text-sm text-gray-600 mt-1">
            Dự án: {projects.find(p => p.id === task.project_id)?.name}
          </p>
        </div>
      )
    },
    {
      key: 'status',
      title: 'Trạng thái',
      sortable: true,
      render: (task: Task) => (
        <span className={`px-2 py-1 text-xs font-medium rounded-full ${
          task.status === 'completed' 
            ? 'bg-green-100 text-green-800'
            : task.status === 'in_progress'
            ? 'bg-blue-100 text-blue-800'
            : task.status === 'on_hold'
            ? 'bg-yellow-100 text-yellow-800'
            : 'bg-gray-100 text-gray-800'
        }`}>
          {task.status === 'completed' ? 'Hoàn thành' :
           task.status === 'in_progress' ? 'Đang thực hiện' :
           task.status === 'on_hold' ? 'Tạm dừng' : 'Chờ xử lý'}
        </span>
      )
    },
    {
      key: 'priority',
      title: 'Độ ưu tiên',
      sortable: true,
      render: (task: Task) => (
        <span className={`px-2 py-1 text-xs font-medium rounded-full ${
          task.priority === 'high' 
            ? 'bg-red-100 text-red-800'
            : task.priority === 'medium'
            ? 'bg-yellow-100 text-yellow-800'
            : 'bg-green-100 text-green-800'
        }`}>
          {task.priority === 'high' ? 'Cao' :
           task.priority === 'medium' ? 'Trung bình' : 'Thấp'}
        </span>
      )
    },
    {
      key: 'assigned_to',
      title: 'Người thực hiện',
      render: (task: Task) => (
        <div className="flex items-center">
          <User className="w-4 h-4 mr-2 text-gray-400" />
          <span className="text-sm">{task.assigned_to?.name || 'Chưa phân công'}</span>
        </div>
      )
    },
    {
      key: 'end_date',
      title: 'Hạn hoàn thành',
      sortable: true,
      render: (task: Task) => {
        const isOverdue = new Date(task.end_date) < new Date() && task.status !== 'completed';
        return (
          <div className={`flex items-center text-sm ${
            isOverdue ? 'text-red-600' : 'text-gray-600'
          }`}>
            <Calendar className="w-4 h-4 mr-1" />
            {new Date(task.end_date).toLocaleDateString('vi-VN')}
            {isOverdue && <AlertTriangle className="w-4 h-4 ml-1" />}
          </div>
        );
      }
    },
    {
      key: 'actions',
      title: '',
      render: (task: Task) => (
        <div className="flex items-center gap-2">
          <Button
            variant="ghost"
            size="sm"
            as={Link}
            to={`/tasks/${task.id}`}
          >
            <Eye className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            as={Link}
            to={`/tasks/${task.id}/edit`}
          >
            <Edit className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            onClick={() => handleDelete(task.id)}
            className="text-red-600 hover:text-red-700"
          >
            <Trash2 className="w-4 h-4" />
          </Button>
        </div>
      )
    }
  ];

  if (isLoading) {
    return <Loading.Skeleton />;
  }

  return (
    <div className="space-y-6" style={{ contain: 'layout style' }}>
      {/* Universal Page Frame Structure */}
      {/* 1. Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-[var(--color-text-primary)]">
            {t('tasks.title', { defaultValue: 'Tasks' })}
          </h1>
          <p className="text-[var(--color-text-muted)]">
            {t('tasks.description', { defaultValue: 'Manage your tasks and track progress' })}
          </p>
        </div>
        <div className="flex gap-3">
          <div className="flex bg-gray-100 rounded-lg p-1">
            <Button
              variant={viewMode === 'list' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => setViewMode('list')}
            >
              {t('tasks.listView', { defaultValue: 'List' })}
            </Button>
            <Button
              variant={viewMode === 'board' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => setViewMode('board')}
              as={Link}
              to="/app/tasks/board"
            >
              <Kanban className="w-4 h-4 mr-2" />
              {t('tasks.kanban', { defaultValue: 'Kanban' })}
            </Button>
          </div>
          <Button variant="outline" size="sm" onClick={handleRefresh} aria-label="Refresh tasks" disabled={isPending}>
            {t('common.refresh', { defaultValue: 'Refresh' })}
          </Button>
          <Button as={Link} to="/app/tasks/create">
            <Plus className="w-4 h-4 mr-2" />
            {t('tasks.create', { defaultValue: 'Create Task' })}
          </Button>
        </div>
      </div>

      {/* 2. KPI Strip */}
      <TasksKpiStrip
        metrics={kpisData}
        loading={kpisLoading}
        error={kpisError}
        onRefresh={handleRefresh}
        onViewAllTasks={() => {
          setFilters(prev => ({ ...prev, status: undefined }));
          navigate('/app/tasks');
        }}
        onViewPendingTasks={() => {
          setFilters(prev => ({ ...prev, status: 'backlog' }));
          navigate('/app/tasks?status=backlog');
        }}
        onViewInProgressTasks={() => {
          setFilters(prev => ({ ...prev, status: 'in_progress' }));
          navigate('/app/tasks?status=in_progress');
        }}
        onViewCompletedTasks={() => {
          setFilters(prev => ({ ...prev, status: 'done' }));
          navigate('/app/tasks?status=done');
        }}
        onViewOverdueTasks={() => {
          navigate('/app/tasks?status=overdue');
        }}
      />

      {/* 3. Alert Bar */}
      <VisibilitySection intrinsicHeight={220}>
        <AlertBar
          alerts={alertsData ? {
            ...alertsData,
            data: alertsData.data?.map(alert => ({
              id: alert.id,
              title: alert.title,
              message: alert.message,
              severity: alert.severity,
              status: alert.status,
              type: alert.type,
              source: alert.source,
              createdAt: alert.createdAt,
              readAt: alert.readAt,
              metadata: alert.metadata,
            })) || []
          } : null}
          loading={alertsLoading}
          error={alertsError}
          showDismissAll={true}
        />
      </VisibilitySection>

      {/* Filters */}
      <Card>
        <CardContent className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div className="md:col-span-2">
              <Input
                placeholder="Tìm kiếm nhiệm vụ..."
                value={filters.search}
                onChange={(e) => handleSearch(e.target.value)}
                leftIcon={<Search className="w-4 h-4" />}
              />
            </div>
            <Select
              placeholder="Dự án"
              value={filters.project_id}
              onChange={(value) => handleFilter('project_id', value)}
              options={[
                { value: '', label: 'Tất cả dự án' },
                ...projects.map(p => ({ value: p.id, label: p.name }))
              ]}
            />
            <Select
              placeholder="Trạng thái"
              value={filters.status}
              onChange={(value) => handleFilter('status', value)}
              options={[
                { value: '', label: 'Tất cả trạng thái' },
                { value: 'pending', label: 'Chờ xử lý' },
                { value: 'in_progress', label: 'Đang thực hiện' },
                { value: 'on_hold', label: 'Tạm dừng' },
                { value: 'completed', label: 'Hoàn thành' }
              ]}
            />
            <Select
              placeholder="Độ ưu tiên"
              value={filters.priority}
              onChange={(value) => handleFilter('priority', value)}
              options={[
                { value: '', label: 'Tất cả mức độ' },
                { value: 'high', label: 'Cao' },
                { value: 'medium', label: 'Trung bình' },
                { value: 'low', label: 'Thấp' }
              ]}
            />
          </div>
        </CardContent>
      </Card>

      {/* 4. Main Content */}
      {/* Filters */}
      <Card>
        <CardContent className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
            <div className="md:col-span-2">
              <Input
                placeholder={t('tasks.searchPlaceholder', { defaultValue: 'Search tasks...' })}
                value={filters.search}
                onChange={(e) => handleSearch(e.target.value)}
                leftIcon={<Search className="w-4 h-4" />}
              />
            </div>
            <Select
              placeholder={t('tasks.project', { defaultValue: 'Project' })}
              value={filters.project_id}
              onChange={(value) => handleFilter('project_id', value)}
              options={[
                { value: '', label: t('tasks.allProjects', { defaultValue: 'All Projects' }) },
                ...projects.map(p => ({ value: p.id, label: p.name }))
              ]}
            />
            <Select
              placeholder={t('tasks.status', { defaultValue: 'Status' })}
              value={filters.status}
              onChange={(value) => handleFilter('status', value)}
              options={[
                { value: '', label: t('tasks.allStatuses', { defaultValue: 'All Statuses' }) },
                { value: 'backlog', label: t('tasks.backlog', { defaultValue: 'Backlog' }) },
                { value: 'in_progress', label: t('tasks.inProgress', { defaultValue: 'In Progress' }) },
                { value: 'blocked', label: t('tasks.blocked', { defaultValue: 'Blocked' }) },
                { value: 'done', label: t('tasks.done', { defaultValue: 'Done' }) }
              ]}
            />
            <Select
              placeholder={t('tasks.priority', { defaultValue: 'Priority' })}
              value={filters.priority}
              onChange={(value) => handleFilter('priority', value)}
              options={[
                { value: '', label: t('tasks.allPriorities', { defaultValue: 'All Priorities' }) },
                { value: 'urgent', label: t('tasks.urgent', { defaultValue: 'Urgent' }) },
                { value: 'high', label: t('tasks.high', { defaultValue: 'High' }) },
                { value: 'normal', label: t('tasks.normal', { defaultValue: 'Normal' }) },
                { value: 'low', label: t('tasks.low', { defaultValue: 'Low' }) }
              ]}
            />
          </div>
        </CardContent>
      </Card>

      {/* Tasks Table */}
      <Card>
        <CardContent className="p-0">
          <Table
            columns={columns}
            data={tasks}
            pagination={pagination}
            onSort={handleSort}
            sortBy={filters.sortBy}
            sortOrder={filters.sortOrder}
          />
        </CardContent>
      </Card>

      {/* 5. Activity Feed */}
      <VisibilitySection intrinsicHeight={300}>
        <Suspense fallback={<div>Loading activity...</div>}>
          <ActivityFeed
            activities={activityData ? {
              ...activityData,
              data: activityData.data?.map(activity => ({
                id: activity.id,
                type: activity.type,
                action: activity.action,
                description: activity.description,
                timestamp: activity.timestamp,
                user: activity.user,
              })) || []
            } : null}
            loading={activityLoading}
            error={activityError}
            limit={10}
            showHeader={true}
          />
        </Suspense>
      </VisibilitySection>
    </div>
  );
};