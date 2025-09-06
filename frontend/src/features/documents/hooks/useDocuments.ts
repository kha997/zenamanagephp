import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'react-hot-toast';
import {
  getDocuments,
  createDocument,
  updateDocument,
  deleteDocument,
  getDocumentsByProject,
  searchDocuments,
  getDocumentStats
} from '../api/documentsApi';
import type {
  Document,
  DocumentFilters,
  CreateDocumentData,
  UpdateDocumentData,
  DocumentsResponse,
  DocumentStats
} from '../types/document';

// Hook để lấy danh sách documents với filters
export const useDocuments = (filters?: DocumentFilters) => {
  return useQuery({
    queryKey: ['documents', filters],
    queryFn: () => getDocuments(filters),
    staleTime: 5 * 60 * 1000, // 5 phút
    cacheTime: 10 * 60 * 1000, // 10 phút
  });
};

// Hook để lấy documents theo project
export const useDocumentsByProject = (projectId: number) => {
  return useQuery({
    queryKey: ['documents', 'project', projectId],
    queryFn: () => getDocumentsByProject(projectId),
    enabled: !!projectId,
    staleTime: 5 * 60 * 1000,
  });
};

// Hook để search documents
export const useSearchDocuments = (query: string, enabled: boolean = true) => {
  return useQuery({
    queryKey: ['documents', 'search', query],
    queryFn: () => searchDocuments(query),
    enabled: enabled && query.length > 0,
    staleTime: 2 * 60 * 1000, // 2 phút cho search
  });
};

// Hook để lấy thống kê documents
export const useDocumentStats = (projectId?: number) => {
  return useQuery({
    queryKey: ['documents', 'stats', projectId],
    queryFn: () => getDocumentStats(projectId),
    staleTime: 10 * 60 * 1000, // 10 phút
  });
};

// Hook để tạo document mới
export const useCreateDocument = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (data: CreateDocumentData) => createDocument(data),
    onSuccess: (newDocument) => {
      // Invalidate và refetch documents list
      queryClient.invalidateQueries({ queryKey: ['documents'] });
      queryClient.invalidateQueries({ queryKey: ['documents', 'stats'] });
      
      // Nếu có project_id, invalidate documents của project đó
      if (newDocument.project_id) {
        queryClient.invalidateQueries({ 
          queryKey: ['documents', 'project', newDocument.project_id] 
        });
      }
      
      toast.success('Tài liệu đã được tạo thành công!');
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message || 'Có lỗi xảy ra khi tạo tài liệu';
      toast.error(message);
    },
  });
};

// Hook để cập nhật document
export const useUpdateDocument = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, data }: { id: number; data: UpdateDocumentData }) => 
      updateDocument(id, data),
    onSuccess: (updatedDocument) => {
      // Update cache cho document cụ thể
      queryClient.setQueryData(
        ['documents', 'detail', updatedDocument.id],
        updatedDocument
      );
      
      // Invalidate documents list
      queryClient.invalidateQueries({ queryKey: ['documents'] });
      
      if (updatedDocument.project_id) {
        queryClient.invalidateQueries({ 
          queryKey: ['documents', 'project', updatedDocument.project_id] 
        });
      }
      
      toast.success('Tài liệu đã được cập nhật thành công!');
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message || 'Có lỗi xảy ra khi cập nhật tài liệu';
      toast.error(message);
    },
  });
};

// Hook để xóa document
export const useDeleteDocument = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => deleteDocument(id),
    onSuccess: (_, deletedId) => {
      // Remove từ cache
      queryClient.removeQueries({ queryKey: ['documents', 'detail', deletedId] });
      
      // Invalidate documents list
      queryClient.invalidateQueries({ queryKey: ['documents'] });
      queryClient.invalidateQueries({ queryKey: ['documents', 'stats'] });
      
      toast.success('Tài liệu đã được xóa thành công!');
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message || 'Có lỗi xảy ra khi xóa tài liệu';
      toast.error(message);
    },
  });
};