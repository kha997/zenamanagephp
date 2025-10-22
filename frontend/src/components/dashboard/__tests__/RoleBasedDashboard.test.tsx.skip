import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { ChakraProvider } from '@chakra-ui/react';
import { BrowserRouter } from 'react-router-dom';
import RoleBasedDashboard from '../role-based/RoleBasedDashboard';
import { useAuth } from '../../../hooks/useAuth';
import { useRealTimeUpdates } from '../../../hooks/useRealTimeUpdates';

// Mock the hooks
jest.mock('../../../hooks/useAuth');
jest.mock('../../../hooks/useRealTimeUpdates');

const mockUseAuth = useAuth as jest.MockedFunction<typeof useAuth>;
const mockUseRealTimeUpdates = useRealTimeUpdates as jest.MockedFunction<typeof useRealTimeUpdates>;

// Mock fetch
global.fetch = jest.fn();

const mockDashboardData = {
  dashboard: {
    id: 'dashboard-1',
    name: 'Project Manager Dashboard',
    layout: [
      {
        id: 'widget-1',
        widget_id: 'widget-1',
        type: 'card',
        title: 'Project Overview',
        size: 'large',
        position: { x: 0, y: 0 },
        config: {},
        is_customizable: true,
        created_at: '2024-01-01T00:00:00Z'
      }
    ],
    preferences: { theme: 'light' },
    is_default: true
  },
  widgets: [
    {
      widget: {
        id: 'widget-1',
        name: 'Project Overview',
        code: 'project_overview',
        type: 'card',
        category: 'overview',
        description: 'Project overview widget',
        config: {},
        permissions: ['project_manager'],
        is_active: true
      },
      data: {
        total_projects: 5,
        active_projects: 3,
        completed_projects: 2,
        total_budget: 1000000,
        spent_budget: 750000
      },
      permissions: {
        can_view: true,
        can_edit: true,
        can_delete: true,
        can_configure: true,
        can_share: true
      }
    }
  ],
  metrics: [
    {
      metric: {
        id: 'metric-1',
        name: 'Project Progress',
        code: 'project_progress',
        description: 'Overall project progress',
        unit: '%',
        type: 'gauge',
        is_active: true
      },
      value: 75.5,
      trend: 'up',
      target: 80
    }
  ],
  alerts: [
    {
      id: 'alert-1',
      type: 'project',
      severity: 'medium',
      message: 'Project milestone approaching',
      is_read: false,
      triggered_at: '2024-01-01T00:00:00Z',
      context: { project_id: 'project-1' }
    }
  ],
  permissions: {
    dashboard: ['view', 'edit', 'share'],
    widgets: ['view', 'add', 'edit', 'configure'],
    projects: ['view_assigned', 'edit_assigned'],
    users: ['view_team', 'edit_team'],
    reports: ['view_assigned', 'export_assigned'],
    settings: ['view_project', 'edit_project']
  },
  role_config: {
    name: 'Project Manager',
    description: 'Comprehensive project management and oversight',
    default_widgets: ['project_overview', 'task_progress', 'rfi_status'],
    widget_categories: ['overview', 'tasks', 'communication'],
    data_access: 'project_wide',
    project_access: 'assigned',
    customization_level: 'full',
    priority_metrics: ['project_progress', 'budget_variance'],
    alert_types: ['project', 'budget', 'schedule'],
    dashboard_layout: 'manager_grid'
  },
  project_context: {
    current_project: {
      id: 'project-1',
      name: 'Test Project',
      status: 'active',
      progress: 75,
      budget: 100000,
      start_date: '2024-01-01',
      end_date: '2024-06-30'
    },
    available_projects: [
      {
        id: 'project-1',
        name: 'Test Project',
        status: 'active'
      }
    ]
  }
};

const mockAvailableProjects = [
  {
    id: 'project-1',
    name: 'Test Project',
    status: 'active',
    progress_percentage: 75,
    budget: 100000,
    start_date: '2024-01-01',
    end_date: '2024-06-30'
  }
];

const TestWrapper: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <ChakraProvider>
    <BrowserRouter>
      {children}
    </BrowserRouter>
  </ChakraProvider>
);

