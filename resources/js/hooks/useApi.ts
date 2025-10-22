import { useCallback } from 'react';

interface ApiResponse<T = any> {
  data: T;
  message?: string;
  status: number;
}

interface ApiError {
  message: string;
  status?: number;
  errors?: Record<string, string[]>;
}

export const useApi = () => {
  const baseUrl = '/api'; // Adjust this to match your API base URL
  
  // Get auth token from localStorage or use default test token
  const getAuthToken = () => {
    return localStorage.getItem('auth_token') || 'eyJ1c2VyX2lkIjoyOTE0LCJlbWFpbCI6InN1cGVyYWRtaW5AemVuYS5jb20iLCJyb2xlIjoic3VwZXJfYWRtaW4iLCJleHBpcmVzIjoxNzU4NjE2OTIwfQ==';
  };
  
  const getHeaders = useCallback((includeAuth = true) => {
    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    };
    
    if (includeAuth) {
      headers['Authorization'] = `Bearer ${getAuthToken()}`;
    }
    
    return headers;
  }, []);
  
  const handleResponse = async <T>(response: Response): Promise<ApiResponse<T>> => {
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}));
      throw {
        message: errorData.message || `HTTP ${response.status}`,
        status: response.status,
        errors: errorData.errors
      } as ApiError;
    }
    
    const data = await response.json();
    return {
      data: data.data || data,
      message: data.message,
      status: response.status
    };
  };

  const get = useCallback(async <T = any>(url: string, includeAuth = true): Promise<ApiResponse<T>> => {
    const response = await fetch(`${baseUrl}${url}`, {
      method: 'GET',
      headers: getHeaders(includeAuth),
    });
    
    return handleResponse<T>(response);
  }, [getHeaders]);

  const post = useCallback(async <T = any>(url: string, data?: any, includeAuth = true): Promise<ApiResponse<T>> => {
    const response = await fetch(`${baseUrl}${url}`, {
      method: 'POST',
      headers: getHeaders(includeAuth),
      body: data ? JSON.stringify(data) : undefined,
    });
    
    return handleResponse<T>(response);
  }, [getHeaders]);

  const put = useCallback(async <T = any>(url: string, data?: any, includeAuth = true): Promise<ApiResponse<T>> => {
    const response = await fetch(`${baseUrl}${url}`, {
      method: 'PUT',
      headers: getHeaders(includeAuth),
      body: data ? JSON.stringify(data) : undefined,
    });
    
    return handleResponse<T>(response);
  }, [getHeaders]);

  const del = useCallback(async <T = any>(url: string, includeAuth = true): Promise<ApiResponse<T>> => {
    const response = await fetch(`${baseUrl}${url}`, {
      method: 'DELETE',
      headers: getHeaders(includeAuth),
    });
    
    return handleResponse<T>(response);
  }, [getHeaders]);

  // Dashboard API methods
  const getAdminDashboard = useCallback(async (): Promise<ApiResponse> => {
    const response = await fetch('/test-api-admin-dashboard', {
      method: 'GET',
      headers: getHeaders(true),
    });
    
    return handleResponse(response);
  }, [getHeaders]);

  const getAppDashboard = useCallback(async (): Promise<ApiResponse> => {
    const response = await fetch('/test-api-app-dashboard', {
      method: 'GET',
      headers: getHeaders(true),
    });
    
    return handleResponse(response);
  }, [getHeaders]);

  return {
    get,
    post,
    put,
    delete: del,
    getAdminDashboard,
    getAppDashboard,
    getAuthToken,
  };
};
