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

  const get = useCallback(async <T = any>(url: string): Promise<ApiResponse<T>> => {
    const response = await fetch(`${baseUrl}${url}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        // Add authorization header if needed
        // 'Authorization': `Bearer ${token}`,
      },
    });
    
    return handleResponse<T>(response);
  }, []);

  const post = useCallback(async <T = any>(url: string, data?: any): Promise<ApiResponse<T>> => {
    const response = await fetch(`${baseUrl}${url}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        // Add authorization header if needed
        // 'Authorization': `Bearer ${token}`,
      },
      body: data ? JSON.stringify(data) : undefined,
    });
    
    return handleResponse<T>(response);
  }, []);

  const put = useCallback(async <T = any>(url: string, data?: any): Promise<ApiResponse<T>> => {
    const response = await fetch(`${baseUrl}${url}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        // Add authorization header if needed
        // 'Authorization': `Bearer ${token}`,
      },
      body: data ? JSON.stringify(data) : undefined,
    });
    
    return handleResponse<T>(response);
  }, []);

  const del = useCallback(async <T = any>(url: string): Promise<ApiResponse<T>> => {
    const response = await fetch(`${baseUrl}${url}`, {
      method: 'DELETE',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        // Add authorization header if needed
        // 'Authorization': `Bearer ${token}`,
      },
    });
    
    return handleResponse<T>(response);
  }, []);

  return {
    get,
    post,
    put,
    delete: del,
  };
};
