import React from 'react';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest';
import { useGlobalSearch } from '@/features/search/hooks/useGlobalSearch';
import { fetchGlobalSearch } from '@/api/searchApi';

vi.mock('@/api/searchApi', () => ({
  fetchGlobalSearch: vi.fn(),
}));

const createQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

const createWrapper = () => {
  const client = createQueryClient();
  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  );
};

describe('useGlobalSearch', () => {
  const mockFetch = vi.mocked(fetchGlobalSearch);

  beforeEach(() => {
    mockFetch.mockResolvedValue({
      pagination: { page: 1, per_page: 20, total: 0 },
      results: [],
    });
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  it('calls API with provided params when enabled', async () => {
    const wrapper = createWrapper();

    const { result } = renderHook(
      () =>
        useGlobalSearch(
          {
            q: 'alpha',
            modules: ['projects'],
            project_id: 'proj-1',
            page: 2,
            per_page: 15,
          },
          true,
        ),
      { wrapper },
    );

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mockFetch).toHaveBeenCalledWith({
      q: 'alpha',
      project_id: 'proj-1',
      modules: ['projects'],
      page: 2,
      per_page: 15,
    });
  });

  it('does not call API when disabled or query is empty', async () => {
    const wrapper = createWrapper();

    renderHook(
      () =>
        useGlobalSearch(
          {
            q: '',
            page: 1,
            per_page: 20,
          },
          true,
        ),
      { wrapper },
    );

    expect(mockFetch).not.toHaveBeenCalled();
  });
});
