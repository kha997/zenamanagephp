/**
 * Components Hooks
 * Custom hooks cho data fetching và state management
 */
import { useState, useEffect, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'react-hot-toast';
import { componentsApi } from '../api/componentsApi';
import { useAuthStore } from '@/store/auth';
import type {
  Component,
  ComponentFilters,
  CreateComponentRequest,
  UpdateComponentRequest,
  ComponentProgressUpdate
} from '../types/component';

/**
 * Hook để lấy danh sách components với pagination và filters
 */
export const useComponents = (
  projectId: string,
  filters: ComponentFilters & { page?: number; per_page?: number } = {}
) => {
  const { hasPermission } = useAuthStore();
  
  const query = useQuery({
    queryKey: ['components', projectId, filters],
    queryFn: () => componentsApi.list(projectId, filters),
    enabled: !!projectId && hasPermission('component.view', projectId),
    staleTime: 5 * 60 * 1000, // 5 minutes
    retry: 2
  });

  return {
    components: query.data?.data || [],
    meta: query.data?.meta,
    links: query.data?.links,
    isLoading: query.isLoading,
    error: query.error,
    refetch: query.refetch
  };
};

/**
 * Hook để lấy chi tiết component
 */
export const useComponent = (projectId: string, componentId: string) => {
  const { hasPermission } = useAuthStore();
  
  const query = useQuery({
    queryKey: ['component', projectId, componentId],
    queryFn: () => componentsApi.get(projectId, componentId),
    enabled: !!projectId && !!componentId && hasPermission('component.view', projectId),
    staleTime: 5 * 60 * 1000,
    retry: 2
  });

  return {
    component: query.data?.data,
    isLoading: query.isLoading,
    error: query.error,
    refetch: query.refetch
  };
};

/**
 * Hook để lấy cấu trúc tree components
 */
export const useComponentsTree = (projectId: string) => {
  const { hasPermission } = useAuthStore();
  
  const query = useQuery({
    queryKey: ['components-tree', projectId],
    queryFn: () => componentsApi.getTree(projectId),
    enabled: !!projectId && hasPermission('component.view', projectId),
    staleTime: 5 * 60 * 1000,
    retry: 2
  });

  return {
    tree: query.data?.data || [],
    isLoading: query.isLoading,
    error: query.error,
    refetch: query.refetch
  };
};

/**
 * Hook để tạo component mới
 */
export const useCreateComponent = (projectId: string) => {
  const queryClient = useQueryClient();
  const { hasPermission } = useAuthStore();

  return useMutation({
    mutationFn: (data: CreateComponentRequest) => {
      if (!hasPermission('component.create', projectId)) {
        throw new Error('Bạn không có quyền tạo component');
      }
      return componentsApi.create(projectId, data);
    },
    onSuccess: (response) => {
      // Invalidate và refetch các queries liên quan
      queryClient.invalidateQueries({ queryKey: ['components', projectId] });
      queryClient.invalidateQueries({ queryKey: ['components-tree', projectId] });
      
      toast.success('Tạo component thành công!');
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Có lỗi xảy ra khi tạo component';
      toast.error(message);
    }
  });
};

/**
 * Hook để cập nhật component
 */
export const useUpdateComponent = (projectId: string, componentId: string) => {
  const queryClient = useQueryClient();
  const { hasPermission } = useAuthStore();

  return useMutation({
    mutationFn: (data: UpdateComponentRequest) => {
      if (!hasPermission('component.edit', projectId)) {
        throw new Error('Bạn không có quyền chỉnh sửa component');
      }
      return componentsApi.update(projectId, componentId, data);
    },
    onSuccess: (response) => {
      // Update cache với optimistic update
      queryClient.setQueryData(
        ['component', projectId, componentId],
        response
      );
      
      // Invalidate lists
      queryClient.invalidateQueries({ queryKey: ['components', projectId] });
      queryClient.invalidateQueries({ queryKey: ['components-tree', projectId] });
      
      toast.success('Cập nhật component thành công!');
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Có lỗi xảy ra khi cập nhật component';
      toast.error(message);
    }
  });
};

/**
 * Hook để xóa component
 */
export const useDeleteComponent = (projectId: string) => {
  const queryClient = useQueryClient();
  const { hasPermission } = useAuthStore();

  return useMutation({
    mutationFn: (componentId: string) => {
      if (!hasPermission('component.delete', projectId)) {
        throw new Error('Bạn không có quyền xóa component');
      }
      return componentsApi.delete(projectId, componentId);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['components', projectId] });
      queryClient.invalidateQueries({ queryKey: ['components-tree', projectId] });
      
      toast.success('Xóa component thành công!');
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Có lỗi xảy ra khi xóa component';
      toast.error(message);
    }
  });
};

