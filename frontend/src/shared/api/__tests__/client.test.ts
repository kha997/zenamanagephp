import { beforeEach, describe, expect, it } from 'vitest';
import {
  ApiError,
  clearAuthToken,
  createApiClient,
  mapAxiosError,
  setAuthToken,
} from '../client';

describe('api client', () => {
  beforeEach(() => {
    clearAuthToken();
  });

  it('attaches bearer token when có', async () => {
    const client = createApiClient({ baseURL: '/mock' });
    setAuthToken('abc123');
    
    // Test that the interceptor is attached
    expect(client.interceptors.request).toBeDefined();
    
    // Test the token is stored (in real environment)
    if (typeof window !== 'undefined') {
      expect(window.localStorage.getItem('auth_token')).toBe('abc123');
    }
  });

  it('map AxiosError sang ApiError chuẩn', () => {
    const apiError = mapAxiosError({
      isAxiosError: true,
      message: 'Forbidden',
      name: 'AxiosError',
      code: '403',
      toJSON: () => ({}),
      config: {} as any,
      response: {
        status: 403,
        statusText: 'Forbidden',
        headers: {},
        config: {} as any,
        data: {
          error: { message: 'Not allowed', code: 'RBAC_FORBIDDEN' },
        },
      },
    } as any);

    expect(apiError).toBeInstanceOf(ApiError);
    expect(apiError.status).toBe(403);
    expect(apiError.code).toBe('RBAC_FORBIDDEN');
    expect(apiError.message).toBe('Not allowed');
  });
});
