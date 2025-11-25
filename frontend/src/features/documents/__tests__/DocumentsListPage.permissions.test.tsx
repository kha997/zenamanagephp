import React from 'react';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { DocumentsListPage } from '../pages/DocumentsListPage';
import { useAuthStore } from '../../auth/store';
import { useDocuments, useDocumentsKpis, useDocumentsAlerts, useDocumentsActivity } from '../hooks';
import { documentsApi } from '../api';

// Mock the auth store
vi.mock('../../auth/store', () => ({
  useAuthStore: vi.fn(),
}));

// Mock the documents hooks
vi.mock('../hooks', () => ({
  useDocuments: vi.fn(),
  useDocumentsKpis: vi.fn(),
  useDocumentsAlerts: vi.fn(),
  useDocumentsActivity: vi.fn(),
  useUploadDocument: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
  useDeleteDocument: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
  useDownloadDocument: vi.fn(() => ({ mutateAsync: vi.fn(), isPending: false })),
}));

// Mock react-router-dom
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => vi.fn(),
  };
});

// Mock react-hot-toast
vi.mock('react-hot-toast', () => ({
  default: {
    success: vi.fn(),
    error: vi.fn(),
  },
}));

const mockUseAuthStore = vi.mocked(useAuthStore);
const mockUseDocuments = vi.mocked(useDocuments);
const mockUseDocumentsKpis = vi.mocked(useDocumentsKpis);
const mockUseDocumentsAlerts = vi.mocked(useDocumentsAlerts);
const mockUseDocumentsActivity = vi.mocked(useDocumentsActivity);

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });

  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>{children}</BrowserRouter>
    </QueryClientProvider>
  );
};

describe('DocumentsListPage - Permissions', () => {
  beforeEach(() => {
    vi.clearAllMocks();

    // Default mock implementations
    mockUseDocuments.mockReturnValue({
      data: { data: [], meta: { total: 0, current_page: 1, per_page: 12, last_page: 1 } },
      isLoading: false,
      error: null,
    } as any);

    mockUseDocumentsKpis.mockReturnValue({
      data: { total_documents: 0, recent_uploads: 0, storage_used: 0 },
      isLoading: false,
    } as any);

    mockUseDocumentsAlerts.mockReturnValue({
      data: [],
      isLoading: false,
      error: null,
    } as any);

    mockUseDocumentsActivity.mockReturnValue({
      data: [],
      isLoading: false,
      error: null,
    } as any);
  });

  describe('Access Restricted', () => {
    it('should show Access Restricted message when user has no view permission', () => {
      mockUseAuthStore.mockReturnValue({
        user: { id: '1', name: 'Test User' },
        hasTenantPermission: () => false,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <DocumentsListPage />
        </Wrapper>
      );

      expect(screen.getByTestId('access-restricted')).toBeInTheDocument();
      expect(screen.getByText('Access Restricted')).toBeInTheDocument();
      expect(screen.queryByText('Upload Document')).not.toBeInTheDocument();
    });
  });

  describe('Read-only mode', () => {
    it('should show Read-only badge and hide upload button when user has view but not manage permission', async () => {
      mockUseAuthStore.mockReturnValue({
        user: { id: '1', name: 'Test User' },
        hasTenantPermission: (permission: string) => {
          return permission === 'tenant.view_documents';
        },
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <DocumentsListPage />
        </Wrapper>
      );

      await waitFor(() => {
        expect(screen.getByText('Read-only mode')).toBeInTheDocument();
        expect(screen.queryByText('Upload Document')).not.toBeInTheDocument();
      });
    });

    it('should not show Edit or Delete buttons in read-only mode', async () => {
      const mockDocuments = [
        {
          id: 1,
          name: 'Test Document.pdf',
          filename: 'Test Document.pdf',
          size: 1024,
          mime_type: 'application/pdf',
          uploaded_by_name: 'Test User',
        },
      ];

      mockUseAuthStore.mockReturnValue({
        user: { id: '1', name: 'Test User' },
        hasTenantPermission: (permission: string) => {
          return permission === 'tenant.view_documents';
        },
      } as any);

      mockUseDocuments.mockReturnValue({
        data: { data: mockDocuments, meta: { total: 1, current_page: 1, per_page: 12, last_page: 1 } },
        isLoading: false,
        error: null,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <DocumentsListPage />
        </Wrapper>
      );

      await waitFor(() => {
        expect(screen.getByText('Test Document.pdf')).toBeInTheDocument();
        // Check that Edit and Delete buttons are not present
        const editButtons = screen.queryAllByLabelText(/Edit/i);
        const deleteButtons = screen.queryAllByLabelText(/Delete/i);
        expect(editButtons.length).toBe(0);
        expect(deleteButtons.length).toBe(0);
      });
    });
  });

  describe('Full access', () => {
    it('should show Upload button and action buttons when user has manage permission', async () => {
      const mockDocuments = [
        {
          id: 1,
          name: 'Test Document.pdf',
          filename: 'Test Document.pdf',
          size: 1024,
          mime_type: 'application/pdf',
          uploaded_by_name: 'Test User',
        },
      ];

      mockUseAuthStore.mockReturnValue({
        user: { id: '1', name: 'Test User' },
        hasTenantPermission: (permission: string) => {
          return ['tenant.view_documents', 'tenant.manage_documents'].includes(permission);
        },
      } as any);

      mockUseDocuments.mockReturnValue({
        data: { data: mockDocuments, meta: { total: 1, current_page: 1, per_page: 12, last_page: 1 } },
        isLoading: false,
        error: null,
      } as any);

      const Wrapper = createWrapper();
      render(
        <Wrapper>
          <DocumentsListPage />
        </Wrapper>
      );

      await waitFor(() => {
        expect(screen.getByText('Upload Document')).toBeInTheDocument();
        expect(screen.queryByText('Read-only mode')).not.toBeInTheDocument();
        expect(screen.queryByTestId('access-restricted')).not.toBeInTheDocument();
      });

      // Check that action buttons are present (view/download buttons should be visible)
      const viewButtons = screen.queryAllByLabelText(/View/i);
      expect(viewButtons.length).toBeGreaterThan(0);
    });
  });
});

