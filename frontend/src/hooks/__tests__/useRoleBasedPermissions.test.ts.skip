import { renderHook, act } from '@testing-library/react';
import { useRoleBasedPermissions, getRoleColor, getRoleIcon, getRoleDisplayName, getRoleDescription } from '../useRoleBasedPermissions';
import { useAuth } from '../useAuth';

// Mock the useAuth hook
jest.mock('../useAuth');

const mockUseAuth = useAuth as jest.MockedFunction<typeof useAuth>;

// Mock fetch
global.fetch = jest.fn();

// Mock localStorage
Object.defineProperty(window, 'localStorage', {
  value: {
    getItem: jest.fn(() => 'mock-token'),
    setItem: jest.fn(),
    removeItem: jest.fn()
  },
  writable: true
});

const mockPermissions = {
  dashboard: ['view', 'edit', 'share'],
  widgets: ['view', 'add', 'edit', 'configure'],
  projects: ['view_assigned', 'edit_assigned'],
  users: ['view_team', 'edit_team'],
  reports: ['view_assigned', 'export_assigned'],
  settings: ['view_project', 'edit_project']
};

const mockRoleConfig = {
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
};

describe('useRoleBasedPermissions', () => {
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

    // Mock fetch
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({
        success: true,
        data: {
          permissions: mockPermissions,
          role_config: mockRoleConfig
        }
      })
    });
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  it('loads permissions and role config on mount', async () => {
    const { result } = renderHook(() => useRoleBasedPermissions());

    await act(async () => {
      await new Promise(resolve => setTimeout(resolve, 0));
    });

    expect(result.current.permissions).toEqual(mockPermissions);
    expect(result.current.roleConfig).toEqual(mockRoleConfig);
    expect(result.current.userRole).toBe('project_manager');
    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBeNull();
  });

  it('handles permission checking correctly', async () => {
    const { result } = renderHook(() => useRoleBasedPermissions());

    await act(async () => {
      await new Promise(resolve => setTimeout(resolve, 0));
    });

    // Test hasPermission
    expect(result.current.hasPermission('dashboard', 'view')).toBe(true);
    expect(result.current.hasPermission('dashboard', 'edit')).toBe(true);
    expect(result.current.hasPermission('dashboard', 'delete')).toBe(false);
    expect(result.current.hasPermission('widgets', 'add')).toBe(true);
    expect(result.current.hasPermission('projects', 'view_all')).toBe(false);

    // Test canAccessWidget
    expect(result.current.canAccessWidget('project_overview')).toBe(true);
    expect(result.current.canAccessWidget('task_progress')).toBe(true);
    expect(result.current.canAccessWidget('inspection_schedule')).toBe(false);

    // Test canCustomizeDashboard
    expect(result.current.canCustomizeDashboard()).toBe(true);

    // Test canViewProject
    expect(result.current.canViewProject('project-1')).toBe(true);

    // Test canEditProject
    expect(result.current.canEditProject('project-1')).toBe(true);

    // Test canViewReports
    expect(result.current.canViewReports()).toBe(true);

    // Test canExportData
    expect(result.current.canExportData()).toBe(true);

    // Test canManageUsers
    expect(result.current.canManageUsers()).toBe(true);

    // Test canAccessSettings
    expect(result.current.canAccessSettings()).toBe(true);
  });

  it('handles different user roles correctly', async () => {
    // Test QC Inspector role
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

    const qcPermissions = {
      dashboard: ['view'],
      widgets: ['view', 'configure'],
      projects: ['view_assigned'],
      users: ['view_team'],
      reports: ['view_quality', 'export_quality'],
      settings: ['view_quality']
    };

    const qcRoleConfig = {
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
    };

    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({
        success: true,
        data: {
          permissions: qcPermissions,
          role_config: qcRoleConfig
        }
      })
    });

    const { result } = renderHook(() => useRoleBasedPermissions());

    await act(async () => {
      await new Promise(resolve => setTimeout(resolve, 0));
    });

    expect(result.current.permissions).toEqual(qcPermissions);
    expect(result.current.roleConfig).toEqual(qcRoleConfig);
    expect(result.current.userRole).toBe('qc_inspector');
    expect(result.current.hasPermission('dashboard', 'edit')).toBe(false);
    expect(result.current.hasPermission('dashboard', 'view')).toBe(true);
    expect(result.current.canCustomizeDashboard()).toBe(false);
    expect(result.current.canAccessWidget('inspection_schedule')).toBe(true);
    expect(result.current.canAccessWidget('project_overview')).toBe(false);
  });

  it('handles loading state correctly', () => {
    // Mock loading state
    (global.fetch as jest.Mock).mockImplementation(() => new Promise(() => {}));

    const { result } = renderHook(() => useRoleBasedPermissions());

    expect(result.current.isLoading).toBe(true);
    expect(result.current.error).toBeNull();
  });

  it('handles error state correctly', async () => {
    // Mock error response
    (global.fetch as jest.Mock).mockResolvedValue({
      ok: false,
      json: () => Promise.resolve({
        success: false,
        message: 'Failed to load permissions'
      })
    });

    const { result } = renderHook(() => useRoleBasedPermissions());

    await act(async () => {
      await new Promise(resolve => setTimeout(resolve, 0));
    });

    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBe('Failed to load permissions');
  });

  it('handles network errors gracefully', async () => {
    // Mock network error
    (global.fetch as jest.Mock).mockRejectedValue(new Error('Network error'));

    const { result } = renderHook(() => useRoleBasedPermissions());

    await act(async () => {
      await new Promise(resolve => setTimeout(resolve, 0));
    });

    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBe('Network error');
  });

  it('refreshes permissions correctly', async () => {
    const { result } = renderHook(() => useRoleBasedPermissions());

    await act(async () => {
      await new Promise(resolve => setTimeout(resolve, 0));
    });

    // Mock updated permissions
    const updatedPermissions = {
      ...mockPermissions,
      dashboard: ['view', 'edit', 'share', 'delete']
    };

    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({
        success: true,
        data: {
          permissions: updatedPermissions,
          role_config: mockRoleConfig
        }
      })
    });

    await act(async () => {
      await result.current.refreshPermissions();
    });

    expect(result.current.permissions).toEqual(updatedPermissions);
    expect(result.current.hasPermission('dashboard', 'delete')).toBe(true);
  });

  it('handles missing user gracefully', () => {
    mockUseAuth.mockReturnValue({
      user: null,
      login: jest.fn(),
      logout: jest.fn(),
      isLoading: false,
      error: null
    });

    const { result } = renderHook(() => useRoleBasedPermissions());

    expect(result.current.userRole).toBe('');
    expect(result.current.isLoading).toBe(false);
  });

  it('handles unknown role gracefully', async () => {
    mockUseAuth.mockReturnValue({
      user: {
        id: 'user-3',
        name: 'Unknown User',
        email: 'unknown@example.com',
        role: 'unknown_role',
        tenant_id: 'tenant-1'
      },
      login: jest.fn(),
      logout: jest.fn(),
      isLoading: false,
      error: null
    });

    // Mock default role config for unknown role
    const defaultRoleConfig = {
      name: 'Client Representative',
      description: 'Client communication and project oversight',
      default_widgets: ['project_summary', 'progress_report'],
      widget_categories: ['overview', 'communication'],
      data_access: 'client_view',
      project_access: 'assigned',
      customization_level: 'read_only',
      priority_metrics: ['project_progress', 'budget_status'],
      alert_types: ['milestone', 'budget'],
      dashboard_layout: 'client_grid'
    };

    (global.fetch as jest.Mock).mockResolvedValue({
      ok: true,
      json: () => Promise.resolve({
        success: true,
        data: {
          permissions: mockPermissions,
          role_config: defaultRoleConfig
        }
      })
    });

    const { result } = renderHook(() => useRoleBasedPermissions());

    await act(async () => {
      await new Promise(resolve => setTimeout(resolve, 0));
    });

    expect(result.current.userRole).toBe('unknown_role');
    expect(result.current.roleConfig).toEqual(defaultRoleConfig);
  });
});

