import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { documentsApi } from './api';
import type { DocumentsFilters, UploadDocumentRequest, UpdateDocumentRequest } from './types';
import { invalidateFor, createInvalidationContext } from '@/shared/api/invalidateMap';

/**
 * Documents Hooks (React Query)
 */

export const useDocuments = (
  filters?: DocumentsFilters,
  pagination?: { page?: number; per_page?: number },
  options?: { enabled?: boolean }
) => {
  const { enabled = true } = options ?? {};
  return useQuery({
    queryKey: ['documents', filters, pagination],
    queryFn: () => documentsApi.getDocuments(filters || {}, pagination),
    enabled,
    staleTime: 30 * 1000, // 30 seconds
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useDocument = (id: string | number) => {
  return useQuery({
    queryKey: ['document', id],
    queryFn: () => documentsApi.getDocument(id),
    enabled: !!id,
    staleTime: 30 * 1000, // 30 seconds
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useUploadDocument = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (uploadData: UploadDocumentRequest) => documentsApi.uploadDocument(uploadData),
    onSuccess: (_, data) => {
      invalidateFor('document.upload', createInvalidationContext(queryClient, {
        projectId: data.project_id,
      }));
    },
  });
};

export const useUpdateDocument = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, data }: { id: string | number; data: UpdateDocumentRequest }) =>
      documentsApi.updateDocument(id, data),
    onSuccess: (_, variables) => {
      invalidateFor('document.update', createInvalidationContext(queryClient, {
        resourceId: variables.id,
        projectId: variables.data.project_id,
      }));
    },
  });
};

export const useDeleteDocument = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string | number) => documentsApi.deleteDocument(id),
    onSuccess: (_, id) => {
      invalidateFor('document.delete', createInvalidationContext(queryClient, {
        resourceId: id,
      }));
    },
  });
};

export const useDownloadDocument = () => {
  return useMutation({
    mutationFn: (id: string | number) => documentsApi.downloadDocument(id),
  });
};

export const useDocumentsKpis = (options?: { enabled?: boolean }) => {
  const { enabled = true } = options ?? {};
  return useQuery({
    queryKey: ['documents', 'kpis'],
    queryFn: () => documentsApi.getDocumentsKpis(),
    enabled,
    staleTime: 60 * 1000, // 60 seconds - KPIs can be cached longer
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useDocumentsAlerts = (options?: { enabled?: boolean }) => {
  const { enabled = true } = options ?? {};
  return useQuery({
    queryKey: ['documents', 'alerts'],
    queryFn: () => documentsApi.getDocumentsAlerts(),
    enabled,
    staleTime: 30 * 1000, // 30 seconds
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

export const useDocumentsActivity = (limit: number = 10, options?: { enabled?: boolean }) => {
  const { enabled = true } = options ?? {};
  return useQuery({
    queryKey: ['documents', 'activity', limit],
    queryFn: () => documentsApi.getDocumentsActivity(limit),
    enabled,
    staleTime: 15 * 1000, // 15 seconds - activity updates more frequently
    gcTime: 5 * 60 * 1000, // 5 minutes
  });
};

