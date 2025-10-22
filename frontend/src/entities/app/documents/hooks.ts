import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { documentsApi } from './api';
import type {
  DocumentsFilters,
  UploadDocumentRequest,
  UpdateDocumentRequest
} from './types';

// Query Keys
export const documentsKeys = {
  all: ['documents'] as const,
  lists: () => [...documentsKeys.all, 'list'] as const,
  list: (filters: DocumentsFilters) => [...documentsKeys.lists(), filters] as const,
  details: () => [...documentsKeys.all, 'detail'] as const,
  detail: (id: number) => [...documentsKeys.details(), id] as const,
  stats: () => [...documentsKeys.all, 'stats'] as const,
  versions: (id: number) => [...documentsKeys.detail(id), 'versions'] as const,
};

// Get documents list with filters
export const useDocuments = (filters: DocumentsFilters = {}) => {
  return useQuery({
    queryKey: documentsKeys.list(filters),
    queryFn: () => documentsApi.getDocuments(filters),
    staleTime: 30_000, // 30 seconds
    retry: 1,
  });
};

// Get single document
export const useDocument = (id: number, enabled: boolean = true) => {
  return useQuery({
    queryKey: documentsKeys.detail(id),
    queryFn: () => documentsApi.getDocument(id),
    enabled: enabled && !!id,
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

// Get document stats
export const useDocumentStats = () => {
  return useQuery({
    queryKey: documentsKeys.stats(),
    queryFn: () => documentsApi.getDocumentStats(),
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

// Get document versions
export const useDocumentVersions = (id: number, enabled: boolean = true) => {
  return useQuery({
    queryKey: documentsKeys.versions(id),
    queryFn: () => documentsApi.getDocumentVersions(id),
    enabled: enabled && !!id,
    staleTime: 60_000, // 1 minute
    retry: 1,
  });
};

// Upload document mutation
export const useUploadDocument = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (uploadData: UploadDocumentRequest) => documentsApi.uploadDocument(uploadData),
    onSuccess: () => {
      // Invalidate documents list and stats
      queryClient.invalidateQueries({ queryKey: documentsKeys.lists() });
      queryClient.invalidateQueries({ queryKey: documentsKeys.stats() });
    },
  });
};

// Update document mutation
export const useUpdateDocument = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, documentData }: { id: number; documentData: UpdateDocumentRequest }) =>
      documentsApi.updateDocument(id, documentData),
    onSuccess: (_, { id }) => {
      // Invalidate specific document and documents list
      queryClient.invalidateQueries({ queryKey: documentsKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: documentsKeys.lists() });
    },
  });
};

// Delete document mutation
export const useDeleteDocument = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: number) => documentsApi.deleteDocument(id),
    onSuccess: () => {
      // Invalidate documents list and stats
      queryClient.invalidateQueries({ queryKey: documentsKeys.lists() });
      queryClient.invalidateQueries({ queryKey: documentsKeys.stats() });
    },
  });
};

// Download document mutation
export const useDownloadDocument = () => {
  return useMutation({
    mutationFn: (id: number) => documentsApi.downloadDocument(id),
  });
};

// Upload new version mutation
export const useUploadNewVersion = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, file, changeDescription }: { id: number; file: File; changeDescription?: string }) =>
      documentsApi.uploadNewVersion(id, file, changeDescription),
    onSuccess: (_, { id }) => {
      // Invalidate specific document, versions, and documents list
      queryClient.invalidateQueries({ queryKey: documentsKeys.detail(id) });
      queryClient.invalidateQueries({ queryKey: documentsKeys.versions(id) });
      queryClient.invalidateQueries({ queryKey: documentsKeys.lists() });
    },
  });
};

// Bulk delete mutation
export const useBulkDeleteDocuments = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (documentIds: number[]) => documentsApi.bulkDelete(documentIds),
    onSuccess: () => {
      // Invalidate documents list and stats
      queryClient.invalidateQueries({ queryKey: documentsKeys.lists() });
      queryClient.invalidateQueries({ queryKey: documentsKeys.stats() });
    },
  });
};

// Bulk update tags mutation
export const useBulkUpdateDocumentTags = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ documentIds, tags }: { documentIds: number[]; tags: string[] }) =>
      documentsApi.bulkUpdateTags(documentIds, tags),
    onSuccess: () => {
      // Invalidate documents list
      queryClient.invalidateQueries({ queryKey: documentsKeys.lists() });
    },
  });
};

// Bulk update visibility mutation
export const useBulkUpdateDocumentVisibility = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ documentIds, isPublic }: { documentIds: number[]; isPublic: boolean }) =>
      documentsApi.bulkUpdateVisibility(documentIds, isPublic),
    onSuccess: () => {
      // Invalidate documents list
      queryClient.invalidateQueries({ queryKey: documentsKeys.lists() });
    },
  });
};