describe('Role Utility Functions', () => {
  describe('getRoleColor', () => {
    it('returns correct colors for known roles', () => {
      expect(getRoleColor('system_admin')).toBe('purple');
      expect(getRoleColor('project_manager')).toBe('blue');
      expect(getRoleColor('design_lead')).toBe('green');
      expect(getRoleColor('site_engineer')).toBe('orange');
      expect(getRoleColor('qc_inspector')).toBe('red');
      expect(getRoleColor('client_rep')).toBe('teal');
      expect(getRoleColor('subcontractor_lead')).toBe('gray');
    });

    it('returns default color for unknown role', () => {
      expect(getRoleColor('unknown_role')).toBe('gray');
    });
  });

  describe('getRoleIcon', () => {
    it('returns correct icons for known roles', () => {
      expect(getRoleIcon('system_admin')).toBe('crown');
      expect(getRoleIcon('project_manager')).toBe('user-tie');
      expect(getRoleIcon('design_lead')).toBe('pencil');
      expect(getRoleIcon('site_engineer')).toBe('hard-hat');
      expect(getRoleIcon('qc_inspector')).toBe('shield-check');
      expect(getRoleIcon('client_rep')).toBe('user-check');
      expect(getRoleIcon('subcontractor_lead')).toBe('users');
    });

    it('returns default icon for unknown role', () => {
      expect(getRoleIcon('unknown_role')).toBe('user');
    });
  });

  describe('getRoleDisplayName', () => {
    it('returns correct display names for known roles', () => {
      expect(getRoleDisplayName('system_admin')).toBe('System Administrator');
      expect(getRoleDisplayName('project_manager')).toBe('Project Manager');
      expect(getRoleDisplayName('design_lead')).toBe('Design Lead');
      expect(getRoleDisplayName('site_engineer')).toBe('Site Engineer');
      expect(getRoleDisplayName('qc_inspector')).toBe('QC Inspector');
      expect(getRoleDisplayName('client_rep')).toBe('Client Representative');
      expect(getRoleDisplayName('subcontractor_lead')).toBe('Subcontractor Lead');
    });

    it('returns formatted name for unknown role', () => {
      expect(getRoleDisplayName('unknown_role')).toBe('unknown role');
    });
  });

  describe('getRoleDescription', () => {
    it('returns correct descriptions for known roles', () => {
      expect(getRoleDescription('system_admin')).toBe('Full system access and management capabilities');
      expect(getRoleDescription('project_manager')).toBe('Comprehensive project management and oversight');
      expect(getRoleDescription('design_lead')).toBe('Design coordination and technical oversight');
      expect(getRoleDescription('site_engineer')).toBe('Field operations and site management');
      expect(getRoleDescription('qc_inspector')).toBe('Quality control and inspection management');
      expect(getRoleDescription('client_rep')).toBe('Client communication and project oversight');
      expect(getRoleDescription('subcontractor_lead')).toBe('Subcontractor coordination and management');
    });

    it('returns default description for unknown role', () => {
      expect(getRoleDescription('unknown_role')).toBe('User role');
    });
  });
});
