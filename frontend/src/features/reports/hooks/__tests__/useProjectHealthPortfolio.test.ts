import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useQuery } from '@tanstack/react-query';
import { useProjectHealthPortfolio } from '../../hooks';
import { useAuthStore } from '../../../auth/store';

// Mock dependencies
vi.mock('@tanstack/react-query', () => ({
  useQuery: vi.fn(),
}));

vi.mock('../../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

const mockUseQuery = vi.mocked(useQuery);
const mockUseAuthStore = vi.mocked(useAuthStore);

describe('useProjectHealthPortfolio', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should enable query by default when tenantId exists', () => {
    mockUseAuthStore.mockReturnValue({
      user: { tenant_id: 'tenant-123' },
      selectedTenantId: null,
    } as any);
    
    mockUseQuery.mockReturnValue({
      data: [],
      isLoading: false,
      isError: false,
      error: null,
    } as any);

    useProjectHealthPortfolio();

    expect(mockUseQuery).toHaveBeenCalledWith(
      expect.objectContaining({
        enabled: true,
      })
    );
  });

  it('should disable query when enabled option is false', () => {
    mockUseAuthStore.mockReturnValue({
      user: { tenant_id: 'tenant-123' },
      selectedTenantId: null,
    } as any);
    
    mockUseQuery.mockReturnValue({
      data: [],
      isLoading: false,
      isError: false,
      error: null,
    } as any);

    useProjectHealthPortfolio({ enabled: false });

    expect(mockUseQuery).toHaveBeenCalledWith(
      expect.objectContaining({
        enabled: false,
      })
    );
  });

  it('should enable query when enabled option is true', () => {
    mockUseAuthStore.mockReturnValue({
      user: { tenant_id: 'tenant-123' },
      selectedTenantId: null,
    } as any);
    
    mockUseQuery.mockReturnValue({
      data: [],
      isLoading: false,
      isError: false,
      error: null,
    } as any);

    useProjectHealthPortfolio({ enabled: true });

    expect(mockUseQuery).toHaveBeenCalledWith(
      expect.objectContaining({
        enabled: true,
      })
    );
  });

  it('should disable query when tenantId is null', () => {
    mockUseAuthStore.mockReturnValue({
      user: null,
      selectedTenantId: null,
    } as any);
    
    mockUseQuery.mockReturnValue({
      data: [],
      isLoading: false,
      isError: false,
      error: null,
    } as any);

    useProjectHealthPortfolio();

    expect(mockUseQuery).toHaveBeenCalledWith(
      expect.objectContaining({
        enabled: false,
      })
    );
  });

  it('should disable query when tenantId is null even if enabled is true', () => {
    mockUseAuthStore.mockReturnValue({
      user: null,
      selectedTenantId: null,
    } as any);
    
    mockUseQuery.mockReturnValue({
      data: [],
      isLoading: false,
      isError: false,
      error: null,
    } as any);

    useProjectHealthPortfolio({ enabled: true });

    expect(mockUseQuery).toHaveBeenCalledWith(
      expect.objectContaining({
        enabled: false,
      })
    );
  });
});

