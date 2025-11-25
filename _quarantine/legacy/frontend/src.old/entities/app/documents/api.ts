import { apiClient } from '../../../shared/api/client';
import type {
  Document,
  DocumentsResponse,
  DocumentsFilters,
  UploadDocumentRequest,
  UpdateDocumentRequest,
  DocumentStats,
  DocumentVersion,
  DocumentActivity,
  DocumentsMetrics,
  DocumentAlert,
  DocumentActivityItem
} from './types';
import type { ApiResponse } from '../../dashboard/types';

const toDocument = (doc: any): Document => ({
  id: doc.id,
  name: doc.name ?? doc.title ?? '',
  filename: doc.file_name ?? doc.filename ?? '',
  original_filename: doc.original_name ?? doc.original_filename ?? doc.name ?? '',
  mime_type: doc.mime_type ?? doc.file_type ?? '',
  size: doc.file_size ?? doc.size ?? 0,
  path: doc.file_path ?? doc.path ?? '',
  url: doc.url ?? doc.path ?? '',
  project_id: doc.project_id ?? doc.project?.id,
  project_name: doc.project?.name,
  uploaded_by: doc.uploaded_by ?? doc.uploader?.id,
  uploaded_by_name: doc.uploader?.name ?? doc.uploaded_by_name ?? '',
  uploaded_at: doc.created_at ?? doc.uploaded_at ?? '',
  updated_at: doc.updated_at ?? '',
  tags: doc.tags ?? [],
  description: doc.description ?? '',
  version: doc.version ?? doc.currentVersion?.version_number ?? 1,
  is_public: Boolean(doc.is_public),
  download_count: doc.download_count ?? 0,
  last_accessed_at: doc.last_accessed_at,
});

const toDocumentVersion = (version: any): DocumentVersion => ({
  id: Number(version.id),
  version: version.version ?? version.version_number ?? 1,
  filename: version.filename ?? version.file_name ?? '',
  size: version.size ?? version.file_size ?? 0,
  mime_type: version.mime_type ?? version.file_type ?? '',
  checksum: version.checksum ?? version.file_hash,
  uploaded_at: version.created_at ?? version.uploaded_at ?? '',
  uploaded_by: version.created_by ?? version.uploaded_by ?? version.user_id ?? 0,
  uploaded_by_name: version.uploader?.name ?? version.uploaded_by_name ?? version.user_name ?? version.user?.name ?? '',
  change_description: version.change_description ?? version.comment,
  reverted_from_version: version.reverted_from_version ?? version.reverted_from_version_number,
});

const toDocumentActivity = (activity: any): DocumentActivity => ({
  id: String(activity.id ?? activity.event_id ?? `${Date.now()}`),
  action: activity.action ?? activity.event ?? 'upload',
  actor_id: activity.actor_id ?? activity.user_id ?? 0,
  actor_name: activity.actor_name ?? activity.user_name ?? '',
  metadata: activity.metadata ?? activity.payload ?? undefined,
  created_at: activity.created_at ?? activity.timestamp ?? new Date().toISOString(),
});

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
    const payload = response.data ?? {};
    const list = Array.isArray(payload.data?.documents)
      ? payload.data.documents
      : Array.isArray(payload.data)
        ? payload.data
        : [];
    const pagination = payload.data?.pagination ?? {};
    const currentPage = pagination.current_page ?? 1;
    const lastPage = pagination.last_page ?? currentPage;

    return {
      data: list.map(toDocument),
      meta: {
        current_page: currentPage,
        last_page: lastPage,
        per_page: pagination.per_page ?? filters.per_page ?? 12,
        total: pagination.total ?? list.length,
      },
      links: {
        first: pagination.first_page_url ?? '',
        last: pagination.last_page_url ?? '',
        prev: currentPage > 1 ? String(currentPage - 1) : undefined,
        next: currentPage < lastPage ? String(currentPage + 1) : undefined,
      },
    };
  },

  // Get single document by ID
  getDocument: async (id: number): Promise<{ data: Document }> => {
    const response = await apiClient.get(`/api/v1/documents/${id}`);
    const payload = response.data?.data ?? response.data ?? {};
    return {
      data: {
        ...toDocument(payload),
        versions: Array.isArray(payload.versions) ? payload.versions.map(toDocumentVersion) : undefined,
        activity: Array.isArray(payload.activity) ? payload.activity.map(toDocumentActivity) : undefined,
      },
    };
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
    return {
      data: toDocument(response.data.data ?? response.data),
    };
  },

  // Update document metadata
  updateDocument: async (id: number, documentData: UpdateDocumentRequest): Promise<{ data: Document }> => {
    const response = await apiClient.put(`/api/v1/documents/${id}`, documentData);
    return {
      data: toDocument(response.data.data ?? response.data),
    };
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

  // Download specific version
  downloadVersion: async (documentId: number, versionId: number): Promise<Blob> => {
    const response = await apiClient.get(`/api/v1/documents/${documentId}/versions/${versionId}/download`, {
      responseType: 'blob',
    });
    return response.data;
  },

  // Get document stats
  getDocumentStats: async (): Promise<{ data: DocumentStats }> => {
    const response = await apiClient.get('/api/v1/documents/stats');
    return {
      data: response.data.data ?? response.data,
    };
  },

  // Get document versions
  getDocumentVersions: async (id: number): Promise<{ data: DocumentVersion[] }> => {
    const response = await apiClient.get(`/api/v1/documents/${id}/versions`);
    const list = response.data?.data ?? response.data ?? [];
    return { data: list.map(toDocumentVersion) };
  },

  // Upload new version
  uploadNewVersion: async (id: number, file: File, changeDescription?: string): Promise<{ data: DocumentVersion }> => {
    const formData = new FormData();
    formData.append('file', file);
    if (changeDescription) formData.append('change_description', changeDescription);

    const response = await apiClient.post(`/api/v1/documents/${id}/versions`, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return { data: toDocumentVersion(response.data?.data ?? response.data) };
  },

  getDocumentActivity: async (id: number): Promise<{ data: DocumentActivity[] }> => {
    const response = await apiClient.get(`/api/v1/documents/${id}/activity`);
    const list = response.data?.data ?? response.data ?? [];
    return { data: list.map(toDocumentActivity) };
  },

  // Revert to previous version
  revertVersion: async (id: number, versionId: number, comment: string): Promise<{ data: Document }> => {
    const response = await apiClient.post(`/api/v1/documents/${id}/revert`, {
      version_id: versionId,
      comment
    });
    return { data: toDocument(response.data.data ?? response.data) };
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
  },

  // KPI, Alert, and Activity methods for Universal Page Frame
  getDocumentsKpis: async (period?: string): Promise<ApiResponse<DocumentsMetrics>> => {
    const params = new URLSearchParams();
    if (period) params.append('period', period);
    const response = await apiClient.get(`/app/documents/kpis?${params.toString()}`);
    return response.data;
  },

  getDocumentsAlerts: async (): Promise<ApiResponse<DocumentAlert[]>> => {
    const response = await apiClient.get('/app/documents/alerts');
    return response.data;
  },

  getDocumentsActivity: async (limit: number = 10): Promise<ApiResponse<DocumentActivityItem[]>> => {
    const params = new URLSearchParams();
    params.append('limit', limit.toString());
    const response = await apiClient.get(`/app/documents/activity?${params.toString()}`);
    return response.data;
  },
};
