import { apiClient } from '../../../shared/api/client';
import type {
  Document,
  DocumentsResponse,
  DocumentsFilters,
  UploadDocumentRequest,
  UpdateDocumentRequest,
  DocumentStats,
  DocumentVersion
} from './types';

export const documentsApi = {
  // Get paginated list of documents
  getDocuments: async (filters: DocumentsFilters = {}): Promise<DocumentsResponse> => {
    const params = new URLSearchParams();
    
    if (filters.search) params.append('search', filters.search);
    if (filters.project_id) params.append('project_id', filters.project_id.toString());
    if (filters.mime_type) params.append('mime_type', filters.mime_type);
    if (filters.tags && filters.tags.length > 0) {
      filters.tags.forEach(tag => params.append('tags[]', tag));
    }
    if (filters.uploaded_by) params.append('uploaded_by', filters.uploaded_by.toString());
    if (filters.is_public !== undefined) params.append('is_public', filters.is_public.toString());
    if (filters.page) params.append('page', filters.page.toString());
    if (filters.per_page) params.append('per_page', filters.per_page.toString());
    if (filters.sort_by) params.append('sort_by', filters.sort_by);
    if (filters.sort_order) params.append('sort_order', filters.sort_order);

    const response = await apiClient.get(`/api/v1/documents?${params.toString()}`);
    return response.data;
  },

  // Get single document by ID
  getDocument: async (id: number): Promise<{ data: Document }> => {
    const response = await apiClient.get(`/api/v1/documents/${id}`);
    return response.data;
  },

  // Upload new document
  uploadDocument: async (uploadData: UploadDocumentRequest): Promise<{ data: Document }> => {
    const formData = new FormData();
    formData.append('file', uploadData.file);
    if (uploadData.project_id) formData.append('project_id', uploadData.project_id.toString());
    if (uploadData.description) formData.append('description', uploadData.description);
    if (uploadData.tags) {
      uploadData.tags.forEach(tag => formData.append('tags[]', tag));
    }
    if (uploadData.is_public !== undefined) {
      formData.append('is_public', uploadData.is_public.toString());
    }

    const response = await apiClient.post('/api/v1/documents', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  // Update document metadata
  updateDocument: async (id: number, documentData: UpdateDocumentRequest): Promise<{ data: Document }> => {
    const response = await apiClient.put(`/api/v1/documents/${id}`, documentData);
    return response.data;
  },

  // Delete document
  deleteDocument: async (id: number): Promise<void> => {
    await apiClient.delete(`/api/v1/documents/${id}`);
  },

  // Download document
  downloadDocument: async (id: number): Promise<Blob> => {
    const response = await apiClient.get(`/api/v1/documents/${id}/download`, {
      responseType: 'blob',
    });
    return response.data;
  },

  // Get document stats
  getDocumentStats: async (): Promise<{ data: DocumentStats }> => {
    const response = await apiClient.get('/api/v1/documents/stats');
    return response.data;
  },

  // Get document versions
  getDocumentVersions: async (id: number): Promise<{ data: DocumentVersion[] }> => {
    const response = await apiClient.get(`/api/v1/documents/${id}/versions`);
    return response.data;
  },

  // Upload new version
  uploadNewVersion: async (id: number, file: File, changeDescription?: string): Promise<{ data: Document }> => {
    const formData = new FormData();
    formData.append('file', file);
    if (changeDescription) formData.append('change_description', changeDescription);

    const response = await apiClient.post(`/api/v1/documents/${id}/versions`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  // Bulk operations
  bulkDelete: async (documentIds: number[]): Promise<void> => {
    await apiClient.post('/api/v1/documents/bulk-delete', {
      document_ids: documentIds
    });
  },

  bulkUpdateTags: async (documentIds: number[], tags: string[]): Promise<void> => {
    await apiClient.post('/api/v1/documents/bulk-update-tags', {
      document_ids: documentIds,
      tags
    });
  },

  bulkUpdateVisibility: async (documentIds: number[], isPublic: boolean): Promise<void> => {
    await apiClient.post('/api/v1/documents/bulk-update-visibility', {
      document_ids: documentIds,
      is_public: isPublic
    });
  }
};
