import { createApiClient, mapAxiosError } from '../../shared/api/client';
import type {
  Document,
  DocumentsResponse,
  DocumentsFilters,
  UploadDocumentRequest,
  UpdateDocumentRequest,
  DocumentsMetrics,
  DocumentAlert,
  DocumentActivityItem,
} from './types';

const apiClient = createApiClient();

// Helper to transform API response to Document
const toDocument = (doc: any): Document => ({
  id: doc.id,
  name: doc.name ?? doc.title ?? doc.original_name ?? '',
  filename: doc.file_name ?? doc.filename ?? doc.original_name ?? '',
  original_filename: doc.original_name ?? doc.original_filename ?? doc.name ?? '',
  mime_type: doc.mime_type ?? doc.file_type ?? '',
  size: doc.file_size ?? doc.size ?? 0,
  path: doc.file_path ?? doc.path ?? '',
  url: doc.url ?? doc.path ?? '',
  project_id: doc.project_id ?? doc.project?.id,
  project_name: doc.project?.name ?? doc.project_name,
  uploaded_by: doc.uploaded_by ?? doc.uploader?.id ?? doc.created_by ?? 0,
  uploaded_by_name: doc.uploader?.name ?? doc.uploaded_by_name ?? doc.user?.name ?? '',
  uploaded_at: doc.created_at ?? doc.uploaded_at ?? '',
  updated_at: doc.updated_at ?? '',
  tags: Array.isArray(doc.tags) ? doc.tags : (doc.tags ? JSON.parse(doc.tags) : []),
  description: doc.description ?? '',
  version: doc.version ?? doc.current_version ?? 1,
  is_public: Boolean(doc.is_public),
  download_count: doc.download_count ?? 0,
  last_accessed_at: doc.last_accessed_at,
});

/**
 * Documents API Client
 * 
 * Endpoints from routes/api_v1.php: /api/v1/documents/*
 */