describe('RoleBasedDashboard', () => {
  beforeEach(() => {
    // Mock auth hook
    mockUseAuth.mockReturnValue({
      user: {
        id: 'user-1',
        name: 'Test User',
        email: 'test@example.com',
        role: 'project_manager',
        tenant_id: 'tenant-1'
      },
      login: jest.fn(),
      logout: jest.fn(),
      isLoading: false,
      error: null
    });

    // Mock real-time updates hook
    mockUseRealTimeUpdates.mockReturnValue({
      onDashboardUpdate: jest.fn(),
      onRealTimeUpdate: jest.fn(),
      subscribeToUpdates: jest.fn(),
      unsubscribeFromUpdates: jest.fn(),
      broadcastUpdate: jest.fn()
    });

    // Mock fetch
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({
        success: true,
        data: mockDashboardData
      })
    });

    // Mock localStorage
    Object.defineProperty(window, 'localStorage', {
      value: {
        getItem: jest.fn(() => 'mock-token'),
        setItem: jest.fn(),
        removeItem: jest.fn()
      },
      writable: true
    });
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('renders role-based dashboard correctly', async () => {
    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      expect(screen.getByText('Project Manager Dashboard')).toBeInTheDocument();
      expect(screen.getByText('project manager')).toBeInTheDocument();
      expect(screen.getByText('Comprehensive project management and oversight')).toBeInTheDocument();
    });
  });

  it('displays role-specific widgets', async () => {
    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      expect(screen.getByText('Dashboard Widgets')).toBeInTheDocument();
      expect(screen.getByText('Project Overview')).toBeInTheDocument();
    });
  });

  it('shows project selector when projects are available', async () => {
    // Mock projects endpoint
    (global.fetch as jest.Mock).mockImplementation((url) => {
      if (url.includes('/projects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: { projects: mockAvailableProjects }
          })
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({
          success: true,
          data: mockDashboardData
        })
      });
    });

    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      expect(screen.getByDisplayValue('All Projects')).toBeInTheDocument();
    });
  });

  it('handles project switching', async () => {
    // Mock projects endpoint
    (global.fetch as jest.Mock).mockImplementation((url) => {
      if (url.includes('/projects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: { projects: mockAvailableProjects }
          })
        });
      }
      if (url.includes('/switch-project')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: { dashboard: mockDashboardData },
            message: 'Project context switched successfully'
          })
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({
          success: true,
          data: mockDashboardData
        })
      });
    });

    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      const projectSelect = screen.getByDisplayValue('All Projects');
      fireEvent.change(projectSelect, { target: { value: 'project-1' } });
    });

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith(
        '/api/v1/dashboard/role-based/switch-project',
        expect.objectContaining({
          method: 'POST',
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
            'Authorization': 'Bearer mock-token'
          }),
          body: JSON.stringify({ project_id: 'project-1' })
        })
      );
    });
  });

  it('shows customization button for users with edit permissions', async () => {
    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      expect(screen.getByText('Customize')).toBeInTheDocument();
    });
  });

  it('enters customization mode when customize button is clicked', async () => {
    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      const customizeButton = screen.getByText('Customize');
      fireEvent.click(customizeButton);
    });

    await waitFor(() => {
      expect(screen.getByText('Exit Customization')).toBeInTheDocument();
    });
  });

  it('displays alerts tab with unread count', async () => {
    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      expect(screen.getByText('Alerts')).toBeInTheDocument();
      expect(screen.getByText('1')).toBeInTheDocument(); // Unread alert count
    });
  });

  it('shows metrics tab', async () => {
    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      const metricsTab = screen.getByText('Metrics');
      fireEvent.click(metricsTab);
    });

    await waitFor(() => {
      expect(screen.getByText('Key Performance Metrics')).toBeInTheDocument();
      expect(screen.getByText('Project Progress')).toBeInTheDocument();
    });
  });

  it('shows projects tab', async () => {
    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      const projectsTab = screen.getByText('Projects');
      fireEvent.click(projectsTab);
    });

    await waitFor(() => {
      expect(screen.getByText('Project Overview')).toBeInTheDocument();
      expect(screen.getByText('Current Project')).toBeInTheDocument();
    });
  });

  it('handles refresh button click', async () => {
    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      const refreshButton = screen.getByLabelText('Refresh Dashboard');
      fireEvent.click(refreshButton);
    });

    await waitFor(() => {
      expect(global.fetch).toHaveBeenCalledWith(
        '/api/v1/dashboard/role-based',
        expect.objectContaining({
          headers: expect.objectContaining({
            'Authorization': 'Bearer mock-token'
          })
        })
      );
    });
  });

  it('displays loading state', () => {
    // Mock loading state
    (global.fetch as jest.Mock).mockImplementation(() => new Promise(() => {}));

    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    expect(screen.getByText('Loading role-based dashboard...')).toBeInTheDocument();
  });

  it('displays error state', async () => {
    // Mock error response
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: false,
      json: () => Promise.resolve({
        success: false,
        message: 'Failed to load dashboard'
      })
    });

    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      expect(screen.getByText('Error!')).toBeInTheDocument();
      expect(screen.getByText('Failed to load dashboard')).toBeInTheDocument();
    });
  });

  it('handles network errors gracefully', async () => {
    // Mock network error
    (global.fetch as jest.Mock).mockRejectedValue(new Error('Network error'));

    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      expect(screen.getByText('Error!')).toBeInTheDocument();
    });
  });

  it('shows role-specific quick stats', async () => {
    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      expect(screen.getByText('Total Widgets')).toBeInTheDocument();
      expect(screen.getByText('Active Alerts')).toBeInTheDocument();
      expect(screen.getByText('Key Metrics')).toBeInTheDocument();
      expect(screen.getByText('Projects')).toBeInTheDocument();
    });
  });

  it('displays widget grid with correct layout', async () => {
    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      expect(screen.getByText('Dashboard Widgets')).toBeInTheDocument();
      expect(screen.getByText('Project Overview')).toBeInTheDocument();
      expect(screen.getByText('card')).toBeInTheDocument();
    });
  });

  it('shows role badge with correct color', async () => {
    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      const roleBadge = screen.getByText('project manager');
      expect(roleBadge).toBeInTheDocument();
    });
  });

  it('handles empty dashboard data', async () => {
    // Mock empty dashboard data
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({
        success: true,
        data: {
          ...mockDashboardData,
          widgets: [],
          metrics: [],
          alerts: []
        }
      })
    });

    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      expect(screen.getByText('No dashboard data available')).toBeInTheDocument();
    });
  });

  it('handles different user roles', async () => {
    // Mock QC Inspector user
    mockUseAuth.mockReturnValue({
      user: {
        id: 'user-2',
        name: 'QC Inspector',
        email: 'qc@example.com',
        role: 'qc_inspector',
        tenant_id: 'tenant-1'
      },
      login: jest.fn(),
      logout: jest.fn(),
      isLoading: false,
      error: null
    });

    // Mock QC Inspector dashboard data
    const qcDashboardData = {
      ...mockDashboardData,
      role_config: {
        name: 'QC Inspector',
        description: 'Quality control and inspection management',
        default_widgets: ['inspection_schedule', 'ncr_tracking'],
        widget_categories: ['quality', 'inspection'],
        data_access: 'quality_related',
        project_access: 'assigned',
        customization_level: 'read_only',
        priority_metrics: ['inspection_completion', 'defect_rate'],
        alert_types: ['quality', 'inspection'],
        dashboard_layout: 'qc_grid'
      }
    };

    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({
        success: true,
        data: qcDashboardData
      })
    });

    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      expect(screen.getByText('QC Inspector Dashboard')).toBeInTheDocument();
      expect(screen.getByText('Quality control and inspection management')).toBeInTheDocument();
    });
  });

  it('handles project context changes', async () => {
    const onProjectChange = jest.fn();

    render(
      <TestWrapper>
        <RoleBasedDashboard onProjectChange={onProjectChange} />
      </TestWrapper>
    );

    // Mock project switching
    (global.fetch as jest.Mock).mockImplementation((url) => {
      if (url.includes('/switch-project')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: { dashboard: mockDashboardData },
            message: 'Project context switched successfully'
          })
        });
      }
      if (url.includes('/projects')) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve({
            success: true,
            data: { projects: mockAvailableProjects }
          })
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({
          success: true,
          data: mockDashboardData
        })
      });
    });

    await waitFor(() => {
      const projectSelect = screen.getByDisplayValue('All Projects');
      fireEvent.change(projectSelect, { target: { value: 'project-1' } });
    });

    await waitFor(() => {
      expect(onProjectChange).toHaveBeenCalledWith('project-1');
    });
  });

  it('handles real-time updates', async () => {
    const mockOnDashboardUpdate = jest.fn();
    mockUseRealTimeUpdates.mockReturnValue({
      onDashboardUpdate: mockOnDashboardUpdate,
      onRealTimeUpdate: jest.fn(),
      subscribeToUpdates: jest.fn(),
      unsubscribeFromUpdates: jest.fn(),
      broadcastUpdate: jest.fn()
    });

    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      expect(mockOnDashboardUpdate).toHaveBeenCalled();
    });
  });

  it('handles missing project context gracefully', async () => {
    const dashboardDataWithoutProject = {
      ...mockDashboardData,
      project_context: {
        current_project: null,
        available_projects: []
      }
    };

    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({
        success: true,
        data: dashboardDataWithoutProject
      })
    });

    render(
      <TestWrapper>
        <RoleBasedDashboard />
      </TestWrapper>
    );

    await waitFor(() => {
      expect(screen.getByText('Project Manager Dashboard')).toBeInTheDocument();
    });
  });
});
