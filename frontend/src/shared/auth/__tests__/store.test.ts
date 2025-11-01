import { describe, it, expect, beforeEach, vi } from 'vitest';
import type { Mock } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useAuthStore } from '../store';
import { apiClient } from '../../api/client';
import { createTestUser } from '../../../../tests/factories/user';

// Mock the API client
vi.mock('../../api/client', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    defaults: {
      headers: {
        common: {} as Record<string, string>,
      },
    },
  },
}));

type ApiClientMock = typeof apiClient & {
  get: Mock;
  post: Mock;
};

const mockedApiClient = apiClient as ApiClientMock;

// Mock localStorage
const localStorageMock = (() => {
  let store: Record<string, string> = {};

  return {
    getItem: vi.fn((key: string) => store[key] || null),
    setItem: vi.fn((key: string, value: string) => {
      store[key] = value.toString();
    }),
    removeItem: vi.fn((key: string) => {
      delete store[key];
    }),
    clear: vi.fn(() => {
      store = {};
    }),
  };
})();

Object.defineProperty(window, 'localStorage', {
  value: localStorageMock,
});

describe('Auth Store', () => {
  beforeEach(() => {
    // Clear all mocks and localStorage
    vi.clearAllMocks();
    localStorageMock.clear();
    useAuthStore.getState().clearAuth();
  });

  describe('Initial State', () => {
    it('should have correct initial state', () => {
      const { result } = renderHook(() => useAuthStore());
      
      expect(result.current.user).toBeNull();
      expect(result.current.token).toBeNull();
      expect(result.current.isAuthenticated).toBe(false);
      expect(result.current.isLoading).toBe(false);
      expect(result.current.error).toBeNull();
    });
  });

  describe('Login Flow', () => {
    it('should handle successful login', async () => {
      const mockUser = createTestUser();
      const mockToken = 'mock-token';

      const mockResponse = {
        data: {
          user: mockUser,
          token: mockToken,
        },
      };

      mockedApiClient.get.mockResolvedValueOnce({});
      mockedApiClient.post.mockResolvedValueOnce(mockResponse);

      const { result } = renderHook(() => useAuthStore());

      await act(async () => {
        await result.current.login('test@example.com', 'password');
      });

      expect(result.current.user).toEqual(
        expect.objectContaining({
          id: mockUser.id,
          name: mockUser.name,
          permissions: mockUser.permissions,
        })
      );
      expect(result.current.token).toBe(mockToken);
      expect(result.current.isAuthenticated).toBe(true);
      expect(result.current.isLoading).toBe(false);
      expect(result.current.error).toBeNull();
      expect(localStorageMock.setItem).toHaveBeenCalledWith('auth_token', mockToken);
    });

    it('should handle login failure', async () => {
      const mockError = {
        response: {
          data: {
            message: 'Invalid credentials',
          },
        },
      };

      mockedApiClient.get.mockResolvedValueOnce({});
      mockedApiClient.post.mockRejectedValueOnce(mockError);

      const { result } = renderHook(() => useAuthStore());

      await act(async () => {
        try {
          await result.current.login('test@example.com', 'wrong-password');
        } catch (error) {
          // Expected to throw
        }
      });

      expect(result.current.user).toBeNull();
      expect(result.current.token).toBeNull();
      expect(result.current.isAuthenticated).toBe(false);
      expect(result.current.isLoading).toBe(false);
      expect(result.current.error).toBe('Invalid credentials');
    });

    it('should set loading state during login', async () => {
      mockedApiClient.get.mockImplementation(() => new Promise(() => {}));
      mockedApiClient.post.mockImplementation(() => new Promise(() => {}));

      const { result } = renderHook(() => useAuthStore());

      act(() => {
        result.current.login('test@example.com', 'password');
      });

      expect(result.current.isLoading).toBe(true);
    });
  });

  describe('Logout Flow', () => {
    it('should clear auth state on logout', () => {
      const { result } = renderHook(() => useAuthStore());

      // Set initial authenticated state
      const user = createTestUser();
      act(() => {
        result.current.setToken('mock-token');
        result.current.setUser(user);
      });

      expect(result.current.isAuthenticated).toBe(true);

      act(() => {
        result.current.logout();
      });

      expect(result.current.user).toBeNull();
      expect(result.current.token).toBeNull();
      expect(result.current.isAuthenticated).toBe(false);
      expect(localStorageMock.removeItem).toHaveBeenCalledWith('auth_token');
    });
  });

  describe('Token Management', () => {
    it('should set token and update headers', () => {
      const { result } = renderHook(() => useAuthStore());
      const mockToken = 'mock-token';

      act(() => {
        result.current.setToken(mockToken);
      });

      expect(result.current.token).toBe(mockToken);
      expect(result.current.isAuthenticated).toBe(true);
      expect(localStorageMock.setItem).toHaveBeenCalledWith('auth_token', mockToken);
    });
  });

  describe('User Management', () => {
    it('should set user data', () => {
      const { result } = renderHook(() => useAuthStore());
      const mockUser = createTestUser();

      act(() => {
        result.current.setUser(mockUser);
      });

      expect(result.current.user).toEqual(expect.objectContaining({ id: mockUser.id }));
    });
  });

  describe('Error Handling', () => {
    it('should set and clear errors', () => {
      const { result } = renderHook(() => useAuthStore());

      act(() => {
        result.current.setError('Test error');
      });

      expect(result.current.error).toBe('Test error');

      act(() => {
        result.current.setError(null);
      });

      expect(result.current.error).toBeNull();
    });
  });

  describe('Clear Auth', () => {
    it('should clear all auth state', () => {
      const { result } = renderHook(() => useAuthStore());

      // Set some state
      act(() => {
        result.current.setToken('mock-token');
        result.current.setUser(createTestUser());
        result.current.setError('Test error');
        result.current.setLoading(true);
      });

      act(() => {
        result.current.clearAuth();
      });

      expect(result.current.user).toBeNull();
      expect(result.current.token).toBeNull();
      expect(result.current.isAuthenticated).toBe(false);
      expect(result.current.error).toBeNull();
      expect(result.current.isLoading).toBe(false);
      expect(localStorageMock.removeItem).toHaveBeenCalledWith('auth_token');
    });
  });
});
