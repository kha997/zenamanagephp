import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ContractDetailPage } from '../ContractDetailPage';
import { projectsApi } from '../../api';
import { downloadBlob } from '../../../../utils/downloadBlob';

// Mock dependencies
vi.mock('../../api', () => ({
  projectsApi: {
    exportContractPdf: vi.fn(),
  },
}));

vi.mock('../../../../utils/downloadBlob', () => ({
  downloadBlob: vi.fn(),
}));

vi.mock('react-router-dom', () => ({
  useParams: () => ({ id: 'project-123', contractId: 'contract-456' }),
  useNavigate: () => vi.fn(),
}));

vi.mock('../../hooks', () => ({
  useProject: () => ({ data: { data: { name: 'Test Project' } } }),
  useContractDetail: () => ({
    data: {
      data: {
        id: 'contract-456',
        code: 'CT-001',
        name: 'Test Contract',
        currency: 'VND',
        base_amount: 100000,
        current_amount: 100000,
        lines: [],
      },
    },
    isLoading: false,
    error: null,
  }),
  useContractChangeOrders: () => ({ data: { data: [] } }),
  useContractPaymentCertificates: () => ({ data: { data: [] } }),
  useContractPayments: () => ({ data: { data: [] } }),
}));

describe('ContractPdfExportButton', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should render export button', () => {
    render(<ContractDetailPage />);
    const exportButton = screen.getByText(/Export Contract PDF/i);
    expect(exportButton).toBeInTheDocument();
  });

  it('should trigger API call and download when clicked', async () => {
    const mockBlob = new Blob(['PDF content'], { type: 'application/pdf' });
    vi.mocked(projectsApi.exportContractPdf).mockResolvedValue(mockBlob);

    render(<ContractDetailPage />);
    const exportButton = screen.getByText(/Export Contract PDF/i);

    await userEvent.click(exportButton);

    await waitFor(() => {
      expect(projectsApi.exportContractPdf).toHaveBeenCalledWith('project-123', 'contract-456');
      expect(downloadBlob).toHaveBeenCalledWith(mockBlob, 'contract-CT-001.pdf');
    });
  });

  it('should handle error gracefully', async () => {
    const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {});
    
    vi.mocked(projectsApi.exportContractPdf).mockRejectedValue(new Error('Export failed'));

    render(<ContractDetailPage />);
    const exportButton = screen.getByText(/Export Contract PDF/i);

    await userEvent.click(exportButton);

    await waitFor(() => {
      expect(consoleErrorSpy).toHaveBeenCalled();
      expect(alertSpy).toHaveBeenCalledWith('Failed to export contract PDF. Please try again.');
    });

    consoleErrorSpy.mockRestore();
    alertSpy.mockRestore();
  });
});
