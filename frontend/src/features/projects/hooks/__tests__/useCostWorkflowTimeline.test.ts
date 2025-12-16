import { describe, it, expect, beforeEach, vi } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import React from 'react';
import {
  useChangeOrderWorkflowTimeline,
  useCertificateWorkflowTimeline,
  usePaymentWorkflowTimeline,
} from '../../hooks';
import { projectsApi } from '../../api';

// Mock the API
vi.mock('../../api', () => ({
  projectsApi: {
    getProjectHistory: vi.fn(),
  },
}));

const mockProjectsApi = vi.mocked(projectsApi);

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
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  );
};

describe('useChangeOrderWorkflowTimeline', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fetches change order workflow timeline', async () => {
    const mockResponse = {
      success: true,
      data: [
        {
          id: '1',
          action: 'change_order_proposed',
          action_label: 'Change Order Proposed',
          entity_type: 'ChangeOrder',
          entity_id: 'co-123',
          created_at: '2024-01-15T10:00:00Z',
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          metadata: { status_before: 'draft', status_after: 'proposed' },
        },
      ],
    };

    mockProjectsApi.getProjectHistory.mockResolvedValue(mockResponse);

    const { result } = renderHook(
      () => useChangeOrderWorkflowTimeline('project-1', 'contract-1', 'co-123'),
      { wrapper: createWrapper() }
    );

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mockProjectsApi.getProjectHistory).toHaveBeenCalledWith('project-1', {
      entity_type: 'ChangeOrder',
      entity_id: 'co-123',
      limit: 50,
    });

    expect(result.current.data).toEqual(mockResponse.data);
  });

  it('does not fetch when projectId is missing', () => {
    const { result } = renderHook(
      () => useChangeOrderWorkflowTimeline('', 'contract-1', 'co-123'),
      { wrapper: createWrapper() }
    );

    expect(result.current.isFetching).toBe(false);
    expect(mockProjectsApi.getProjectHistory).not.toHaveBeenCalled();
  });

  it('handles API errors gracefully', async () => {
    mockProjectsApi.getProjectHistory.mockRejectedValue(new Error('API Error'));

    const { result } = renderHook(
      () => useChangeOrderWorkflowTimeline('project-1', 'contract-1', 'co-123'),
      { wrapper: createWrapper() }
    );

    await waitFor(() => expect(result.current.isError).toBe(true));
  });
});

describe('useCertificateWorkflowTimeline', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fetches certificate workflow timeline', async () => {
    const mockResponse = {
      success: true,
      data: [
        {
          id: '1',
          action: 'certificate_submitted',
          action_label: 'Certificate Submitted',
          entity_type: 'ContractPaymentCertificate',
          entity_id: 'cert-123',
          created_at: '2024-01-15T10:00:00Z',
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
        },
      ],
    };

    mockProjectsApi.getProjectHistory.mockResolvedValue(mockResponse);

    const { result } = renderHook(
      () => useCertificateWorkflowTimeline('project-1', 'contract-1', 'cert-123'),
      { wrapper: createWrapper() }
    );

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mockProjectsApi.getProjectHistory).toHaveBeenCalledWith('project-1', {
      entity_type: 'ContractPaymentCertificate',
      entity_id: 'cert-123',
      limit: 50,
    });

    expect(result.current.data).toEqual(mockResponse.data);
  });
});

describe('usePaymentWorkflowTimeline', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('fetches payment workflow timeline', async () => {
    const mockResponse = {
      success: true,
      data: [
        {
          id: '1',
          action: 'payment_marked_paid',
          action_label: 'Payment Marked Paid',
          entity_type: 'ContractActualPayment',
          entity_id: 'payment-123',
          created_at: '2024-01-15T10:00:00Z',
          user: { id: '1', name: 'John Doe', email: 'john@example.com' },
          metadata: { amount: 100000 },
        },
      ],
    };

    mockProjectsApi.getProjectHistory.mockResolvedValue(mockResponse);

    const { result } = renderHook(
      () => usePaymentWorkflowTimeline('project-1', 'contract-1', 'payment-123'),
      { wrapper: createWrapper() }
    );

    await waitFor(() => expect(result.current.isSuccess).toBe(true));

    expect(mockProjectsApi.getProjectHistory).toHaveBeenCalledWith('project-1', {
      entity_type: 'ContractActualPayment',
      entity_id: 'payment-123',
      limit: 50,
    });

    expect(result.current.data).toEqual(mockResponse.data);
  });
});
