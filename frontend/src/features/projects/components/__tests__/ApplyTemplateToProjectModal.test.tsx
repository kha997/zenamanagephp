import React from 'react';
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, waitFor, cleanup, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { BrowserRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ApplyTemplateToProjectModal } from '../ApplyTemplateToProjectModal';
import { useTemplateSets, useTemplateSetDetail, useApplyTemplateToProject } from '../../hooks';
// Mock the hooks
vi.mock('../../hooks', () => ({
  useTemplateSets: vi.fn(),
  useTemplateSetDetail: vi.fn(),
  useApplyTemplateToProject: vi.fn(),
}));

// Create mock functions using vi.hoisted to ensure they're available in the mock factory
const { mockAddToast } = vi.hoisted(() => ({
  mockAddToast: vi.fn(),
}));

vi.mock('@/shared/ui/toast', () => ({
  useToast: () => ({
    addToast: mockAddToast,
    toasts: [],
    removeToast: vi.fn(),
    clearToasts: vi.fn(),
  }),
}));

vi.mock('@/shared/utils/idempotency', () => ({
  generateIdempotencyKey: vi.fn(() => 'test-idempotency-key-123'),
}));

const mockUseTemplateSets = vi.mocked(useTemplateSets);
const mockUseTemplateSetDetail = vi.mocked(useTemplateSetDetail);
const mockUseApplyTemplateToProject = vi.mocked(useApplyTemplateToProject);

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
      mutations: {
        retry: false,
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

const mockTemplateSets = [
  { id: 'set1', code: 'SET1', name: 'Template Set 1', description: 'First template set' },
  { id: 'set2', code: 'SET2', name: 'Template Set 2', description: 'Second template set' },
];

const mockPresets = [
  { id: 'preset1', code: 'PRESET1', name: 'Preset 1', description: 'First preset' },
  { id: 'preset2', code: 'PRESET2', name: 'Preset 2', description: 'Second preset' },
];

describe('ApplyTemplateToProjectModal', () => {
  const mockOnApplied = vi.fn();
  const mockMutateAsync = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    mockAddToast.mockClear();

    // Default template sets mock
    mockUseTemplateSets.mockReturnValue({
      data: { data: mockTemplateSets },
      isLoading: false,
      isError: false,
      error: null,
    } as any);

    // Default template set detail mock
    mockUseTemplateSetDetail.mockReturnValue({
      data: { data: { ...mockTemplateSets[0], presets: mockPresets } },
      isLoading: false,
      isError: false,
      error: null,
    } as any);

    // Default apply mutation mock
    mockUseApplyTemplateToProject.mockReturnValue({
      mutateAsync: mockMutateAsync,
      isPending: false,
      isError: false,
      error: null,
    } as any);
  });

  afterEach(() => {
    cleanup();

    // Clean up QueryClient instances
    queryClients.forEach((client) => {
      client.clear();
      client.removeQueries();
    });
    queryClients.length = 0;

    vi.clearAllMocks();
  });

  describe('Permission gating', () => {
    it('should not render when modal is closed', () => {
      const { container } = render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={false}
          onOpenChange={vi.fn()}
        />,
        { wrapper: createWrapper() }
      );

      expect(container.firstChild).toBeNull();
    });

    it('should render when modal is open', () => {
      render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={vi.fn()}
        />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByText('Áp dụng mẫu công việc')).toBeInTheDocument();
    });
  });

  describe('Template sets loading', () => {
    it('should show loading state when fetching template sets', () => {
      mockUseTemplateSets.mockReturnValue({
        data: undefined,
        isLoading: true,
        isError: false,
        error: null,
      } as any);

      render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={vi.fn()}
        />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByText('Đang tải...')).toBeInTheDocument();
    });

    it('should show empty state when no template sets available', () => {
      mockUseTemplateSets.mockReturnValue({
        data: { data: [] },
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={vi.fn()}
        />,
        { wrapper: createWrapper() }
      );

      expect(screen.getByText('Không có mẫu công việc nào khả dụng')).toBeInTheDocument();
    });
  });

  describe('Template set selection', () => {
    it('should display template sets in selector', async () => {
      const user = userEvent.setup();

      render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={vi.fn()}
        />,
        { wrapper: createWrapper() }
      );

      // Find the select button and click it (Select component renders multiple elements with same text)
      const selectButtons = screen.getAllByText('Chọn mẫu công việc');
      const selectButton = selectButtons.find(btn => btn.tagName === 'BUTTON' || btn.closest('button'));
      if (!selectButton) throw new Error('Select button not found');
      await user.click(selectButton);

      // Wait for dropdown to open and check that template sets are available (use role="option" to avoid option elements)
      await waitFor(() => {
        const options = screen.getAllByRole('option');
        expect(options.length).toBeGreaterThan(0);
      });
      const options = screen.getAllByRole('option');
      const optionTexts = options.map(opt => opt.textContent);
      expect(optionTexts).toContain('Template Set 1');
      expect(optionTexts).toContain('Template Set 2');
    });

    it('should load presets when template set is selected', async () => {
      const user = userEvent.setup();

      render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={vi.fn()}
        />,
        { wrapper: createWrapper() }
      );

      // Select a template set
      const selectButtons = screen.getAllByText('Chọn mẫu công việc');
      const selectButton = selectButtons.find(btn => btn.tagName === 'BUTTON' || btn.closest('button'));
      if (!selectButton) throw new Error('Select button not found');
      await user.click(selectButton);
      
      // Wait for dropdown and click the option button (not the option element)
      await waitFor(() => {
        const options = screen.getAllByRole('option');
        expect(options.length).toBeGreaterThan(0);
      });
      const optionButtons = screen.getAllByRole('option');
      const templateSet1Option = optionButtons.find(opt => opt.textContent === 'Template Set 1');
      if (!templateSet1Option) throw new Error('Template Set 1 option not found');
      await user.click(templateSet1Option);

      // Wait for presets to load
      await waitFor(() => {
        expect(mockUseTemplateSetDetail).toHaveBeenCalledWith('set1', { enabled: true });
      });

      // Check that preset selector appears
      expect(screen.getByText('Preset (tùy chọn)')).toBeInTheDocument();
    });

    it('should reset preset when template set changes', async () => {
      const user = userEvent.setup();

      render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={vi.fn()}
        />,
        { wrapper: createWrapper() }
      );

      // Select first template set and a preset
      const selectButtons1 = screen.getAllByText('Chọn mẫu công việc');
      const selectButton1 = selectButtons1.find(btn => btn.tagName === 'BUTTON' || btn.closest('button'));
      if (!selectButton1) throw new Error('Select button not found');
      await user.click(selectButton1);
      await waitFor(() => {
        const options = screen.getAllByRole('option');
        expect(options.length).toBeGreaterThan(0);
      });
      const optionButtons = screen.getAllByRole('option');
      const templateSet1Option = optionButtons.find(opt => opt.textContent === 'Template Set 1');
      if (!templateSet1Option) throw new Error('Template Set 1 option not found');
      await user.click(templateSet1Option);

      await waitFor(() => {
        expect(screen.getByText('Preset (tùy chọn)')).toBeInTheDocument();
      });

      // Select a preset
      const presetButtons = screen.getAllByText('Chọn preset (tùy chọn)');
      const presetButton = presetButtons.find(btn => btn.tagName === 'BUTTON' || btn.closest('button'));
      if (!presetButton) throw new Error('Preset button not found');
      await user.click(presetButton);
      
      // Wait for dropdown and click the option button (filter out template set options)
      await waitFor(() => {
        const allOptions = screen.getAllByRole('option');
        const presetOptions = allOptions.filter(opt => opt.textContent === 'Preset 1' || opt.textContent === 'Preset 2');
        expect(presetOptions.length).toBeGreaterThan(0);
      });
      const allOptions = screen.getAllByRole('option');
      const presetOptionButtons = allOptions.filter(opt => opt.textContent === 'Preset 1' || opt.textContent === 'Preset 2');
      const preset1Option = presetOptionButtons.find(opt => opt.textContent === 'Preset 1');
      if (!preset1Option) throw new Error('Preset 1 option not found');
      await user.click(preset1Option);

      // Change template set - need to click the select again
      const currentValueButtons = screen.getAllByText('Template Set 1');
      const selectButton2 = currentValueButtons.find(btn => (btn.tagName === 'BUTTON' || btn.closest('button')) && btn.getAttribute('aria-expanded') !== 'true');
      if (selectButton2) {
        await user.click(selectButton2);
        await waitFor(() => {
          const options = screen.getAllByRole('option');
          expect(options.length).toBeGreaterThan(0);
        });
        const optionButtons = screen.getAllByRole('option');
        const templateSet2Option = optionButtons.find(opt => opt.textContent === 'Template Set 2');
        if (templateSet2Option) {
          await user.click(templateSet2Option);
        }
      }

      // Preset should be reset
      await waitFor(() => {
        const presetSelects = screen.getAllByText('Chọn preset (tùy chọn)');
        expect(presetSelects.length).toBeGreaterThan(0);
      });
    });
  });

  describe('Happy path - Apply template', () => {
    it('should apply template with correct payload and idempotency key', async () => {
      const user = userEvent.setup();
      const mockOnOpenChange = vi.fn();

      mockMutateAsync.mockResolvedValue({
        data: {
          project_id: '123',
          template_set_id: 'set1',
          created_tasks: 5,
          created_dependencies: 2,
        },
      });

      render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={mockOnOpenChange}
          onApplied={mockOnApplied}
        />,
        { wrapper: createWrapper() }
      );

      // Select template set
      const selectButtons = screen.getAllByText('Chọn mẫu công việc');
      const selectButton = selectButtons.find(btn => btn.tagName === 'BUTTON' || btn.closest('button'));
      if (!selectButton) throw new Error('Select button not found');
      await user.click(selectButton);
      await waitFor(() => {
        const options = screen.getAllByRole('option');
        expect(options.length).toBeGreaterThan(0);
      });
      const optionButtons = screen.getAllByRole('option');
      const templateSet1Option = optionButtons.find(opt => opt.textContent === 'Template Set 1');
      if (!templateSet1Option) throw new Error('Template Set 1 option not found');
      await user.click(templateSet1Option);

      // Toggle include_dependencies to false
      const checkbox = screen.getByLabelText('Tạo dependencies (phụ thuộc)');
      await user.click(checkbox);

      // Click apply button
      const applyButton = screen.getByText('Áp dụng');
      await user.click(applyButton);

      // Wait for mutation to be called
      await waitFor(() => {
        expect(mockMutateAsync).toHaveBeenCalledWith({
          projectId: '123',
          payload: {
            template_set_id: 'set1',
            preset_id: null,
            options: {
              include_dependencies: false,
            },
          },
          idempotencyKey: 'test-idempotency-key-123',
        });
      });

      // Check success toast
      await waitFor(() => {
        expect(mockAddToast).toHaveBeenCalledWith({
          type: 'success',
          title: 'Áp dụng mẫu thành công',
          message: 'Đã tạo 5 công việc, 2 phụ thuộc',
        });
      });

      // Modal should close
      await waitFor(() => {
        expect(mockOnOpenChange).toHaveBeenCalledWith(false);
      });

      // Callback should be called
      expect(mockOnApplied).toHaveBeenCalled();
    });

    it('should apply template with preset selected', async () => {
      const user = userEvent.setup();

      mockMutateAsync.mockResolvedValue({
        data: {
          project_id: '123',
          template_set_id: 'set1',
          created_tasks: 3,
          created_dependencies: 1,
        },
      });

      render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={vi.fn()}
        />,
        { wrapper: createWrapper() }
      );

      // Select template set
      const selectButtons = screen.getAllByText('Chọn mẫu công việc');
      const selectButton = selectButtons.find(btn => btn.tagName === 'BUTTON' || btn.closest('button'));
      if (!selectButton) throw new Error('Select button not found');
      await user.click(selectButton);
      await waitFor(() => {
        const options = screen.getAllByRole('option');
        expect(options.length).toBeGreaterThan(0);
      });
      const optionButtons = screen.getAllByRole('option');
      const templateSet1Option = optionButtons.find(opt => opt.textContent === 'Template Set 1');
      if (!templateSet1Option) throw new Error('Template Set 1 option not found');
      await user.click(templateSet1Option);

      // Wait for presets to load
      await waitFor(() => {
        expect(screen.getByText('Preset (tùy chọn)')).toBeInTheDocument();
      });

      // Select preset
      const presetButtons = screen.getAllByText('Chọn preset (tùy chọn)');
      const presetButton = presetButtons.find(btn => btn.tagName === 'BUTTON' || btn.closest('button'));
      if (!presetButton) throw new Error('Preset button not found');
      await user.click(presetButton);
      
      // Wait for dropdown and click the option button (filter out template set options)
      await waitFor(() => {
        const allOptions = screen.getAllByRole('option');
        const presetOptions = allOptions.filter(opt => opt.textContent === 'Preset 1' || opt.textContent === 'Preset 2');
        expect(presetOptions.length).toBeGreaterThan(0);
      });
      const allOptions = screen.getAllByRole('option');
      const presetOptionButtons = allOptions.filter(opt => opt.textContent === 'Preset 1' || opt.textContent === 'Preset 2');
      const preset1Option = presetOptionButtons.find(opt => opt.textContent === 'Preset 1');
      if (!preset1Option) throw new Error('Preset 1 option not found');
      await user.click(preset1Option);

      // Click apply
      const applyButton = screen.getByText('Áp dụng');
      await user.click(applyButton);

      // Check that preset_id is included
      await waitFor(() => {
        expect(mockMutateAsync).toHaveBeenCalledWith({
          projectId: '123',
          payload: {
            template_set_id: 'set1',
            preset_id: 'preset1',
            options: {
              include_dependencies: true,
            },
          },
          idempotencyKey: 'test-idempotency-key-123',
        });
      });
    });
  });

  describe('Error handling', () => {
    it('should show error message when apply fails', async () => {
      const user = userEvent.setup();

      const error = new Error('Failed to apply template');
      mockUseApplyTemplateToProject.mockReturnValue({
        mutateAsync: mockMutateAsync,
        isPending: false,
        isError: true,
        error: error as any,
      } as any);

      mockMutateAsync.mockRejectedValue(error);

      render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={vi.fn()}
        />,
        { wrapper: createWrapper() }
      );

      // Select template set
      const selectButtons = screen.getAllByText('Chọn mẫu công việc');
      const selectButton = selectButtons.find(btn => btn.tagName === 'BUTTON' || btn.closest('button'));
      if (!selectButton) throw new Error('Select button not found');
      await user.click(selectButton);
      await waitFor(() => {
        const options = screen.getAllByRole('option');
        expect(options.length).toBeGreaterThan(0);
      });
      const optionButtons = screen.getAllByRole('option');
      const templateSet1Option = optionButtons.find(opt => opt.textContent === 'Template Set 1');
      if (!templateSet1Option) throw new Error('Template Set 1 option not found');
      await user.click(templateSet1Option);

      // Click apply
      const applyButton = screen.getByText('Áp dụng');
      await user.click(applyButton);

      // Wait for error to appear
      await waitFor(() => {
        expect(screen.getByText('Không thể áp dụng mẫu')).toBeInTheDocument();
        expect(screen.getByText('Failed to apply template')).toBeInTheDocument();
      });

      // Retry button should be visible
      expect(screen.getByText('Thử lại')).toBeInTheDocument();
    });

    it('should retry with new idempotency key when retry button is clicked', async () => {
      const user = userEvent.setup();

      const error = new Error('Network error');
      mockMutateAsync.mockRejectedValueOnce(error).mockResolvedValueOnce({
        data: {
          project_id: '123',
          template_set_id: 'set1',
          created_tasks: 3,
          created_dependencies: 0,
        },
      });

      mockUseApplyTemplateToProject.mockReturnValue({
        mutateAsync: mockMutateAsync,
        isPending: false,
        isError: true,
        error: error as any,
      } as any);

      render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={vi.fn()}
        />,
        { wrapper: createWrapper() }
      );

      // Select template set and apply
      const selectButtons = screen.getAllByText('Chọn mẫu công việc');
      const selectButton = selectButtons.find(btn => btn.tagName === 'BUTTON' || btn.closest('button'));
      if (!selectButton) throw new Error('Select button not found');
      await user.click(selectButton);
      await waitFor(() => {
        const options = screen.getAllByRole('option');
        expect(options.length).toBeGreaterThan(0);
      });
      const optionButtons = screen.getAllByRole('option');
      const templateSet1Option = optionButtons.find(opt => opt.textContent === 'Template Set 1');
      if (!templateSet1Option) throw new Error('Template Set 1 option not found');
      await user.click(templateSet1Option);

      const applyButton = screen.getByText('Áp dụng');
      await user.click(applyButton);

      // Wait for error
      await waitFor(() => {
        expect(screen.getByText('Thử lại')).toBeInTheDocument();
      });

      // Click retry
      const retryButton = screen.getByText('Thử lại');
      await user.click(retryButton);

      // Should call mutateAsync again (with new idempotency key)
      await waitFor(() => {
        expect(mockMutateAsync).toHaveBeenCalledTimes(2);
      });
    });

    it('should show validation error when no template set is selected', async () => {
      const user = userEvent.setup();

      render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={vi.fn()}
        />,
        { wrapper: createWrapper() }
      );

      // Try to apply without selecting template set
      const applyButton = screen.getByText('Áp dụng');
      await user.click(applyButton);

      // Should show error toast
      await waitFor(() => {
        expect(mockAddToast).toHaveBeenCalledWith({
          type: 'error',
          title: 'Lỗi',
          message: 'Vui lòng chọn mẫu công việc',
        });
      });

      // Should not call mutation
      expect(mockMutateAsync).not.toHaveBeenCalled();
    });
  });

  describe('Empty states', () => {
    it('should show message when template set has no presets', async () => {
      const user = userEvent.setup();

      mockUseTemplateSetDetail.mockReturnValue({
        data: { data: { ...mockTemplateSets[0], presets: [] } },
        isLoading: false,
        isError: false,
        error: null,
      } as any);

      render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={vi.fn()}
        />,
        { wrapper: createWrapper() }
      );

      // Select template set
      const selectButtons = screen.getAllByText('Chọn mẫu công việc');
      const selectButton = selectButtons.find(btn => btn.tagName === 'BUTTON' || btn.closest('button'));
      if (!selectButton) throw new Error('Select button not found');
      await user.click(selectButton);
      await waitFor(() => {
        const options = screen.getAllByRole('option');
        expect(options.length).toBeGreaterThan(0);
      });
      const optionButtons = screen.getAllByRole('option');
      const templateSet1Option = optionButtons.find(opt => opt.textContent === 'Template Set 1');
      if (!templateSet1Option) throw new Error('Template Set 1 option not found');
      await user.click(templateSet1Option);

      // Wait for preset section to appear
      await waitFor(() => {
        expect(screen.getByText('Mẫu này không có preset')).toBeInTheDocument();
      });
    });
  });

  describe('Form reset', () => {
    it('should reset form when modal closes', async () => {
      const user = userEvent.setup();
      const mockOnOpenChange = vi.fn();

      render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={mockOnOpenChange}
        />,
        { wrapper: createWrapper() }
      );

      // Select template set
      const selectButtons = screen.getAllByText('Chọn mẫu công việc');
      const selectButton = selectButtons.find(btn => btn.tagName === 'BUTTON' || btn.closest('button'));
      if (!selectButton) throw new Error('Select button not found');
      await user.click(selectButton);
      await waitFor(() => {
        const options = screen.getAllByRole('option');
        expect(options.length).toBeGreaterThan(0);
      });
      const optionButtons = screen.getAllByRole('option');
      const templateSet1Option = optionButtons.find(opt => opt.textContent === 'Template Set 1');
      if (!templateSet1Option) throw new Error('Template Set 1 option not found');
      await user.click(templateSet1Option);

      // Close modal
      const cancelButton = screen.getByText('Hủy');
      await user.click(cancelButton);

      expect(mockOnOpenChange).toHaveBeenCalledWith(false);

      // Reopen modal - form should be reset
      const { rerender } = render(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={mockOnOpenChange}
        />,
        { wrapper: createWrapper() }
      );

      // Close and reopen
      mockOnOpenChange(false);
      rerender(
        <ApplyTemplateToProjectModal
          projectId="123"
          open={true}
          onOpenChange={mockOnOpenChange}
        />
      );

      // Template set selector should be empty (check that placeholder is visible)
      const selectButtonsAfterReset = screen.getAllByText('Chọn mẫu công việc');
      expect(selectButtonsAfterReset.length).toBeGreaterThan(0);
    });
  });
});

