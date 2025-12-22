import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ChangeOrderDetailPage } from '../ChangeOrderDetailPage';
import { projectsApi } from '../../api';
import { downloadBlob } from '../../../../utils/downloadBlob';

// Mock dependencies
vi.mock('../../api', () => ({
  projectsApi: {
    exportChangeOrderPdf: vi.fn(),
  },
}));

vi.mock('../../../../utils/downloadBlob', () => ({
  downloadBlob: vi.fn(),
}));

vi.mock('react-router-dom', () => ({
  useParams: () => ({ id: 'project-123', contractId: 'contract-456', coId: 'co-789' }),
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
      },
    },
  }),
  useChangeOrderDetail: () => ({
    data: {
      data: {
        id: 'co-789',
        code: 'CO-001',
        title: 'Test Change Order',
        amount_delta: 5000,
        status: 'approved',
        lines: [],
      },
    },
    isLoading: false,
    error: null,
  }),
}));

describe('ChangeOrderPdfExportButton', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should render export button', () => {
    render(<ChangeOrderDetailPage />);
    const exportButton = screen.getByText(/Export CO PDF/i);
    expect(exportButton).toBeInTheDocument();
  });

  it('should trigger API call and download when clicked', async () => {
    const mockBlob = new Blob(['PDF content'], { type: 'application/pdf' });
    vi.mocked(projectsApi.exportChangeOrderPdf).mockResolvedValue(mockBlob);

    render(<ChangeOrderDetailPage />);
    const exportButton = screen.getByText(/Export CO PDF/i);

    await userEvent.click(exportButton);

    await waitFor(() => {
      expect(projectsApi.exportChangeOrderPdf).toHaveBeenCalledWith('project-123', 'contract-456', 'co-789');
      expect(downloadBlob).toHaveBeenCalledWith(mockBlob, 'change-order-CO-001.pdf');
    });
  });

  it('should handle error gracefully', async () => {
    const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});
    const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {});
    
    vi.mocked(projectsApi.exportChangeOrderPdf).mockRejectedValue(new Error('Export failed'));

    render(<ChangeOrderDetailPage />);
    const exportButton = screen.getByText(/Export CO PDF/i);

    await userEvent.click(exportButton);

    await waitFor(() => {
      expect(consoleErrorSpy).toHaveBeenCalled();
      expect(alertSpy).toHaveBeenCalledWith('Failed to export change order PDF. Please try again.');
    });

    consoleErrorSpy.mockRestore();
    alertSpy.mockRestore();
  });
});
