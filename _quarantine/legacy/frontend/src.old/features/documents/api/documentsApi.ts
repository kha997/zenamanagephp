import { apiClient } from '@/lib/api';
import {
  Document,
  DocumentFilters,
  CreateDocumentData,
  UpdateDocumentData,
  UploadVersionData,
  ApproveForClientData,
  DocumentsResponse,
  DocumentResponse,
  DocumentVersionsResponse,
  DocumentStatsResponse
} from '../types/document';

/**
 * Lấy danh sách documents với filtering và pagination
 */
export const getDocuments = async (filters?: DocumentFilters): Promise<DocumentsResponse> => {
  const params = new URLSearchParams();
  
  if (filters) {
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        if (Array.isArray(value)) {
          value.forEach(item => params.append(`${key}[]`, item.toString()));
        } else {
          params.append(key, value.toString());
        }
      }
    });
  }
  
  const response = await apiClient.get(`/documents?${params.toString()}`);
  return response.data;
};

/**
 * Lấy chi tiết một document
 */
export const getDocument = async (id: string): Promise<DocumentResponse> => {
  const response = await apiClient.get(`/documents/${id}`);
  return response.data;
};

/**
 * Tạo document mới
 */
export const createDocument = async (data: CreateDocumentData): Promise<DocumentResponse> => {
  const formData = new FormData();
  
  // Append các field thông thường
  formData.append('project_id', data.project_id);
  formData.append('title', data.title);
  formData.append('visibility', data.visibility);
  formData.append('file', data.file);
  
  if (data.description) formData.append('description', data.description);
  if (data.linked_entity_type) formData.append('linked_entity_type', data.linked_entity_type);
  if (data.linked_entity_id) formData.append('linked_entity_id', data.linked_entity_id);
  if (data.comment) formData.append('comment', data.comment);
  
  if (data.tags && data.tags.length > 0) {
    data.tags.forEach(tag => formData.append('tags[]', tag));
  }
  
  const response = await apiClient.post('/documents', formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  });
  return response.data;
};

/**
 * Cập nhật document
 */
export const updateDocument = async (id: string, data: UpdateDocumentData): Promise<DocumentResponse> => {
  const response = await apiClient.put(`/documents/${id}`, data);
  return response.data;
};

/**
 * Xóa document
 */
export const deleteDocument = async (id: string): Promise<void> => {
  await apiClient.delete(`/documents/${id}`);
};

/**
 * Upload version mới cho document
 */
export const uploadDocumentVersion = async (id: string, data: UploadVersionData): Promise<DocumentResponse> => {
  const formData = new FormData();
  formData.append('file', data.file);
  if (data.comment) formData.append('comment', data.comment);
  
  const response = await apiClient.post(`/documents/${id}/versions`, formData, {
    headers: {
      'Content-Type': 'multipart/form-data',
    },
  });
  return response.data;
};

/**
 * Lấy danh sách versions của document
 */
export const getDocumentVersions = async (id: string): Promise<DocumentVersionsResponse> => {
  const response = await apiClient.get(`/documents/${id}/versions`);
  return response.data;
};

/**
 * Revert document về version cũ
 */
export const revertDocumentVersion = async (id: string, versionNumber: number): Promise<DocumentResponse> => {
  const response = await apiClient.post(`/documents/${id}/versions/${versionNumber}/revert`);
  return response.data;
};

/**
 * Download document version
 */
export const downloadDocumentVersion = async (id: string, versionNumber?: number): Promise<Blob> => {
  const url = versionNumber 
    ? `/documents/${id}/versions/${versionNumber}/download`
    : `/documents/${id}/download`;
    
  const response = await apiClient.get(url, {
    responseType: 'blob',
  });
  return response.data;
};

/**
 * Phê duyệt document cho client
 */
export const approveDocumentForClient = async (id: string, data: ApproveForClientData): Promise<DocumentResponse> => {
  const response = await apiClient.post(`/documents/${id}/approve-for-client`, data);
  return response.data;
};

/**
 * Lấy thống kê documents
 */
export const getDocumentStats = async (projectId?: string): Promise<DocumentStatsResponse> => {
  const params = projectId ? `?project_id=${projectId}` : '';
  const response = await apiClient.get(`/documents/stats${params}`);
  return response.data;
};

/**
 * Lấy documents theo project
 */
export const getDocumentsByProject = async (projectId: string, filters?: Omit<DocumentFilters, 'project_id'>): Promise<DocumentsResponse> => {
  const params = new URLSearchParams();
  params.append('project_id', projectId);
  
  if (filters) {
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        if (Array.isArray(value)) {
          value.forEach(item => params.append(`${key}[]`, item.toString()));
        } else {
          params.append(key, value.toString());
        }
      }
    });
  }
  
  const response = await apiClient.get(`/documents?${params.toString()}`);
  return response.data;
};

/**
 * Tìm kiếm documents
 */
export const searchDocuments = async (query: string, filters?: DocumentFilters): Promise<DocumentsResponse> => {
  const searchFilters = { ...filters, search: query };
  return getDocuments(searchFilters);
};