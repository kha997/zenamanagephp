import { describe, it, expect, beforeEach, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { WidgetGrid } from '../WidgetGrid';
import { createWidget, widgetRegistry } from '../registry';
import type { DashboardWidget } from '../../../entities/dashboard/types';

// Mock the dashboard hooks
vi.mock('../../../entities/dashboard/hooks', () => ({
  useAddWidget: () => ({
    mutateAsync: vi.fn().mockResolvedValue({}),
    isPending: false,
  }),
  useRemoveWidget: () => ({
    mutateAsync: vi.fn().mockResolvedValue({}),
    isPending: false,
  }),
  useUpdateWidgetConfig: () => ({
    mutateAsync: vi.fn().mockResolvedValue({}),
    isPending: false,
  }),
  useWidgetData: () => ({
    data: { value: 100, label: 'Test Widget' },
    isLoading: false,
    error: null,
  }),
}));

// Mock i18n context
vi.mock('../../../app/i18n-context', () => ({
  useI18n: () => ({
    t: (key: string, options?: { defaultValue?: string }) => options?.defaultValue || key,
  }),
}));

// Mock React Query
vi.mock('@tanstack/react-query', () => ({
  useQueryClient: () => ({
    invalidateQueries: vi.fn(),
  }),
}));

const mockWidgets: DashboardWidget[] = [
  {
    id: '1',
    type: 'kpi',
    title: 'Test KPI',
    description: 'Test KPI Widget',
    size: 'medium',
    position: { x: 0, y: 0, w: 2, h: 1 },
    config: { metric: 'total_projects' },
    permissions: ['read'],
    createdAt: '2024-01-01T00:00:00Z',
    updatedAt: '2024-01-01T00:00:00Z',
  },
  {
    id: '2',
    type: 'chart',
    title: 'Test Chart',
    description: 'Test Chart Widget',
    size: 'large',
    position: { x: 2, y: 0, w: 2, h: 2 },
    config: { chartType: 'line' },
    permissions: ['read'],
    createdAt: '2024-01-01T00:00:00Z',
    updatedAt: '2024-01-01T00:00:00Z',
  },
];

describe('WidgetGrid', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('Rendering', () => {
    it('should render widgets', () => {
      render(<WidgetGrid widgets={mockWidgets} editable={false} />);

      expect(screen.getByText('Test KPI')).toBeInTheDocument();
      expect(screen.getByText('Test Chart')).toBeInTheDocument();
    });

    it('should show add widget section when editable', () => {
      render(<WidgetGrid widgets={mockWidgets} editable={true} />);

      expect(screen.getByText('Add widget:')).toBeInTheDocument();
    });

    it('should not show add widget section when not editable', () => {
      render(<WidgetGrid widgets={mockWidgets} editable={false} />);

      expect(screen.queryByText('Add widget:')).not.toBeInTheDocument();
    });
  });

  describe('Widget Actions', () => {
    it('should show widget type buttons in edit mode', () => {
      render(<WidgetGrid widgets={mockWidgets} editable={true} />);

      expect(screen.getByText('KPI Card')).toBeInTheDocument();
      expect(screen.getByText('Chart')).toBeInTheDocument();
    });

    it('should not show widget type buttons when not editable', () => {
      render(<WidgetGrid widgets={mockWidgets} editable={false} />);

      expect(screen.queryByText('KPI Card')).not.toBeInTheDocument();
    });

    it('should show remove button for widgets in edit mode', () => {
      render(<WidgetGrid widgets={mockWidgets} editable={true} />);

      const removeButtons = screen.getAllByLabelText('Remove widget');
      expect(removeButtons).toHaveLength(2);
    });

    it('should not show remove buttons when not editable', () => {
      render(<WidgetGrid widgets={mockWidgets} editable={false} />);

      expect(screen.queryByLabelText('Remove widget')).not.toBeInTheDocument();
    });
  });

  describe('Empty State', () => {
    it('should show empty state when no widgets', () => {
      render(<WidgetGrid widgets={[]} editable={false} />);

      expect(screen.getByText('No widgets yet')).toBeInTheDocument();
      expect(screen.getByText('Add widgets to customize your dashboard')).toBeInTheDocument();
    });

    it('should show add widget button in empty state when editable', () => {
      render(<WidgetGrid widgets={[]} editable={true} />);

      expect(screen.getByText('KPI Card')).toBeInTheDocument();
    });
  });
});

describe('Widget Registry', () => {
  describe('Widget Creation', () => {
    it('should create widget with correct type', () => {
      const widget = createWidget('kpi');

      expect(widget.type).toBe('kpi');
      expect(widget.title).toBe('KPI Card');
      expect(widget.description).toBe('Display key performance indicators with trends');
      expect(widget.size).toBe('medium');
    });

    it('should create widget with overrides', () => {
      const widget = createWidget('kpi', {
        title: 'Custom KPI',
        size: 'large',
        config: { metric: 'custom_metric' },
      });

      expect(widget.title).toBe('Custom KPI');
      expect(widget.size).toBe('large');
      expect(widget.config).toEqual({ metric: 'custom_metric' });
    });

    it('should throw error for unknown widget type', () => {
      expect(() => createWidget('unknown' as any)).toThrow('Widget type "unknown" is not registered');
    });
  });

  describe('Widget Registry', () => {
    it('should have all required widget types', () => {
      const requiredTypes = ['kpi', 'chart', 'table', 'list', 'progress', 'alert', 'activity', 'calendar'];
      
      requiredTypes.forEach(type => {
        expect(widgetRegistry.has(type)).toBe(true);
      });
    });

    it('should have correct widget metadata', () => {
      const kpiEntry = widgetRegistry.get('kpi');
      
      expect(kpiEntry).toBeDefined();
      expect(kpiEntry?.title).toBe('KPI Card');
      expect(kpiEntry?.description).toBe('Display key performance indicators with trends');
      expect(kpiEntry?.defaultSize).toBe('medium');
      expect(kpiEntry?.category).toBe('metrics');
    });
  });
});
