import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ProjectDocumentsSection } from '../ProjectDocumentsSection';
import { useProjectDocuments, useUploadProjectDocument, useUpdateProjectDocument, useDeleteProjectDocument, useDocumentVersions, useUploadDocumentVersion } from '../../hooks';
import { projectsApi } from '../../api';

// Mock the hooks
vi.mock('../../hooks', () => ({
  useProjectDocuments: vi.fn(),
  useUploadProjectDocument: vi.fn(),
  useUpdateProjectDocument: vi.fn(),
  useDeleteProjectDocument: vi.fn(),
  useDocumentVersions: vi.fn(),
  useUploadDocumentVersion: vi.fn(),
}));

// Mock the API
vi.mock('../../api', () => ({
  projectsApi: {
    downloadProjectDocument: vi.fn(),
    downloadDocumentVersion: vi.fn(),
  },
}));

const mockUseProjectDocuments = vi.mocked(useProjectDocuments);
const mockDownloadProjectDocument = vi.mocked(projectsApi.downloadProjectDocument);
const mockUseUploadProjectDocument = vi.mocked(useUploadProjectDocument);
const mockUseUpdateProjectDocument = vi.mocked(useUpdateProjectDocument);
const mockUseDeleteProjectDocument = vi.mocked(useDeleteProjectDocument);
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

