/**
 * Components API Layer
 * Xử lý tất cả HTTP requests cho module Components
 */
import { api } from '@/lib/api';
import type {
  Component,
  ComponentListResponse,
  ComponentDetailResponse,
  CreateComponentRequest,
  UpdateComponentRequest,
  ComponentFilters,
  ComponentCostSummary,
  ComponentProgressUpdate
} from '../types/component';

export const componentsApi = {
  /**
   * Lấy danh sách components của project
   */
  list: async (
    projectId: string,
    filters: ComponentFilters & { page?: number; per_page?: number } = {}
  ): Promise<ComponentListResponse> => {
    const params = new URLSearchParams();
    
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, String(value));
      }
    });

    const response = await api.get(
      `/projects/${projectId}/components?${params.toString()}`
    );
    return response.data;
  },

  /**
   * Lấy chi tiết component
   */
  get: async (projectId: string, componentId: string): Promise<ComponentDetailResponse> => {
    const response = await api.get(`/projects/${projectId}/components/${componentId}`);
    return response.data;
  },

  /**
   * Tạo component mới
   */
  create: async (
    projectId: string,
    data: CreateComponentRequest
  ): Promise<ComponentDetailResponse> => {
    const response = await api.post(`/projects/${projectId}/components`, data);
    return response.data;
  },

  /**
   * Cập nhật component
   */
  update: async (
    projectId: string,
    componentId: string,
    data: UpdateComponentRequest
  ): Promise<ComponentDetailResponse> => {
    const response = await api.put(`/projects/${projectId}/components/${componentId}`, data);
    return response.data;
  },

  /**
   * Xóa component
   */
  delete: async (projectId: string, componentId: string): Promise<void> => {
    await api.delete(`/projects/${projectId}/components/${componentId}`);
  },

  /**
   * Lấy cấu trúc tree components
   */
  getTree: async (projectId: string): Promise<{ data: Component[] }> => {
    const response = await api.get(`/projects/${projectId}/components/tree`);
    return response.data;
  },

  /**
   * Tính toán lại progress của component
   */
  recalculateProgress: async (
    projectId: string,
    componentId: string
  ): Promise<ComponentDetailResponse> => {
    const response = await api.post(
      `/projects/${projectId}/components/${componentId}/recalculate-progress`
    );
    return response.data;
  },

  /**
   * Lấy tổng quan chi phí components
   */
  getCostSummary: async (projectId: string): Promise<{ data: ComponentCostSummary }> => {
    const response = await api.get(`/projects/${projectId}/components/cost-summary`);
    return response.data;
  },

  /**
   * Cập nhật progress hàng loạt
   */
  bulkUpdateProgress: async (
    projectId: string,
    updates: ComponentProgressUpdate[]
  ): Promise<{ data: Component[] }> => {
    const response = await api.post(
      `/projects/${projectId}/components/bulk-update-progress`,
      { updates }
    );
    return response.data;
  }
};