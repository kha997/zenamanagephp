import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { DocumentVersionsModal } from '../DocumentVersionsModal';
import { useDocumentVersions, useUploadDocumentVersion } from '../../hooks';
import { projectsApi } from '../../api';

// Mock the hooks
vi.mock('../../hooks', () => ({
  useDocumentVersions: vi.fn(),
  useUploadDocumentVersion: vi.fn(),
}));

// Mock the API
vi.mock('../../api', () => ({
  projectsApi: {
    downloadDocumentVersion: vi.fn(),
  },
}));

const mockUseDocumentVersions = vi.mocked(useDocumentVersions);
const mockUseUploadDocumentVersion = vi.mocked(useUploadDocumentVersion);
const mockDownloadDocumentVersion = vi.mocked(projectsApi.downloadDocumentVersion);

// Store QueryClient instances for cleanup
const queryClients: QueryClient[] = [];

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

  queryClients.push(queryClient);

  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>{children}</BrowserRouter>
    </QueryClientProvider>
  );
};

describe('DocumentVersionsModal', () => {
  const mockVersions = [
    {
      id: 'v1',
      version_number: 1,
      name: 'version-1.pdf',
      original_name: 'version-1.pdf',
      file_size: 100000,
      uploaded_by: { id: '1', name: 'John Doe', email: 'john@example.com' },
      created_at: '2025-01-15T10:00:00Z',
    },
    {
      id: 'v2',
      version_number: 2,
      name: 'version-2.pdf',
      original_name: 'version-2.pdf',
      file_size: 120000,
      uploaded_by: { id: '1', name: 'John Doe', email: 'john@example.com' },
      created_at: '2025-01-16T10:00:00Z',
    },
  ];

  const mockUploadMutation = {
    mutateAsync: vi.fn(),
    isPending: false,
  };

  beforeEach(() => {
    vi.clearAllMocks();
    mockUseDocumentVersions.mockReturnValue({
      data: { success: true, data: mockVersions },
      isLoading: false,
      error: null,
    } as any);
    mockUseUploadDocumentVersion.mockReturnValue(mockUploadMutation as any);
    mockDownloadDocumentVersion.mockResolvedValue(undefined);
  });

  afterEach(() => {
    queryClients.forEach((client) => {
      client.clear();
      client.removeQueries();
    });
    queryClients.length = 0;
    vi.clearAllMocks();
  });

  it('renders upload new version button', () => {
    render(
      <DocumentVersionsModal
        isOpen={true}
        onClose={vi.fn()}
        projectId="123"
        documentId="1"
      />,
      { wrapper: createWrapper() }
    );

    expect(screen.getByTestId('open-upload-version-modal-button')).toBeInTheDocument();
    expect(screen.getByText('Upload New Version')).toBeInTheDocument();
  });

  it('opens upload version modal when button is clicked', async () => {
    render(
      <DocumentVersionsModal
        isOpen={true}
        onClose={vi.fn()}
        projectId="123"
        documentId="1"
      />,
      { wrapper: createWrapper() }
    );

    const uploadButton = screen.getByTestId('open-upload-version-modal-button');
    fireEvent.click(uploadButton);

    await waitFor(() => {
      expect(screen.getByTestId('upload-document-version-modal')).toBeInTheDocument();
    });
  });

  it('validates file is required before submitting', async () => {
    render(
      <DocumentVersionsModal
        isOpen={true}
        onClose={vi.fn()}
        projectId="123"
        documentId="1"
      />,
      { wrapper: createWrapper() }
    );

    // Open upload modal
    const uploadButton = screen.getByTestId('open-upload-version-modal-button');
    fireEvent.click(uploadButton);

    await waitFor(() => {
      expect(screen.getByTestId('upload-document-version-modal')).toBeInTheDocument();
    });

    // Try to submit without file
    const submitButton = screen.getByText('Upload');
    fireEvent.click(submitButton);

    // Should show error
    await waitFor(() => {
      expect(screen.getByTestId('upload-version-error-message')).toBeInTheDocument();
      expect(screen.getByText(/Please select a file/i)).toBeInTheDocument();
    });

    // Mutation should not be called
    expect(mockUploadMutation.mutateAsync).not.toHaveBeenCalled();
  });

  it('calls mutation with correct params when form is submitted', async () => {
    mockUploadMutation.mutateAsync.mockResolvedValue({
      success: true,
      data: { id: '1', name: 'test.pdf' },
    });

    render(
      <DocumentVersionsModal
        isOpen={true}
        onClose={vi.fn()}
        projectId="123"
        documentId="1"
      />,
      { wrapper: createWrapper() }
    );

    // Open upload modal
    const uploadButton = screen.getByTestId('open-upload-version-modal-button');
    fireEvent.click(uploadButton);

    await waitFor(() => {
      expect(screen.getByTestId('upload-document-version-modal')).toBeInTheDocument();
    });

    // Create a mock file
    const file = new File(['test content'], 'test.pdf', { type: 'application/pdf' });
    const fileInput = screen.getByTestId('upload-version-file-input') as HTMLInputElement;
    
    // Simulate file selection
    Object.defineProperty(fileInput, 'files', {
      value: [file],
      writable: false,
    });
    fireEvent.change(fileInput);

    // Fill form fields
    const nameInput = screen.getByTestId('upload-version-name-input') as HTMLInputElement;
    fireEvent.change(nameInput, { target: { value: 'Test Document' } });

    const descriptionInput = screen.getByTestId('upload-version-description-input') as HTMLTextAreaElement;
    fireEvent.change(descriptionInput, { target: { value: 'Test description' } });

    // Submit form
    const submitButton = screen.getByText('Upload');
    fireEvent.click(submitButton);

    // Wait for mutation to be called
    await waitFor(() => {
      expect(mockUploadMutation.mutateAsync).toHaveBeenCalled();
    });

    // Check that mutation was called with correct arguments
    const callArgs = mockUploadMutation.mutateAsync.mock.calls[0][0];
    expect(callArgs.projectId).toBe('123');
    expect(callArgs.documentId).toBe('1');
    expect(callArgs.formData).toBeInstanceOf(FormData);
  });

  it('closes modal on successful upload', async () => {
    mockUploadMutation.mutateAsync.mockResolvedValue({
      success: true,
      data: { id: '1', name: 'test.pdf' },
    });

    render(
      <DocumentVersionsModal
        isOpen={true}
        onClose={vi.fn()}
        projectId="123"
        documentId="1"
      />,
      { wrapper: createWrapper() }
    );

    // Open upload modal
    const uploadButton = screen.getByTestId('open-upload-version-modal-button');
    fireEvent.click(uploadButton);

    await waitFor(() => {
      expect(screen.getByTestId('upload-document-version-modal')).toBeInTheDocument();
    });

    // Create a mock file
    const file = new File(['test content'], 'test.pdf', { type: 'application/pdf' });
    const fileInput = screen.getByTestId('upload-version-file-input') as HTMLInputElement;
    
    Object.defineProperty(fileInput, 'files', {
      value: [file],
      writable: false,
    });
    fireEvent.change(fileInput);

    // Submit form
    const submitButton = screen.getByText('Upload');
    fireEvent.click(submitButton);

    // Wait for modal to close
    await waitFor(() => {
      expect(screen.queryByTestId('upload-document-version-modal')).not.toBeInTheDocument();
    });
  });

  it('shows error message when upload fails', async () => {
    const error = new Error('Upload failed');
    mockUploadMutation.mutateAsync.mockRejectedValue(error);

    render(
      <DocumentVersionsModal
        isOpen={true}
        onClose={vi.fn()}
        projectId="123"
        documentId="1"
      />,
      { wrapper: createWrapper() }
    );

    // Open upload modal
    const uploadButton = screen.getByTestId('open-upload-version-modal-button');
    fireEvent.click(uploadButton);

    await waitFor(() => {
      expect(screen.getByTestId('upload-document-version-modal')).toBeInTheDocument();
    });

    // Create a mock file
    const file = new File(['test content'], 'test.pdf', { type: 'application/pdf' });
    const fileInput = screen.getByTestId('upload-version-file-input') as HTMLInputElement;
    
    Object.defineProperty(fileInput, 'files', {
      value: [file],
      writable: false,
    });
    fireEvent.change(fileInput);

    // Submit form
    const submitButton = screen.getByText('Upload');
    fireEvent.click(submitButton);

    // Wait for error to appear
    await waitFor(() => {
      expect(screen.getByTestId('upload-version-error-message')).toBeInTheDocument();
    });

    // Modal should still be open
    expect(screen.getByTestId('upload-document-version-modal')).toBeInTheDocument();
  });

  it('renders version list correctly', () => {
    render(
      <DocumentVersionsModal
        isOpen={true}
        onClose={vi.fn()}
        projectId="123"
        documentId="1"
      />,
      { wrapper: createWrapper() }
    );

    expect(screen.getByText('v1')).toBeInTheDocument();
    expect(screen.getByText('v2')).toBeInTheDocument();
    expect(screen.getByText('version-1.pdf')).toBeInTheDocument();
    expect(screen.getByText('version-2.pdf')).toBeInTheDocument();
  });

  it('handles download version correctly', async () => {
    render(
      <DocumentVersionsModal
        isOpen={true}
        onClose={vi.fn()}
        projectId="123"
        documentId="1"
      />,
      { wrapper: createWrapper() }
    );

    await waitFor(() => {
      expect(screen.getByTestId('download-version-button-v1')).toBeInTheDocument();
    });

    const downloadButton = screen.getByTestId('download-version-button-v1');
    fireEvent.click(downloadButton);

    await waitFor(() => {
      expect(mockDownloadDocumentVersion).toHaveBeenCalledWith('123', '1', 'v1');
    });
  });
});

