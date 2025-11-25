import axios, {
  AxiosError,
  AxiosInstance,
  AxiosRequestConfig,
  InternalAxiosRequestConfig,
} from 'axios';
import type { ColorMode } from '../tokens';

declare global {
  interface Window {
    Laravel?: {
      csrfToken?: string;
      locale?: string;
      tenant?: {
        id?: string;
      };
    };
  }
}

export interface ApiClientOptions extends Pick<AxiosRequestConfig, 'baseURL' | 'timeout'> {
  headers?: Record<string, string>;
}

export class ApiError extends Error {
  public status: number;
  public code?: string;
  public details?: unknown;

  constructor(message: string, status: number, code?: string, details?: unknown) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

const readLocale = () => window.Laravel?.locale ?? document.documentElement.lang ?? 'en';

const readTenantId = () => window.Laravel?.tenant?.id;

const readCsrfToken = () =>
  window.Laravel?.csrfToken ??
  document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ??
  undefined;

const requestId = () =>
  typeof crypto !== 'undefined' && 'randomUUID' in crypto ? crypto.randomUUID() : `req_${Date.now()}`;

let authToken: string | null = null;

if (typeof window !== 'undefined') {
  const stored = window.localStorage.getItem('auth_token');
  if (stored) {
    authToken = stored;
  }
}

const attachAuthHeader = (config: InternalAxiosRequestConfig) => {
  if (!config.headers) {
    config.headers = {} as any;
  }

  // Always check localStorage for latest token
  if (typeof window !== 'undefined') {
    const token = window.localStorage.getItem('auth_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
  } else if (authToken) {
    config.headers.Authorization = `Bearer ${authToken}`;
  }

  const tenantId = readTenantId();
  if (tenantId) {
    config.headers['X-Tenant-ID'] = tenantId;
  }

  const csrf = readCsrfToken();
  if (csrf) {
    config.headers['X-CSRF-TOKEN'] = csrf;
  }

  config.headers['X-Request-ID'] = requestId();
  config.headers['Accept-Language'] = readLocale();
  config.headers['X-Frontend-Theme'] =
    (document.documentElement.dataset.theme as ColorMode | undefined) ?? 'light';

  return config;
};

export const mapAxiosError = (error: AxiosError | Error): ApiError => {
  if ((error as AxiosError).isAxiosError) {
    const axiosError = error as AxiosError<{
      message?: string;
      error?: { message?: string; code?: string };
      errors?: Record<string, string[]>;
    }>;

    const status = axiosError.response?.status ?? 500;
    const payload = axiosError.response?.data;
    const message =
      payload?.message ??
      payload?.error?.message ??
      axiosError.message ??
      'Đã xảy ra lỗi trong quá trình gọi API';
    const code = payload?.error?.code ?? axiosError.code ?? undefined;
    const details = payload?.errors ?? payload;

    return new ApiError(message, status, code, details);
  }

  return new ApiError(error.message || 'Lỗi không xác định', 500);
};

const DEFAULT_API_BASE_URL = '/api/v1'; // Use relative URL for Vite proxy

// Retry configuration for rate limiting and maintenance
const MAX_RETRIES = 3;
const RETRY_DELAY = 1000; // 1 second

const shouldRetry = (error: AxiosError, retryCount: number): boolean => {
  if (retryCount >= MAX_RETRIES) return false;
  
  const status = error.response?.status;
  // Retry on 429 (Too Many Requests) and 503 (Service Unavailable)
  if (status === 429 || status === 503) {
    return true;
  }
  
  // Retry on network errors
  if (!error.response) {
    return true;
  }
  
  return false;
};

const getRetryDelay = (error: AxiosError, retryCount: number): number => {
  const retryAfter = error.response?.headers['retry-after'];
  if (retryAfter) {
    return parseInt(retryAfter, 10) * 1000;
  }
  // Exponential backoff
  return RETRY_DELAY * Math.pow(2, retryCount);
};

export const createApiClient = (options: ApiClientOptions = {}): AxiosInstance => {
  const instance = axios.create({
    baseURL: options.baseURL ?? DEFAULT_API_BASE_URL,
    timeout: options.timeout ?? 12000,
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(options.headers ?? {}),
    },
    withCredentials: true,
  });

  instance.interceptors.request.use(attachAuthHeader);
  
  // Response interceptor with retry logic
  instance.interceptors.response.use(
    (response) => response,
    async (error: AxiosError) => {
      const config = error.config as InternalAxiosRequestConfig & { __retryCount?: number };
      
      if (!config) {
        return Promise.reject(mapAxiosError(error));
      }
      
      config.__retryCount = config.__retryCount ?? 0;
      
      if (shouldRetry(error, config.__retryCount)) {
        config.__retryCount += 1;
        const delay = getRetryDelay(error, config.__retryCount);
        
        await new Promise((resolve) => setTimeout(resolve, delay));
        
        return instance(config);
      }
      
      return Promise.reject(mapAxiosError(error));
    },
  );

  return instance;
};

export const apiClient = createApiClient();

export const setAuthToken = (token: string | null) => {
  authToken = token;
  if (typeof window !== 'undefined') {
    if (token) {
      window.localStorage.setItem('auth_token', token);
    } else {
      window.localStorage.removeItem('auth_token');
    }
  }
};

export const clearAuthToken = () => setAuthToken(null);

export const http = {
  get: async <T = unknown>(url: string, config?: AxiosRequestConfig) =>
    apiClient.get<T>(url, config).then((response) => response.data),
  post: async <T = unknown>(url: string, data?: unknown, config?: AxiosRequestConfig) =>
    apiClient.post<T>(url, data, config).then((response) => response.data),
  put: async <T = unknown>(url: string, data?: unknown, config?: AxiosRequestConfig) =>
    apiClient.put<T>(url, data, config).then((response) => response.data),
  patch: async <T = unknown>(url: string, data?: unknown, config?: AxiosRequestConfig) =>
    apiClient.patch<T>(url, data, config).then((response) => response.data),
  delete: async <T = unknown>(url: string, config?: AxiosRequestConfig) =>
    apiClient.delete<T>(url, config).then((response) => response.data),
};
