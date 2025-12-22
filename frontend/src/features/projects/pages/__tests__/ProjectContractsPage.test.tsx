import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { BrowserRouter } from 'react-router-dom';
import { ProjectContractsPage } from '../ProjectContractsPage';
import { useProjectContracts, useProject } from '../../hooks';
import type { ContractSummary } from '../../api';

// Mock the hooks
vi.mock('../../hooks', () => ({
  useProjectContracts: vi.fn(),
  useProject: vi.fn(),
}));

const mockUseProjectContracts = vi.mocked(useProjectContracts);
const mockUseProject = vi.mocked(useProject);

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        cacheTime: 0,
        staleTime: 0,
      },
    },
  });

  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        {children}
      </BrowserRouter>
    </QueryClientProvider>
  );
};

const mockContracts: ContractSummary[] = [
  {
    id: 'contract-1',
    project_id: 'project-123',
    code: 'CT-001',
    name: 'Main Contract',
    type: 'construction',
    party_name: 'ABC Construction',
    currency: 'VND',
    base_amount: 1000000,
    current_amount: 1050000,
    total_certified_amount: 500000,
    total_paid_amount: 450000,
    outstanding_amount: 50000,
    status: 'active',
    start_date: '2024-01-01',
    end_date: '2024-12-31',
    signed_at: '2024-01-01T00:00:00Z',
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z',
  },
];

describe('ProjectContractsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Mock useParams
    vi.mock('react-router-dom', async () => {
      const actual = await vi.importActual('react-router-dom');
      return {
        ...actual,
        useParams: () => ({ id: 'project-123' }),
      };
    });
  });

  it('renders loading state', () => {
    mockUseProjectContracts.mockReturnValue({
      data: undefined,
      isLoading: true,
      error: null,
    } as any);

    mockUseProject.mockReturnValue({
      data: undefined,
      isLoading: true,
      error: null,
    } as any);

    render(<ProjectContractsPage />, {
      wrapper: createWrapper(),
    });

    expect(screen.getByText(/Loading contracts/i)).toBeInTheDocument();
  });

  it('renders contracts list', async () => {
    mockUseProjectContracts.mockReturnValue({
      data: { data: mockContracts },
      isLoading: false,
      error: null,
    } as any);

    mockUseProject.mockReturnValue({
      data: { data: { id: 'project-123', name: 'Test Project' } },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectContractsPage />, {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(screen.getByText('Contracts')).toBeInTheDocument();
      expect(screen.getByText('CT-001')).toBeInTheDocument();
      expect(screen.getByText('Main Contract')).toBeInTheDocument();
      expect(screen.getByText('ABC Construction')).toBeInTheDocument();
    });
  });

  it('renders empty state when no contracts', () => {
    mockUseProjectContracts.mockReturnValue({
      data: { data: [] },
      isLoading: false,
      error: null,
    } as any);

    mockUseProject.mockReturnValue({
      data: { data: { id: 'project-123', name: 'Test Project' } },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectContractsPage />, {
      wrapper: createWrapper(),
    });

    expect(screen.getByText(/No contracts found/i)).toBeInTheDocument();
  });

  it('renders error state', () => {
    mockUseProjectContracts.mockReturnValue({
      data: undefined,
      isLoading: false,
      error: new Error('Failed to load contracts'),
    } as any);

    mockUseProject.mockReturnValue({
      data: { data: { id: 'project-123', name: 'Test Project' } },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectContractsPage />, {
      wrapper: createWrapper(),
    });

    expect(screen.getByText(/Error loading contracts/i)).toBeInTheDocument();
    expect(screen.getByText(/Retry/i)).toBeInTheDocument();
  });
});
