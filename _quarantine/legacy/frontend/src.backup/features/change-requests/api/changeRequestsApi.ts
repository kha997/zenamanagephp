import { apiClient } from '@/lib/api';
import {
  ChangeRequest,
  ChangeRequestFilters,
  CreateChangeRequestData,
  UpdateChangeRequestData,
  ChangeRequestDecision,
  ChangeRequestsResponse,
  ChangeRequestResponse,
  ChangeRequestStats
} from '../types/changeRequest';

/**
 * Lấy danh sách change requests với bộ lọc
 */
export const getChangeRequests = async (filters: ChangeRequestFilters = {}): Promise<ChangeRequestsResponse> => {
  const params = new URLSearchParams();
  
  Object.entries(filters).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      params.append(key, value.toString());
    }
  });

  const response = await apiClient.get(`/change-requests?${params.toString()}`);
  return response.data;
};

/**
 * Lấy chi tiết một change request
 */
export const getChangeRequest = async (id: string): Promise<ChangeRequestResponse> => {
  const response = await apiClient.get(`/change-requests/${id}`);
  return response.data;
};

/**
 * Tạo change request mới
 */
export const createChangeRequest = async (data: CreateChangeRequestData): Promise<ChangeRequestResponse> => {
  const response = await apiClient.post('/change-requests', data);
  return response.data;
};

/**
 * Cập nhật change request
 */
export const updateChangeRequest = async (
  id: string, 
  data: UpdateChangeRequestData
): Promise<ChangeRequestResponse> => {
  const response = await apiClient.put(`/change-requests/${id}`, data);
  return response.data;
};

/**
 * Xóa change request
 */
export const deleteChangeRequest = async (id: string): Promise<void> => {
  await apiClient.delete(`/change-requests/${id}`);
};

/**
 * Gửi change request để duyệt
 */
export const submitChangeRequest = async (id: string): Promise<ChangeRequestResponse> => {
  const response = await apiClient.post(`/change-requests/${id}/submit`);
  return response.data;
};

/**
 * Đưa ra quyết định cho change request (approve/reject)
 */
export const decideChangeRequest = async (
  id: string, 
  decision: ChangeRequestDecision
): Promise<ChangeRequestResponse> => {
  const response = await apiClient.post(`/change-requests/${id}/decide`, decision);
  return response.data;
};

/**
 * Lấy thống kê change requests
 */
export const getChangeRequestStats = async (projectId?: string): Promise<ChangeRequestStats> => {
  const params = projectId ? `?project_id=${projectId}` : '';
  const response = await apiClient.get(`/change-requests/stats${params}`);
  return response.data;
};

/**
 * Lấy danh sách change requests theo project
 */
export const getChangeRequestsByProject = async (
  projectId: string, 
  filters: Omit<ChangeRequestFilters, 'project_id'> = {}
): Promise<ChangeRequestsResponse> => {
  return getChangeRequests({ ...filters, project_id: projectId });
};

/**
 * Duplicate change request
 */
export const duplicateChangeRequest = async (id: string): Promise<ChangeRequestResponse> => {
  const response = await apiClient.post(`/change-requests/${id}/duplicate`);
  return response.data;
};