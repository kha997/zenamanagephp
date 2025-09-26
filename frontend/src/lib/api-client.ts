import axios from 'axios';

// Base API client configuration
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000';

// Create axios instance
export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor to add auth token
apiClient.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor to handle errors
apiClient.interceptors.response.use(
  (response) => {
    return response;
  },
  (error) => {
    if (error.response?.status === 401) {
      // Handle unauthorized access
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
    return Promise.reject(error);
  }
);

// API methods
export const api = {
  // Projects
  projects: {
    list: (params?: any) => apiClient.get('/projects', { params }),
    create: (data: any) => apiClient.post('/projects', data),
    get: (id: string) => apiClient.get(`/projects/${id}`),
    update: (id: string, data: any) => apiClient.put(`/projects/${id}`, data),
    delete: (id: string) => apiClient.delete(`/projects/${id}`),
  },
  
  // Tasks
  tasks: {
    list: (params?: any) => apiClient.get('/tasks', { params }),
    create: (data: any) => apiClient.post('/tasks', data),
    get: (id: string) => apiClient.get(`/tasks/${id}`),
    update: (id: string, data: any) => apiClient.put(`/tasks/${id}`, data),
    delete: (id: string) => apiClient.delete(`/tasks/${id}`),
  },
  
  // Users
  users: {
    list: (params?: any) => apiClient.get('/users', { params }),
    create: (data: any) => apiClient.post('/users', data),
    get: (id: string) => apiClient.get(`/users/${id}`),
    update: (id: string, data: any) => apiClient.put(`/users/${id}`, data),
    delete: (id: string) => apiClient.delete(`/users/${id}`),
  },
  
  // Auth
  auth: {
    login: (credentials: any) => apiClient.post('/login', credentials),
    logout: () => apiClient.post('/logout'),
    me: () => apiClient.get('/me'),
  },
};

export default apiClient;