export const documentsApi = {
  /**
   * Get paginated list of documents
   * GET /api/v1/documents
   */
  async getDocuments(filters: DocumentsFilters = {}, pagination?: { page?: number; per_page?: number }): Promise<DocumentsResponse> {
    try {
      const params = new URLSearchParams();
      
      if (filters.search) params.append('search', filters.search);
      if (filters.project_id) params.append('project_id', filters.project_id.toString());
      if (filters.mime_type) params.append('mime_type', filters.mime_type);
      if (filters.tags && filters.tags.length > 0) {
        filters.tags.forEach(tag => params.append('tags[]', tag));
      }
      if (filters.uploaded_by) params.append('uploaded_by', filters.uploaded_by.toString());
      if (filters.is_public !== undefined) params.append('is_public', filters.is_public.toString());
      if (filters.sort_by) params.append('sort_by', filters.sort_by);
      if (filters.sort_order) params.append('sort_order', filters.sort_order);
      
      // Pagination
      const page = pagination?.page ?? filters.page ?? 1;
      const perPage = pagination?.per_page ?? filters.per_page ?? 12;
      params.append('page', page.toString());
      params.append('per_page', perPage.toString());

      const response = await apiClient.get<{ success?: boolean; data?: Document[]; meta?: any } | DocumentsResponse>(
        `/v1/app/documents?${params.toString()}`
      );
      
      // Handle both response formats
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        const apiResponse = response.data as { success: boolean; data: any[]; meta?: any };
        const documents = apiResponse.data || [];
        const meta = apiResponse.meta || {};
        
        return {
          data: documents.map(toDocument),
          meta: {
            current_page: meta.current_page ?? page,
            last_page: meta.last_page ?? meta.total_pages ?? 1,
            per_page: meta.per_page ?? perPage,
            total: meta.total ?? documents.length,
          },
          links: {
            first: meta.first_page_url ?? '',
            last: meta.last_page_url ?? '',
            prev: meta.prev_page_url ?? undefined,
            next: meta.next_page_url ?? undefined,
          },
        };
      }
      
      const responseData = response.data as DocumentsResponse;
      return {
        data: (responseData.data || []).map(toDocument),
        meta: responseData.meta || {
          current_page: page,
          last_page: 1,
          per_page: perPage,
          total: 0,
        },
        links: responseData.links || {
          first: '',
          last: '',
        },
      };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get single document by ID
   * GET /api/v1/documents/{id}
   */
  async getDocument(id: string | number): Promise<{ data: Document }> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: any } | { data: Document }>(
        `/v1/app/documents/${id}`
      );
      
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return { data: toDocument((response.data as any).data) };
      }
      return { data: toDocument((response.data as { data: Document }).data) };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Upload new document
   * POST /api/v1/documents
   */
  async uploadDocument(uploadData: UploadDocumentRequest): Promise<{ data: Document }> {
    try {
      const formData = new FormData();
      formData.append('file', uploadData.file);
      if (uploadData.project_id) formData.append('project_id', uploadData.project_id.toString());
      if (uploadData.description) formData.append('description', uploadData.description);
      if (uploadData.tags && uploadData.tags.length > 0) {
        uploadData.tags.forEach(tag => formData.append('tags[]', tag));
      }
      if (uploadData.is_public !== undefined) {
        formData.append('is_public', uploadData.is_public.toString());
      }

      const response = await apiClient.post<{ success?: boolean; data?: any } | { data: Document }>(
        '/v1/app/documents',
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        }
      );
      
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return { data: toDocument((response.data as any).data) };
      }
      return { data: toDocument((response.data as { data: Document }).data) };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Update document metadata
   * PUT /api/v1/documents/{id}
   */
  async updateDocument(id: string | number, documentData: UpdateDocumentRequest): Promise<{ data: Document }> {
    try {
      const response = await apiClient.put<{ success?: boolean; data?: any } | { data: Document }>(
        `/v1/app/documents/${id}`,
        documentData
      );
      
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return { data: toDocument((response.data as any).data) };
      }
      return { data: toDocument((response.data as { data: Document }).data) };
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Delete document
   * DELETE /api/v1/documents/{id}
   */
  async deleteDocument(id: string | number): Promise<void> {
    try {
      await apiClient.delete(`/v1/app/documents/${id}`);
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Download document
   * GET /api/v1/documents/{id}/download
   */
  async downloadDocument(id: string | number): Promise<Blob> {
    try {
      const response = await apiClient.get(`/v1/app/documents/${id}/download`, {
        responseType: 'blob',
      });
      return response.data as Blob;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get documents KPIs
   * GET /api/v1/documents/kpis
   */
  async getDocumentsKpis(): Promise<DocumentsMetrics> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: DocumentsMetrics } | DocumentsMetrics>(
        '/v1/app/documents/kpis'
      );
      
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return (response.data as any).data;
      }
      return response.data as DocumentsMetrics;
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get documents alerts
   * GET /api/v1/documents/alerts
   */
  async getDocumentsAlerts(): Promise<DocumentAlert[]> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: DocumentAlert[] } | { data: DocumentAlert[] }>(
        '/v1/app/documents/alerts'
      );
      
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return (response.data as any).data || [];
      }
      return (response.data as { data: DocumentAlert[] }).data || [];
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },

  /**
   * Get documents activity
   * GET /api/v1/documents/activity?limit={limit}
   */
  async getDocumentsActivity(limit: number = 10): Promise<DocumentActivityItem[]> {
    try {
      const response = await apiClient.get<{ success?: boolean; data?: DocumentActivityItem[] } | { data: DocumentActivityItem[] }>(
        `/v1/app/documents/activity?limit=${limit}`
      );
      
      if (response.data && typeof response.data === 'object' && 'success' in response.data) {
        return (response.data as any).data || [];
      }
      return (response.data as { data: DocumentActivityItem[] }).data || [];
    } catch (error) {
      throw mapAxiosError(error as any);
    }
  },
};

