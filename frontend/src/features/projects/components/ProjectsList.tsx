import React, { useState, useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { useProjectStore } from '@/store/projects';
import { Button } from '@/components/ui/Button';
import { ProjectCard } from './ProjectCard';
import { ProjectsFilterBar, ProjectsFilters } from './ProjectsFilterBar';
import { EmptyState } from '@/components/ui/EmptyState';
import { LoadingSpinner } from '@/components/ui/loading-spinner';
import { ErrorMessage } from '@/components/ui/ErrorMessage';
import { ResponsiveGrid } from '@/components/ui/ResponsiveGrid';
import { Plus, Grid, List, Download, Upload } from 'lucide-react';
import { Project } from '../types';
import { cn } from '@/lib/utils';

type ViewMode = 'grid' | 'list';
type SortField = 'name' | 'progress' | 'start_date' | 'end_date' | 'status';
type SortOrder = 'asc' | 'desc';

export const ProjectsList: React.FC = () => {
  const { projects, isLoading, error, fetchProjects, deleteProject } = useProjectStore();
  
  // State management
  const [viewMode, setViewMode] = useState<ViewMode>('grid');
  const [sortField, setSortField] = useState<SortField>('name');
  const [sortOrder, setSortOrder] = useState<SortOrder>('asc');
  const [selectedProjects, setSelectedProjects] = useState<number[]>([]);
  const [filters, setFilters] = useState<ProjectsFilters>({
    search: '',
    status: [],
    dateRange: {},
    progressRange: {},
    costRange: {}
  });

  // Load projects on mount
  useEffect(() => {
    fetchProjects();
  }, [fetchProjects]);

  // Filter and sort projects
  const filteredAndSortedProjects = useMemo(() => {
    let filtered = projects.filter(project => {
      // Search filter
      if (filters.search) {
        const searchLower = filters.search.toLowerCase();
        const matchesSearch = 
          project.name.toLowerCase().includes(searchLower) ||
          project.description?.toLowerCase().includes(searchLower);
        if (!matchesSearch) return false;
      }

      // Status filter
      if (filters.status.length > 0) {
        if (!filters.status.includes(project.status)) return false;
      }

      // Date range filter
      if (filters.dateRange.start || filters.dateRange.end) {
        const projectStart = new Date(project.start_date);
        const projectEnd = new Date(project.end_date);
        
        if (filters.dateRange.start) {
          const filterStart = new Date(filters.dateRange.start);
          if (projectStart < filterStart) return false;
        }
        
        if (filters.dateRange.end) {
          const filterEnd = new Date(filters.dateRange.end);
          if (projectEnd > filterEnd) return false;
        }
      }

      // Progress range filter
      if (filters.progressRange.min !== undefined || filters.progressRange.max !== undefined) {
        const progress = project.progress;
        if (filters.progressRange.min !== undefined && progress < filters.progressRange.min) return false;
        if (filters.progressRange.max !== undefined && progress > filters.progressRange.max) return false;
      }

      // Cost range filter
      if (filters.costRange.min !== undefined || filters.costRange.max !== undefined) {
        const cost = project.actual_cost || 0;
        if (filters.costRange.min !== undefined && cost < filters.costRange.min) return false;
        if (filters.costRange.max !== undefined && cost > filters.costRange.max) return false;
      }

      return true;
    });

    // Sort projects
    filtered.sort((a, b) => {
      let aValue: any, bValue: any;
      
      switch (sortField) {
        case 'name':
          aValue = a.name.toLowerCase();
          bValue = b.name.toLowerCase();
          break;
        case 'progress':
          aValue = a.progress;
          bValue = b.progress;
          break;
        case 'start_date':
          aValue = new Date(a.start_date);
          bValue = new Date(b.start_date);
          break;
        case 'end_date':
          aValue = new Date(a.end_date);
          bValue = new Date(b.end_date);
          break;
        case 'status':
          aValue = a.status;
          bValue = b.status;
          break;
        default:
          return 0;
      }

      if (aValue < bValue) return sortOrder === 'asc' ? -1 : 1;
      if (aValue > bValue) return sortOrder === 'asc' ? 1 : -1;
      return 0;
    });

    return filtered;
  }, [projects, filters, sortField, sortOrder]);

  // Handle project actions
  const handleEditProject = (project: Project) => {
    // Navigate to edit page or open modal
    console.log('Edit project:', project.id);
  };

  const handleDeleteProject = async (project: Project) => {
    if (window.confirm(`Bạn có chắc chắn muốn xóa dự án "${project.name}"?`)) {
      try {
        await deleteProject(project.id);
      } catch (error) {
        console.error('Error deleting project:', error);
      }
    }
  };

  const handleBulkAction = (action: string) => {
    console.log('Bulk action:', action, selectedProjects);
  };

  const handleSort = (field: SortField) => {
    if (sortField === field) {
      setSortOrder(sortOrder === 'asc' ? 'desc' : 'asc');
    } else {
      setSortField(field);
      setSortOrder('asc');
    }
  };

  // Loading state
  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Dự án</h1>
            <p className="text-gray-600">Quản lý tất cả các dự án của bạn</p>
          </div>
        </div>
        <LoadingSpinner size="lg" className="mx-auto" />
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Dự án</h1>
            <p className="text-gray-600">Quản lý tất cả các dự án của bạn</p>
          </div>
        </div>
        <ErrorMessage 
          message={error}
          onRetry={() => fetchProjects()}
        />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Dự án</h1>
          <p className="text-gray-600">Quản lý tất cả các dự án của bạn</p>
        </div>
        
        <div className="flex gap-2">
          <Button variant="outline" size="sm">
            <Download className="w-4 h-4 mr-2" />
            Xuất Excel
          </Button>
          <Button variant="outline" size="sm">
            <Upload className="w-4 h-4 mr-2" />
            Nhập Excel
          </Button>
          <Button asChild>
            <Link to="/projects/new">
              <Plus className="w-4 h-4 mr-2" />
              Tạo dự án mới
            </Link>
          </Button>
        </div>
      </div>

      {/* Filters */}
      <ProjectsFilterBar
        filters={filters}
        onFiltersChange={setFilters}
        totalCount={projects.length}
        filteredCount={filteredAndSortedProjects.length}
      />

      {/* View Controls */}
      <div className="flex justify-between items-center">
        <div className="flex items-center gap-4">
          {/* Sort Controls */}
          <div className="flex items-center gap-2">
            <span className="text-sm text-gray-600">Sắp xếp theo:</span>
            <select
              value={`${sortField}-${sortOrder}`}
              onChange={(e) => {
                const [field, order] = e.target.value.split('-') as [SortField, SortOrder];
                setSortField(field);
                setSortOrder(order);
              }}
              className="text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="name-asc">Tên (A-Z)</option>
              <option value="name-desc">Tên (Z-A)</option>
              <option value="progress-desc">Tiến độ (Cao-Thấp)</option>
              <option value="progress-asc">Tiến độ (Thấp-Cao)</option>
              <option value="start_date-desc">Ngày bắt đầu (Mới-Cũ)</option>
              <option value="start_date-asc">Ngày bắt đầu (Cũ-Mới)</option>
              <option value="status-asc">Trạng thái</option>
            </select>
          </div>

          {/* Bulk Actions */}
          {selectedProjects.length > 0 && (
            <div className="flex items-center gap-2">
              <span className="text-sm text-gray-600">
                Đã chọn {selectedProjects.length} dự án
              </span>
              <Button
                variant="outline"
                size="sm"
                onClick={() => handleBulkAction('export')}
              >
                Xuất
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => handleBulkAction('archive')}
              >
                Lưu trữ
              </Button>
            </div>
          )}
        </div>

        {/* View Mode Toggle */}
        <div className="flex items-center border rounded-lg p-1">
          <button
            onClick={() => setViewMode('grid')}
            className={cn(
              'p-2 rounded transition-colors',
              viewMode === 'grid' ? 'bg-blue-100 text-blue-600' : 'text-gray-400 hover:text-gray-600'
            )}
          >
            <Grid className="w-4 h-4" />
          </button>
          <button
            onClick={() => setViewMode('list')}
            className={cn(
              'p-2 rounded transition-colors',
              viewMode === 'list' ? 'bg-blue-100 text-blue-600' : 'text-gray-400 hover:text-gray-600'
            )}
          >
            <List className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Projects Display */}
      {filteredAndSortedProjects.length === 0 ? (
        <EmptyState
          icon={Plus}
          title="Không tìm thấy dự án"
          description={
            filters.search || filters.status.length > 0 || Object.keys(filters.dateRange).length > 0
              ? "Thử thay đổi bộ lọc hoặc từ khóa tìm kiếm"
              : "Bạn chưa có dự án nào. Hãy tạo dự án đầu tiên!"
          }
          action={
            <Button asChild>
              <Link to="/projects/new">
                <Plus className="w-4 h-4 mr-2" />
                Tạo dự án mới
              </Link>
            </Button>
          }
        />
      ) : (
        <ResponsiveGrid
          mode={viewMode}
          items={filteredAndSortedProjects}
          renderItem={(project) => (
            <ProjectCard
              key={project.id}
              project={project}
              onEdit={handleEditProject}
              onDelete={handleDeleteProject}
            />
          )}
          gridCols={{ sm: 1, md: 2, lg: 3 }}
          gap="6"
        />
      )}
    </div>
  );
};