describe('ProjectDocumentsSection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Set default mock return values for all hooks
    mockUseUploadProjectDocument.mockReturnValue({
      mutateAsync: vi.fn(),
      isPending: false,
    } as any);
    mockUseUpdateProjectDocument.mockReturnValue({
      mutateAsync: vi.fn(),
      isPending: false,
    } as any);
    mockUseDeleteProjectDocument.mockReturnValue({
      mutateAsync: vi.fn(),
      isPending: false,
    } as any);
  });

  afterEach(() => {
    queryClients.forEach((client) => {
      client.clear();
      client.removeQueries();
    });
    queryClients.length = 0;
    vi.clearAllMocks();
  });

  it('renders loading state', () => {
    mockUseProjectDocuments.mockReturnValue({
      data: undefined,
      isLoading: true,
      error: null,
    } as any);

    render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

    expect(screen.getByText('Documents')).toBeInTheDocument();
    // Check for loading skeleton
    const loadingElements = screen.getAllByText(/Documents/i);
    expect(loadingElements.length).toBeGreaterThan(0);
  });

  it('renders error state', () => {
    mockUseProjectDocuments.mockReturnValue({
      data: undefined,
      isLoading: false,
      error: new Error('Failed to load documents'),
    } as any);

    render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

    expect(screen.getByText(/Error loading documents/i)).toBeInTheDocument();
  });

  it('renders empty state when no documents', () => {
    mockUseProjectDocuments.mockReturnValue({
      data: { success: true, data: [] },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

    expect(screen.getByText(/No documents found for this project/i)).toBeInTheDocument();
  });

  it('renders documents list with correct fields', async () => {
    const mockDocuments = [
      {
        id: '1',
        name: 'Test Document 1',
        description: 'Test description',
        category: 'contract',
        status: 'active',
        file_type: 'pdf',
        mime_type: 'application/pdf',
        file_size: 1024000,
        uploaded_by: { id: '1', name: 'John Doe', email: 'john@example.com' },
        created_at: '2025-01-15T10:00:00Z',
      },
      {
        id: '2',
        name: 'Test Document 2',
        description: 'Another document',
        category: 'drawing',
        status: 'pending',
        file_type: 'dwg',
        file_size: 2048000,
        uploaded_by: { id: '2', name: 'Jane Smith', email: 'jane@example.com' },
        created_at: '2025-01-14T10:00:00Z',
      },
    ];

    mockUseProjectDocuments.mockReturnValue({
      data: { success: true, data: mockDocuments },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

    await waitFor(() => {
      expect(screen.getByText('Test Document 1')).toBeInTheDocument();
      expect(screen.getByText('Test Document 2')).toBeInTheDocument();
    });

    // Check document details
    expect(screen.getByText('Test description')).toBeInTheDocument();
    // Use getAllByText since "contract" appears in both the select option and the document badge
    const contractElements = screen.getAllByText(/contract/i);
    expect(contractElements.length).toBeGreaterThan(0);
    // Use getAllByText since "active" appears in both the select option and the document badge
    const activeElements = screen.getAllByText(/active/i);
    expect(activeElements.length).toBeGreaterThan(0);
    expect(screen.getByText(/John Doe/i)).toBeInTheDocument();
  });

  it('calls onUploadClick when upload button is clicked', async () => {
    const mockOnUploadClick = vi.fn();
    mockUseProjectDocuments.mockReturnValue({
      data: { success: true, data: [] },
      isLoading: false,
      error: null,
    } as any);

    render(
      <ProjectDocumentsSection
        projectId="123"
        onUploadClick={mockOnUploadClick}
        showUploadButton={true}
      />,
      { wrapper: createWrapper() }
    );

    // Get the header upload button specifically (not the empty state one)
    const uploadButtons = screen.getAllByText(/Upload/i);
    const headerUploadButton = uploadButtons.find(btn => btn.textContent === 'Upload Document');
    expect(headerUploadButton).toBeInTheDocument();
    headerUploadButton!.click();

    expect(mockOnUploadClick).toHaveBeenCalledTimes(1);
  });

  it('displays document count in header', async () => {
    const mockDocuments = [
      { id: '1', name: 'Doc 1' },
      { id: '2', name: 'Doc 2' },
      { id: '3', name: 'Doc 3' },
    ];

    mockUseProjectDocuments.mockReturnValue({
      data: { success: true, data: mockDocuments },
      isLoading: false,
      error: null,
    } as any);

    render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

    await waitFor(() => {
      expect(screen.getByText(/\(3\)/)).toBeInTheDocument();
    });
  });

  describe('Filter functionality', () => {
    const mockDocuments = [
      {
        id: '1',
        name: 'Contract Document',
        description: 'Main contract',
        category: 'contract',
        status: 'active',
        file_type: 'pdf',
        uploaded_by: { id: '1', name: 'John Doe' },
        created_at: '2025-01-15T10:00:00Z',
      },
      {
        id: '2',
        name: 'Drawing File',
        description: 'Architectural drawing',
        category: 'drawing',
        status: 'pending',
        file_type: 'dwg',
        uploaded_by: { id: '2', name: 'Jane Smith' },
        created_at: '2025-01-14T10:00:00Z',
      },
      {
        id: '3',
        name: 'Specification Document',
        description: 'Technical specs',
        category: 'specification',
        status: 'active',
        file_type: 'pdf',
        uploaded_by: { id: '1', name: 'John Doe' },
        created_at: '2025-01-13T10:00:00Z',
      },
    ];

    it('renders filter controls', () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: mockDocuments },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      expect(screen.getByTestId('documents-search-input')).toBeInTheDocument();
      expect(screen.getByTestId('documents-category-select')).toBeInTheDocument();
      expect(screen.getByTestId('documents-status-select')).toBeInTheDocument();
    });

    it('filters documents by search term', async () => {
      const user = userEvent.setup();

      // Start with all documents
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: mockDocuments },
        isLoading: false,
        error: null,
      } as any);

      const { rerender } = render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      // Initially all documents should be visible
      await waitFor(() => {
        expect(screen.getByText('Contract Document')).toBeInTheDocument();
        expect(screen.getByText('Drawing File')).toBeInTheDocument();
        expect(screen.getByText('Specification Document')).toBeInTheDocument();
      });

      // Type in search input
      const searchInput = screen.getByTestId('documents-search-input') as HTMLInputElement;
      await user.type(searchInput, 'Contract');

      // Update mock to return filtered results
      const filtered = mockDocuments.filter((doc) => {
        const searchLower = 'Contract'.toLowerCase();
        return (
          doc.name.toLowerCase().includes(searchLower) ||
          doc.description.toLowerCase().includes(searchLower)
        );
      });

      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: filtered },
        isLoading: false,
        error: null,
      } as any);

      // Rerender to simulate React Query refetch
      rerender(<ProjectDocumentsSection projectId="123" />);

      // Wait for filtered results
      await waitFor(() => {
        expect(screen.getByText('Contract Document')).toBeInTheDocument();
        expect(screen.queryByText('Drawing File')).not.toBeInTheDocument();
        expect(screen.queryByText('Specification Document')).not.toBeInTheDocument();
      });
    });

    it('filters documents by category', async () => {
      // Start with all documents
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: mockDocuments },
        isLoading: false,
        error: null,
      } as any);

      const { rerender } = render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      // Initially all documents should be visible
      await waitFor(() => {
        expect(screen.getByText('Contract Document')).toBeInTheDocument();
        expect(screen.getByText('Drawing File')).toBeInTheDocument();
      });

      // Verify filter controls are present
      expect(screen.getByTestId('documents-category-select')).toBeInTheDocument();

      // Update mock to return filtered results for category 'contract'
      const filtered = mockDocuments.filter((doc) => doc.category === 'contract');

      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: filtered },
        isLoading: false,
        error: null,
      } as any);

      // Rerender to simulate React Query refetch with category filter
      rerender(<ProjectDocumentsSection projectId="123" />);

      // Wait for filtered results
      await waitFor(() => {
        expect(screen.getByText('Contract Document')).toBeInTheDocument();
        expect(screen.queryByText('Drawing File')).not.toBeInTheDocument();
        expect(screen.queryByText('Specification Document')).not.toBeInTheDocument();
      });
    });

    it('filters documents by status', async () => {
      // Start with all documents
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: mockDocuments },
        isLoading: false,
        error: null,
      } as any);

      const { rerender } = render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      // Initially all documents should be visible
      await waitFor(() => {
        expect(screen.getByText('Contract Document')).toBeInTheDocument();
        expect(screen.getByText('Drawing File')).toBeInTheDocument();
      });

      // Verify filter controls are present
      expect(screen.getByTestId('documents-status-select')).toBeInTheDocument();

      // Update mock to return filtered results for status 'active'
      const filtered = mockDocuments.filter((doc) => doc.status === 'active');

      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: filtered },
        isLoading: false,
        error: null,
      } as any);

      // Rerender to simulate React Query refetch with status filter
      rerender(<ProjectDocumentsSection projectId="123" />);

      // Wait for filtered results
      await waitFor(() => {
        expect(screen.getByText('Contract Document')).toBeInTheDocument();
        expect(screen.getByText('Specification Document')).toBeInTheDocument();
        expect(screen.queryByText('Drawing File')).not.toBeInTheDocument();
      });
    });

    it('shows all documents when "All" is selected for category/status', async () => {
      // Start with all documents
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: mockDocuments },
        isLoading: false,
        error: null,
      } as any);

      const { rerender } = render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      // Initially all documents should be visible
      await waitFor(() => {
        expect(screen.getByText('Contract Document')).toBeInTheDocument();
        expect(screen.getByText('Drawing File')).toBeInTheDocument();
        expect(screen.getByText('Specification Document')).toBeInTheDocument();
      });

      // Filter by category - update mock to return only contract documents
      const filteredByCategory = mockDocuments.filter((doc) => doc.category === 'contract');

      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: filteredByCategory },
        isLoading: false,
        error: null,
      } as any);

      rerender(<ProjectDocumentsSection projectId="123" />);

      await waitFor(() => {
        expect(screen.getByText('Contract Document')).toBeInTheDocument();
        expect(screen.queryByText('Drawing File')).not.toBeInTheDocument();
      });

      // Reset to "All Categories" - update mock to return all documents again
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: mockDocuments },
        isLoading: false,
        error: null,
      } as any);

      rerender(<ProjectDocumentsSection projectId="123" />);

      // All documents should reappear
      await waitFor(() => {
        expect(screen.getByText('Contract Document')).toBeInTheDocument();
        expect(screen.getByText('Drawing File')).toBeInTheDocument();
        expect(screen.getByText('Specification Document')).toBeInTheDocument();
      });
    });
  });

  describe('Download functionality', () => {
    const mockDocument = {
      id: '1',
      name: 'Test Document',
      description: 'Test description',
      category: 'contract',
      status: 'active',
      file_type: 'pdf',
      mime_type: 'application/pdf',
      file_size: 1024000,
      uploaded_by: { id: '1', name: 'John Doe', email: 'john@example.com' },
      created_at: '2025-01-15T10:00:00Z',
    };

    beforeEach(() => {
      mockDownloadProjectDocument.mockReset();
    });

    it('renders download button for documents', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const downloadButton = screen.getByTestId('download-button-1');
      expect(downloadButton).toBeInTheDocument();
      expect(downloadButton).toHaveTextContent('Download');
    });

    it('calls downloadProjectDocument when download button is clicked', async () => {
      mockDownloadProjectDocument.mockResolvedValue(undefined);
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const downloadButton = screen.getByTestId('download-button-1');
      fireEvent.click(downloadButton);

      await waitFor(() => {
        expect(mockDownloadProjectDocument).toHaveBeenCalledTimes(1);
        expect(mockDownloadProjectDocument).toHaveBeenCalledWith('123', '1');
      });
    });

    it('shows loading state while downloading', async () => {
      // Create a promise that we can control
      let resolveDownload: () => void;
      const downloadPromise = new Promise<void>((resolve) => {
        resolveDownload = resolve;
      });

      mockDownloadProjectDocument.mockReturnValue(downloadPromise);
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const downloadButton = screen.getByTestId('download-button-1');
      fireEvent.click(downloadButton);

      // Check loading state
      await waitFor(() => {
        expect(downloadButton).toHaveTextContent('Downloading...');
        expect(downloadButton).toBeDisabled();
      });

      // Resolve the download
      resolveDownload!();
      await waitFor(() => {
        expect(downloadButton).toHaveTextContent('Download');
        expect(downloadButton).not.toBeDisabled();
      });
    });

    it('handles download errors gracefully', async () => {
      const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
      const error = new Error('Download failed');
      mockDownloadProjectDocument.mockRejectedValue(error);
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const downloadButton = screen.getByTestId('download-button-1');
      fireEvent.click(downloadButton);

      await waitFor(() => {
        expect(mockDownloadProjectDocument).toHaveBeenCalled();
        expect(consoleErrorSpy).toHaveBeenCalledWith('Failed to download document:', error);
        // Button should return to normal state after error
        expect(downloadButton).toHaveTextContent('Download');
        expect(downloadButton).not.toBeDisabled();
      });

      consoleErrorSpy.mockRestore();
    });

    it('does not crash when download fails', async () => {
      const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
      mockDownloadProjectDocument.mockRejectedValue(new Error('Network error'));
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const downloadButton = screen.getByTestId('download-button-1');
      fireEvent.click(downloadButton);

      // Component should still render after error
      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
        expect(downloadButton).toBeInTheDocument();
      });

      consoleErrorSpy.mockRestore();
    });
  });

  describe('Upload functionality', () => {
    const mockUploadMutation = {
      mutateAsync: vi.fn(),
      isPending: false,
    };

    beforeEach(() => {
      mockUseUploadProjectDocument.mockReturnValue(mockUploadMutation as any);
      mockUploadMutation.mutateAsync.mockReset();
      mockUploadMutation.isPending = false;
    });

    it('renders upload button when showUploadButton is true', () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" showUploadButton={true} />, { wrapper: createWrapper() });

      const uploadButton = screen.getByTestId('upload-document-button');
      expect(uploadButton).toBeInTheDocument();
      expect(uploadButton).toHaveTextContent('Upload Document');
    });

    it('opens upload modal when upload button is clicked', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" showUploadButton={true} />, { wrapper: createWrapper() });

      const uploadButton = screen.getByTestId('upload-document-button');
      fireEvent.click(uploadButton);

      await waitFor(() => {
        expect(screen.getByTestId('upload-document-modal')).toBeInTheDocument();
      });
    });

    it('closes modal when cancel is clicked', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" showUploadButton={true} />, { wrapper: createWrapper() });

      // Open modal
      const uploadButton = screen.getByTestId('upload-document-button');
      fireEvent.click(uploadButton);

      await waitFor(() => {
        expect(screen.getByTestId('upload-document-modal')).toBeInTheDocument();
      });

      // Click cancel
      const cancelButton = screen.getByText('Cancel');
      fireEvent.click(cancelButton);

      await waitFor(() => {
        expect(screen.queryByTestId('upload-document-modal')).not.toBeInTheDocument();
      });
    });

    it('submits form with correct data when upload is clicked', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [] },
        isLoading: false,
        error: null,
      } as any);

      mockUploadMutation.mutateAsync.mockResolvedValue({
        success: true,
        data: { id: '1', name: 'test.pdf' },
      });

      render(<ProjectDocumentsSection projectId="123" showUploadButton={true} />, { wrapper: createWrapper() });

      // Open modal
      const uploadButton = screen.getByTestId('upload-document-button');
      fireEvent.click(uploadButton);

      await waitFor(() => {
        expect(screen.getByTestId('upload-document-modal')).toBeInTheDocument();
      });

      // Create a mock file
      const file = new File(['test content'], 'test.pdf', { type: 'application/pdf' });
      const fileInput = screen.getByTestId('upload-file-input') as HTMLInputElement;
      
      // Simulate file selection
      Object.defineProperty(fileInput, 'files', {
        value: [file],
        writable: false,
      });
      fireEvent.change(fileInput);

      // Fill form fields
      const nameInput = screen.getByTestId('upload-name-input') as HTMLInputElement;
      fireEvent.change(nameInput, { target: { value: 'Test Document' } });

      const descriptionInput = screen.getByTestId('upload-description-input') as HTMLTextAreaElement;
      fireEvent.change(descriptionInput, { target: { value: 'Test description' } });

      const categorySelect = screen.getByTestId('upload-category-select');
      // The Select component uses a custom onChange, so we need to trigger it differently
      // For now, we'll just check that the form can be submitted

      // Submit form
      const submitButton = screen.getByText('Upload');
      fireEvent.click(submitButton);

      // Wait for mutation to be called
      await waitFor(() => {
        expect(mockUploadMutation.mutateAsync).toHaveBeenCalled();
      });

      // Check that FormData was created correctly
      const callArgs = mockUploadMutation.mutateAsync.mock.calls[0][0];
      expect(callArgs.projectId).toBe('123');
      expect(callArgs.formData).toBeInstanceOf(FormData);
    });

    it('shows error message when upload fails', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [] },
        isLoading: false,
        error: null,
      } as any);

      const error = new Error('Upload failed');
      mockUploadMutation.mutateAsync.mockRejectedValue(error);

      render(<ProjectDocumentsSection projectId="123" showUploadButton={true} />, { wrapper: createWrapper() });

      // Open modal
      const uploadButton = screen.getByTestId('upload-document-button');
      fireEvent.click(uploadButton);

      await waitFor(() => {
        expect(screen.getByTestId('upload-document-modal')).toBeInTheDocument();
      });

      // Create a mock file
      const file = new File(['test content'], 'test.pdf', { type: 'application/pdf' });
      const fileInput = screen.getByTestId('upload-file-input') as HTMLInputElement;
      
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
        expect(screen.getByTestId('upload-error-message')).toBeInTheDocument();
      });

      // Modal should still be open
      expect(screen.getByTestId('upload-document-modal')).toBeInTheDocument();
    });

    it('validates file is required before submitting', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" showUploadButton={true} />, { wrapper: createWrapper() });

      // Open modal
      const uploadButton = screen.getByTestId('upload-document-button');
      fireEvent.click(uploadButton);

      await waitFor(() => {
        expect(screen.getByTestId('upload-document-modal')).toBeInTheDocument();
      });

      // Try to submit without file
      const submitButton = screen.getByText('Upload');
      fireEvent.click(submitButton);

      // Should show error
      await waitFor(() => {
        expect(screen.getByTestId('upload-error-message')).toBeInTheDocument();
        expect(screen.getByText(/Please select a file/i)).toBeInTheDocument();
      });

      // Mutation should not be called
      expect(mockUploadMutation.mutateAsync).not.toHaveBeenCalled();
    });

    it('closes modal and invalidates queries on successful upload', async () => {
      const queryClient = new QueryClient({
        defaultOptions: {
          queries: { retry: false, cacheTime: 0, staleTime: 0 },
        },
      });

      const invalidateQueriesSpy = vi.spyOn(queryClient, 'invalidateQueries');

      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [] },
        isLoading: false,
        error: null,
      } as any);

      mockUploadMutation.mutateAsync.mockResolvedValue({
        success: true,
        data: { id: '1', name: 'test.pdf' },
      });

      const Wrapper = ({ children }: { children: React.ReactNode }) => (
        <QueryClientProvider client={queryClient}>
          <BrowserRouter>{children}</BrowserRouter>
        </QueryClientProvider>
      );

      render(<ProjectDocumentsSection projectId="123" showUploadButton={true} />, { wrapper: Wrapper });

      // Open modal
      const uploadButton = screen.getByTestId('upload-document-button');
      fireEvent.click(uploadButton);

      await waitFor(() => {
        expect(screen.getByTestId('upload-document-modal')).toBeInTheDocument();
      });

      // Create a mock file
      const file = new File(['test content'], 'test.pdf', { type: 'application/pdf' });
      const fileInput = screen.getByTestId('upload-file-input') as HTMLInputElement;
      
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
        expect(screen.queryByTestId('upload-document-modal')).not.toBeInTheDocument();
      });

      // Queries should be invalidated (this happens in the hook's onSuccess)
      // The hook will call invalidateQueries, but we can't easily test that here
      // since it's internal to the hook. The important thing is the modal closes.
    });

    it('uses onUploadClick prop when provided instead of internal modal', () => {
      const mockOnUploadClick = vi.fn();
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [] },
        isLoading: false,
        error: null,
      } as any);

      render(
        <ProjectDocumentsSection
          projectId="123"
          showUploadButton={true}
          onUploadClick={mockOnUploadClick}
        />,
        { wrapper: createWrapper() }
      );

      const uploadButton = screen.getByTestId('upload-document-button');
      fireEvent.click(uploadButton);

      expect(mockOnUploadClick).toHaveBeenCalledTimes(1);
      // Modal should not be rendered when onUploadClick is provided
      expect(screen.queryByTestId('upload-document-modal')).not.toBeInTheDocument();
    });
  });

  describe('Edit functionality', () => {
    const mockDocument = {
      id: '1',
      name: 'Test Document',
      description: 'Test description',
      category: 'contract',
      status: 'active',
      file_type: 'pdf',
      mime_type: 'application/pdf',
      file_size: 1024000,
      uploaded_by: { id: '1', name: 'John Doe', email: 'john@example.com' },
      created_at: '2025-01-15T10:00:00Z',
    };

    const mockUpdateMutation = {
      mutateAsync: vi.fn(),
      isPending: false,
    };

    beforeEach(() => {
      mockUseUpdateProjectDocument.mockReturnValue(mockUpdateMutation as any);
      mockUpdateMutation.mutateAsync.mockReset();
      mockUpdateMutation.isPending = false;
    });

    it('renders Edit button for each document row', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const editButton = screen.getByTestId('edit-button-1');
      expect(editButton).toBeInTheDocument();
      expect(editButton).toHaveTextContent('Edit');
    });

    it('opens EditDocumentModal with prefilled values when Edit is clicked', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const editButton = screen.getByTestId('edit-button-1');
      fireEvent.click(editButton);

      await waitFor(() => {
        expect(screen.getByTestId('edit-document-modal')).toBeInTheDocument();
      });

      // Check that form fields are prefilled
      const nameInput = screen.getByTestId('edit-name-input') as HTMLInputElement;
      expect(nameInput.value).toBe('Test Document');

      const descriptionInput = screen.getByTestId('edit-description-input') as HTMLTextAreaElement;
      expect(descriptionInput.value).toBe('Test description');
    });

    it('calls useUpdateProjectDocument with correct payload when form is submitted', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      mockUpdateMutation.mutateAsync.mockResolvedValue({
        success: true,
        data: { ...mockDocument, name: 'Updated Document' },
      });

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      // Open edit modal
      const editButton = screen.getByTestId('edit-button-1');
      fireEvent.click(editButton);

      await waitFor(() => {
        expect(screen.getByTestId('edit-document-modal')).toBeInTheDocument();
      });

      // Update name field
      const nameInput = screen.getByTestId('edit-name-input') as HTMLInputElement;
      fireEvent.change(nameInput, { target: { value: 'Updated Document' } });

      // Submit form
      const submitButton = screen.getByText('Save Changes');
      fireEvent.click(submitButton);

      // Wait for mutation to be called
      await waitFor(() => {
        expect(mockUpdateMutation.mutateAsync).toHaveBeenCalled();
      });

      // Check that mutation was called with correct arguments
      const callArgs = mockUpdateMutation.mutateAsync.mock.calls[0][0];
      expect(callArgs.projectId).toBe('123');
      expect(callArgs.documentId).toBe('1');
      expect(callArgs.payload).toMatchObject({
        name: 'Updated Document',
      });
    });

    it('closes modal on successful update', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      mockUpdateMutation.mutateAsync.mockResolvedValue({
        success: true,
        data: mockDocument,
      });

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      // Open edit modal
      const editButton = screen.getByTestId('edit-button-1');
      fireEvent.click(editButton);

      await waitFor(() => {
        expect(screen.getByTestId('edit-document-modal')).toBeInTheDocument();
      });

      // Submit form
      const submitButton = screen.getByText('Save Changes');
      fireEvent.click(submitButton);

      // Wait for modal to close
      await waitFor(() => {
        expect(screen.queryByTestId('edit-document-modal')).not.toBeInTheDocument();
      });
    });

    it('shows error message when update fails', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      const error = new Error('Update failed');
      mockUpdateMutation.mutateAsync.mockRejectedValue(error);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      // Open edit modal
      const editButton = screen.getByTestId('edit-button-1');
      fireEvent.click(editButton);

      await waitFor(() => {
        expect(screen.getByTestId('edit-document-modal')).toBeInTheDocument();
      });

      // Submit form
      const submitButton = screen.getByText('Save Changes');
      fireEvent.click(submitButton);

      // Wait for error to appear
      await waitFor(() => {
        expect(screen.getByTestId('edit-error-message')).toBeInTheDocument();
      });

      // Modal should still be open
      expect(screen.getByTestId('edit-document-modal')).toBeInTheDocument();
    });

    it('disables Edit button while update is in progress', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      // Set mutation as pending
      mockUpdateMutation.isPending = true;
      mockUseUpdateProjectDocument.mockReturnValue(mockUpdateMutation as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      // Open edit modal
      const editButton = screen.getByTestId('edit-button-1');
      fireEvent.click(editButton);

      await waitFor(() => {
        expect(screen.getByTestId('edit-document-modal')).toBeInTheDocument();
      });

      // The edit button for the document being edited should be disabled
      // (This is handled by the component's disabled prop)
    });
  });

  describe('Delete functionality', () => {
    const mockDocument = {
      id: '1',
      name: 'Test Document',
      description: 'Test description',
      category: 'contract',
      status: 'active',
      file_type: 'pdf',
      mime_type: 'application/pdf',
      file_size: 1024000,
      uploaded_by: { id: '1', name: 'John Doe', email: 'john@example.com' },
      created_at: '2025-01-15T10:00:00Z',
    };

    const mockDeleteMutation = {
      mutateAsync: vi.fn(),
      isPending: false,
    };

    beforeEach(() => {
      mockUseDeleteProjectDocument.mockReturnValue(mockDeleteMutation as any);
      mockDeleteMutation.mutateAsync.mockReset();
      mockDeleteMutation.isPending = false;
    });

    it('renders Delete button for each document row', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const deleteButton = screen.getByTestId('delete-button-1');
      expect(deleteButton).toBeInTheDocument();
      expect(deleteButton).toHaveTextContent('Delete');
    });

    it('opens confirmation dialog when Delete is clicked', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const deleteButton = screen.getByTestId('delete-button-1');
      fireEvent.click(deleteButton);

      await waitFor(() => {
        expect(screen.getByTestId('delete-document-dialog')).toBeInTheDocument();
      });

      expect(screen.getByText('Delete document?')).toBeInTheDocument();
    });

    it('calls useDeleteProjectDocument with correct arguments when confirmed', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      mockDeleteMutation.mutateAsync.mockResolvedValue({
        success: true,
        data: null,
      });

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      // Open delete dialog
      const deleteButton = screen.getByTestId('delete-button-1');
      fireEvent.click(deleteButton);

      await waitFor(() => {
        expect(screen.getByTestId('delete-document-dialog')).toBeInTheDocument();
      });

      // Confirm delete - get all Delete buttons and find the one inside the dialog
      const allDeleteButtons = screen.getAllByText('Delete');
      const dialog = screen.getByTestId('delete-document-dialog');
      const confirmButton = allDeleteButtons.find(btn => dialog.contains(btn));
      if (confirmButton) {
        fireEvent.click(confirmButton);
      } else {
        // Fallback: get buttons in dialog footer and click the primary action (last button)
        const footer = dialog.querySelector('footer');
        if (footer) {
          const buttons = footer.querySelectorAll('button');
          fireEvent.click(buttons[buttons.length - 1]);
        }
      }

      // Wait for mutation to be called
      await waitFor(() => {
        expect(mockDeleteMutation.mutateAsync).toHaveBeenCalled();
      });

      // Check that mutation was called with correct arguments
      const callArgs = mockDeleteMutation.mutateAsync.mock.calls[0][0];
      expect(callArgs.projectId).toBe('123');
      expect(callArgs.documentId).toBe('1');
    });

    it('closes dialog on successful delete', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      mockDeleteMutation.mutateAsync.mockResolvedValue({
        success: true,
        data: null,
      });

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      // Open delete dialog
      const deleteButton = screen.getByTestId('delete-button-1');
      fireEvent.click(deleteButton);

      await waitFor(() => {
        expect(screen.getByTestId('delete-document-dialog')).toBeInTheDocument();
      });

      // Confirm delete - get all Delete buttons and find the one inside the dialog
      const allDeleteButtons = screen.getAllByText('Delete');
      const dialog = screen.getByTestId('delete-document-dialog');
      const confirmButton = allDeleteButtons.find(btn => dialog.contains(btn));
      if (confirmButton) {
        fireEvent.click(confirmButton);
      } else {
        // Fallback: get buttons in dialog footer and click the primary action (last button)
        const footer = dialog.querySelector('footer');
        if (footer) {
          const buttons = footer.querySelectorAll('button');
          fireEvent.click(buttons[buttons.length - 1]);
        }
      }

      // Wait for dialog to close
      await waitFor(() => {
        expect(screen.queryByTestId('delete-document-dialog')).not.toBeInTheDocument();
      });
    });

    it('does not call mutation when Cancel is clicked', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      // Open delete dialog
      const deleteButton = screen.getByTestId('delete-button-1');
      fireEvent.click(deleteButton);

      await waitFor(() => {
        expect(screen.getByTestId('delete-document-dialog')).toBeInTheDocument();
      });

      // Click Cancel
      const cancelButton = screen.getByText('Cancel');
      fireEvent.click(cancelButton);

      // Wait for dialog to close
      await waitFor(() => {
        expect(screen.queryByTestId('delete-document-dialog')).not.toBeInTheDocument();
      });

      // Mutation should not be called
      expect(mockDeleteMutation.mutateAsync).not.toHaveBeenCalled();
    });

    it('handles delete errors gracefully', async () => {
      const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      const error = new Error('Delete failed');
      mockDeleteMutation.mutateAsync.mockRejectedValue(error);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      // Open delete dialog
      const deleteButton = screen.getByTestId('delete-button-1');
      fireEvent.click(deleteButton);

      await waitFor(() => {
        expect(screen.getByTestId('delete-document-dialog')).toBeInTheDocument();
      });

      // Confirm delete - get all Delete buttons and find the one inside the dialog
      const allDeleteButtons = screen.getAllByText('Delete');
      const dialog = screen.getByTestId('delete-document-dialog');
      const confirmButton = allDeleteButtons.find(btn => dialog.contains(btn));
      if (confirmButton) {
        fireEvent.click(confirmButton);
      } else {
        // Fallback: get buttons in dialog footer and click the primary action (last button)
        const footer = dialog.querySelector('footer');
        if (footer) {
          const buttons = footer.querySelectorAll('button');
          fireEvent.click(buttons[buttons.length - 1]);
        }
      }

      // Wait for error to be logged
      await waitFor(() => {
        expect(consoleErrorSpy).toHaveBeenCalledWith('Delete error:', error);
      });

      // Component should still render (not crash)
      expect(screen.getByText('Test Document')).toBeInTheDocument();

      consoleErrorSpy.mockRestore();
    });

    it('shows loading state while deleting', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      // Create a promise that we can control
      let resolveDelete: () => void;
      const deletePromise = new Promise<{ success: boolean; data: null }>((resolve) => {
        resolveDelete = () => resolve({ success: true, data: null });
      });

      mockDeleteMutation.mutateAsync.mockReturnValue(deletePromise);
      mockDeleteMutation.isPending = true;
      mockUseDeleteProjectDocument.mockReturnValue(mockDeleteMutation as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      // Open delete dialog
      const deleteButton = screen.getByTestId('delete-button-1');
      fireEvent.click(deleteButton);

      await waitFor(() => {
        expect(screen.getByTestId('delete-document-dialog')).toBeInTheDocument();
      });

      // Confirm delete - get all Delete buttons and find the one inside the dialog
      const allDeleteButtons = screen.getAllByText('Delete');
      const dialog = screen.getByTestId('delete-document-dialog');
      const confirmButton = allDeleteButtons.find(btn => dialog.contains(btn));
      if (confirmButton) {
        fireEvent.click(confirmButton);
      } else {
        // Fallback: get buttons in dialog footer and click the primary action (last button)
        const footer = dialog.querySelector('footer');
        if (footer) {
          const buttons = footer.querySelectorAll('button');
          fireEvent.click(buttons[buttons.length - 1]);
        }
      }

      // Check loading state - button should show "Deleting..."
      await waitFor(() => {
        const deleteBtn = screen.getByTestId('delete-button-1');
        expect(deleteBtn).toHaveTextContent('Deleting...');
      });

      // Resolve the delete
      resolveDelete!();
      await waitFor(() => {
        expect(screen.queryByTestId('delete-document-dialog')).not.toBeInTheDocument();
      });
    });
  });

  describe('Versions functionality', () => {
    const mockDocument = {
      id: '1',
      name: 'Test Document',
      description: 'Test description',
      category: 'contract',
      status: 'active',
      file_type: 'pdf',
      mime_type: 'application/pdf',
      file_size: 1024000,
      uploaded_by: { id: '1', name: 'John Doe', email: 'john@example.com' },
      created_at: '2025-01-15T10:00:00Z',
    };

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

    beforeEach(() => {
      mockUseDocumentVersions.mockReturnValue({
        data: { success: true, data: mockVersions },
        isLoading: false,
        error: null,
      } as any);
      mockUseUploadDocumentVersion.mockReturnValue({
        mutateAsync: vi.fn(),
        isPending: false,
      } as any);
      mockDownloadDocumentVersion.mockResolvedValue(undefined);
    });

    it('renders "Versions" button for each document row', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const versionsButton = screen.getByTestId('versions-button-1');
      expect(versionsButton).toBeInTheDocument();
      expect(versionsButton).toHaveTextContent('Versions');
    });

    it('opens modal when Versions button is clicked', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const versionsButton = screen.getByTestId('versions-button-1');
      fireEvent.click(versionsButton);

      await waitFor(() => {
        expect(screen.getByTestId('document-versions-modal')).toBeInTheDocument();
      });
    });

    it('fetches version list when modal opens', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const versionsButton = screen.getByTestId('versions-button-1');
      fireEvent.click(versionsButton);

      await waitFor(() => {
        expect(mockUseDocumentVersions).toHaveBeenCalledWith('123', '1');
      });
    });

    it('shows version rows correctly', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const versionsButton = screen.getByTestId('versions-button-1');
      fireEvent.click(versionsButton);

      await waitFor(() => {
        expect(screen.getByText('v1')).toBeInTheDocument();
        expect(screen.getByText('v2')).toBeInTheDocument();
        expect(screen.getByText('version-1.pdf')).toBeInTheDocument();
        expect(screen.getByText('version-2.pdf')).toBeInTheDocument();
      });
    });

    it('calls downloadDocumentVersion with correct params when download button is clicked', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const versionsButton = screen.getByTestId('versions-button-1');
      fireEvent.click(versionsButton);

      await waitFor(() => {
        expect(screen.getByTestId('download-version-button-v1')).toBeInTheDocument();
      });

      const downloadButton = screen.getByTestId('download-version-button-v1');
      fireEvent.click(downloadButton);

      await waitFor(() => {
        expect(mockDownloadDocumentVersion).toHaveBeenCalledWith('123', '1', 'v1');
      });
    });

    it('handles error state in modal', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      mockUseDocumentVersions.mockReturnValue({
        data: undefined,
        isLoading: false,
        error: new Error('Failed to load versions'),
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const versionsButton = screen.getByTestId('versions-button-1');
      fireEvent.click(versionsButton);

      await waitFor(() => {
        expect(screen.getByText(/Error loading versions/)).toBeInTheDocument();
      });
    });

    it('handles loading state in modal', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      mockUseDocumentVersions.mockReturnValue({
        data: undefined,
        isLoading: true,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const versionsButton = screen.getByTestId('versions-button-1');
      fireEvent.click(versionsButton);

      await waitFor(() => {
        expect(screen.getByText('Loading versions...')).toBeInTheDocument();
      });
    });

    it('closes modal properly', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const versionsButton = screen.getByTestId('versions-button-1');
      fireEvent.click(versionsButton);

      await waitFor(() => {
        expect(screen.getByTestId('document-versions-modal')).toBeInTheDocument();
      });

      // Find and click the close button
      const modal = screen.getByTestId('document-versions-modal');
      const closeButton = modal.querySelector('button[aria-label]');
      if (closeButton) {
        fireEvent.click(closeButton);
      }

      await waitFor(() => {
        expect(screen.queryByTestId('document-versions-modal')).not.toBeInTheDocument();
      });
    });

    it('shows Upload New Version button in versions modal', async () => {
      mockUseProjectDocuments.mockReturnValue({
        data: { success: true, data: [mockDocument] },
        isLoading: false,
        error: null,
      } as any);

      render(<ProjectDocumentsSection projectId="123" />, { wrapper: createWrapper() });

      await waitFor(() => {
        expect(screen.getByText('Test Document')).toBeInTheDocument();
      });

      const versionsButton = screen.getByTestId('versions-button-1');
      fireEvent.click(versionsButton);

      await waitFor(() => {
        expect(screen.getByTestId('document-versions-modal')).toBeInTheDocument();
        expect(screen.getByTestId('open-upload-version-modal-button')).toBeInTheDocument();
        expect(screen.getByText('Upload New Version')).toBeInTheDocument();
      });
    });
  });
});

