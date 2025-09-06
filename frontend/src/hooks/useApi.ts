/**
 * Generic API hook với TanStack Query
 * Cung cấp các method chung cho API calls với caching và error handling
 */
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../lib/api/client';
import { ApiResponse } from '../lib/types';
import { useToast } from './useToast';

interface UseApiOptions {
  showSuccessToast?: boolean;
  showErrorToast?: boolean;
  successMessage?: string;
  onSuccess?: (data: any) => void;
  onError?: (error: any) => void;
}

export const useApi = () => {
  const queryClient = useQueryClient();
  const { showToast } = useToast();

  // Generic GET request with caching
  const useGet = <T>(
    key: string | string[],
    url: string,
    options?: {
      enabled?: boolean;
      staleTime?: number;
      cacheTime?: number;
    }
  ) => {
    return useQuery({
      queryKey: Array.isArray(key) ? key : [key],
      queryFn: async () => {
        const response = await apiClient.get<ApiResponse<T>>(url);
        if (response.data.status === 'success') {
          return response.data.data;
        }
        throw new Error(response.data.message || 'API request failed');
      },
      enabled: options?.enabled ?? true,
      staleTime: options?.staleTime ?? 5 * 60 * 1000, // 5 minutes
      cacheTime: options?.cacheTime ?? 10 * 60 * 1000, // 10 minutes
    });
  };

  // Generic POST request
  const usePost = <T, D = any>(
    url: string,
    options: UseApiOptions = {}
  ) => {
    return useMutation({
      mutationFn: async (data: D) => {
        const response = await apiClient.post<ApiResponse<T>>(url, data);
        if (response.data.status === 'success') {
          return response.data.data;
        }
        throw new Error(response.data.message || 'API request failed');
      },
      onSuccess: (data) => {
        if (options.showSuccessToast) {
          showToast(options.successMessage || 'Thao tác thành công!', 'success');
        }
        options.onSuccess?.(data);
      },
      onError: (error: any) => {
        if (options.showErrorToast !== false) {
          const message = error.response?.data?.message || error.message || 'Có lỗi xảy ra';
          showToast(message, 'error');
        }
        options.onError?.(error);
      }
    });
  };

  // Generic PUT request
  const usePut = <T, D = any>(
    url: string,
    options: UseApiOptions = {}
  ) => {
    return useMutation({
      mutationFn: async (data: D) => {
        const response = await apiClient.put<ApiResponse<T>>(url, data);
        if (response.data.status === 'success') {
          return response.data.data;
        }
        throw new Error(response.data.message || 'API request failed');
      },
      onSuccess: (data) => {
        if (options.showSuccessToast) {
          showToast(options.successMessage || 'Cập nhật thành công!', 'success');
        }
        options.onSuccess?.(data);
      },
      onError: (error: any) => {
        if (options.showErrorToast !== false) {
          const message = error.response?.data?.message || error.message || 'Có lỗi xảy ra';
          showToast(message, 'error');
        }
        options.onError?.(error);
      }
    });
  };

  // Generic DELETE request
  const useDelete = (
    url: string,
    options: UseApiOptions = {}
  ) => {
    return useMutation({
      mutationFn: async (id: number | string) => {
        const response = await apiClient.delete<ApiResponse<void>>(`${url}/${id}`);
        if (response.data.status === 'success') {
          return response.data.data;
        }
        throw new Error(response.data.message || 'API request failed');
      },
      onSuccess: (data) => {
        if (options.showSuccessToast) {
          showToast(options.successMessage || 'Xóa thành công!', 'success');
        }
        options.onSuccess?.(data);
      },
      onError: (error: any) => {
        if (options.showErrorToast !== false) {
          const message = error.response?.data?.message || error.message || 'Có lỗi xảy ra';
          showToast(message, 'error');
        }
        options.onError?.(error);
      }
    });
  };

  // Invalidate queries (for cache refresh)
  const invalidateQueries = (key: string | string[]) => {
    queryClient.invalidateQueries({
      queryKey: Array.isArray(key) ? key : [key]
    });
  };

  // Set query data manually
  const setQueryData = (key: string | string[], data: any) => {
    queryClient.setQueryData(Array.isArray(key) ? key : [key], data);
  };

  return {
    useGet,
    usePost,
    usePut,
    useDelete,
    invalidateQueries,
    setQueryData,
    queryClient
  };
};