/**
 * Hook để tính toán lại progress
 */
export const useRecalculateProgress = (projectId: string, componentId: string) => {
  const queryClient = useQueryClient();
  const { hasPermission } = useAuthStore();

  return useMutation({
    mutationFn: () => {
      if (!hasPermission('component.edit', projectId)) {
        throw new Error('Bạn không có quyền cập nhật component');
      }
      return componentsApi.recalculateProgress(projectId, componentId);
    },
    onSuccess: (response) => {
      queryClient.setQueryData(
        ['component', projectId, componentId],
        response
      );
      
      queryClient.invalidateQueries({ queryKey: ['components', projectId] });
      queryClient.invalidateQueries({ queryKey: ['components-tree', projectId] });
      
      toast.success('Tính toán lại tiến độ thành công!');
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Có lỗi xảy ra khi tính toán tiến độ';
      toast.error(message);
    }
  });
};

/**
 * Hook để lấy tổng quan chi phí
 */
export const useComponentsCostSummary = (projectId: string) => {
  const { hasPermission } = useAuthStore();
  
  const query = useQuery({
    queryKey: ['components-cost-summary', projectId],
    queryFn: () => componentsApi.getCostSummary(projectId),
    enabled: !!projectId && hasPermission('component.view', projectId),
    staleTime: 2 * 60 * 1000, // 2 minutes
    retry: 2
  });

  return {
    costSummary: query.data?.data,
    isLoading: query.isLoading,
    error: query.error,
    refetch: query.refetch
  };
};

/**
 * Hook để cập nhật progress hàng loạt
 */
export const useBulkUpdateProgress = (projectId: string) => {
  const queryClient = useQueryClient();
  const { hasPermission } = useAuthStore();

  return useMutation({
    mutationFn: (updates: ComponentProgressUpdate[]) => {
      if (!hasPermission('component.edit', projectId)) {
        throw new Error('Bạn không có quyền cập nhật component');
      }
      return componentsApi.bulkUpdateProgress(projectId, updates);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['components', projectId] });
      queryClient.invalidateQueries({ queryKey: ['components-tree', projectId] });
      
      toast.success('Cập nhật tiến độ hàng loạt thành công!');
    },
    onError: (error: any) => {
      const message = error.response?.data?.message || 'Có lỗi xảy ra khi cập nhật tiến độ';
      toast.error(message);
    }
  });
};

/**
 * Hook để quản lý filters với debounce
 */
export const useComponentsFilters = (initialFilters: ComponentFilters = {}) => {
  const [filters, setFilters] = useState<ComponentFilters>(initialFilters);
  const [debouncedFilters, setDebouncedFilters] = useState<ComponentFilters>(initialFilters);

  // Debounce filters để tránh gọi API quá nhiều
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedFilters(filters);
    }, 500);

    return () => clearTimeout(timer);
  }, [filters]);

  const updateFilter = useCallback((key: keyof ComponentFilters, value: any) => {
    setFilters(prev => ({ ...prev, [key]: value }));
  }, []);

  const resetFilters = useCallback(() => {
    setFilters(initialFilters);
  }, [initialFilters]);

  return {
    filters,
    debouncedFilters,
    updateFilter,
    resetFilters,
    setFilters
  };
};