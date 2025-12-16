import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ContractDetailPage } from '../ContractDetailPage';
import { projectsApi } from '../../api';
import { downloadBlob } from '../../../../utils/downloadBlob';

// Mock dependencies
vi.mock('../../api', () => ({
  projectsApi: {
    exportPaymentCertificatePdf: vi.fn(),
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
  useContractPaymentCertificates: () => ({
    data: {
      data: [
        {
          id: 'cert-001',
          code: 'PC-001',
          title: 'Test Certificate',
          status: 'approved',
          amount_payable: 50000,
        },
      ],
    },
  }),
  useContractPayments: () => ({ data: { data: [] } }),
}));

describe('PaymentCertificatePdfExportButton', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should render export button for each certificate', () => {
    render(<ContractDetailPage />);
    const exportButtons = screen.getAllByText(/Export PDF/i);
    expect(exportButtons.length).toBeGreaterThan(0);
  });

  it('should trigger API call and download when clicked', async () => {
    const mockBlob = new Blob(['PDF content'], { type: 'application/pdf' });
    vi.mocked(projectsApi.exportPaymentCertificatePdf).mockResolvedValue(mockBlob);

    render(<ContractDetailPage />);
    const exportButtons = screen.getAllByText(/Export PDF/i);
    const certificateButton = exportButtons.find(btn => btn.textContent?.includes('Export PDF'));

    if (certificateButton) {
      await userEvent.click(certificateButton);

      await waitFor(() => {
        expect(projectsApi.exportPaymentCertificatePdf).toHaveBeenCalledWith('project-123', 'contract-456', 'cert-001');
        expect(downloadBlob).toHaveBeenCalledWith(mockBlob, 'payment-certificate-PC-001.pdf');
      });
    }
  });

  it('should handle error gracefully', async () => {
    const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {});
    
    vi.mocked(projectsApi.exportPaymentCertificatePdf).mockRejectedValue(new Error('Export failed'));

    render(<ContractDetailPage />);
    const exportButtons = screen.getAllByText(/Export PDF/i);
    const certificateButton = exportButtons.find(btn => btn.textContent?.includes('Export PDF'));

    if (certificateButton) {
      await userEvent.click(certificateButton);

      await waitFor(() => {
        expect(consoleErrorSpy).toHaveBeenCalled();
        expect(alertSpy).toHaveBeenCalledWith('Failed to export payment certificate PDF. Please try again.');
      });
    }

    consoleErrorSpy.mockRestore();
    alertSpy.mockRestore();
  });
});
