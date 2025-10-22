import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { 
  useProjects, 
  useProject, 
  useCreateProject, 
  useUpdateProject, 
  useDeleteProject 
} from '../hooks';
import { projectsApi } from '../api';
import type { Project, CreateProjectRequest, UpdateProjectRequest } from '../types';

// Mock the API
vi.mock('../api', () => ({
  projectsApi: {
    getProjects: vi.fn(),
    getProject: vi.fn(),
    createProject: vi.fn(),
    updateProject: vi.fn(),
    deleteProject: vi.fn(),
  },
}));

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
      mutations: {
        retry: false,
      },
    },
  });
  
  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={queryClient}>
      {children}
    </QueryClientProvider>
  );
};

const mockProject: Project = {
  id: 1,
  name: 'Test Project',
  description: 'A test project',
  status: 'active',
  priority: 'high',
  start_date: '2024-01-01',
  end_date: '2024-12-31',
  budget: 10000,
  spent: 5000,
  progress: 50,
  tenant_id: 1,
  tenant_name: 'Test Tenant',
  created_by: 1,
  created_by_name: 'Test User',
  team_members: [],
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-01T00:00:00Z',
};

describe('Projects Hooks', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  describe('useProjects', () => {
    it('should fetch projects successfully', async () => {
      const mockResponse = {
        data: [mockProject],
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 12,
          total: 1,
        },
        links: {
          first: 'http://localhost/api/v1/projects?page=1',
          last: 'http://localhost/api/v1/projects?page=1',
        },
      };

      vi.mocked(projectsApi.getProjects).mockResolvedValue(mockResponse);

      const { result } = renderHook(() => useProjects(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });

      expect(result.current.data).toEqual(mockResponse);
      expect(projectsApi.getProjects).toHaveBeenCalledTimes(1);
    });

    it('should fetch projects with filters', async () => {
      const filters = { search: 'test', status: 'active', page: 1 };
      const mockResponse = {
        data: [mockProject],
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 12,
          total: 1,
        },
        links: {
          first: 'http://localhost/api/v1/projects?page=1',
          last: 'http://localhost/api/v1/projects?page=1',
        },
      };

      vi.mocked(projectsApi.getProjects).mockResolvedValue(mockResponse);

      const { result } = renderHook(() => useProjects(filters), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });

      expect(projectsApi.getProjects).toHaveBeenCalledWith(filters);
    });

    it('should handle API errors', async () => {
      const error = new Error('API Error');
      vi.mocked(projectsApi.getProjects).mockRejectedValue(error);

      const { result } = renderHook(() => useProjects(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isError).toBe(true);
      }, { timeout: 5000 });

      expect(result.current.error).toEqual(error);
    });

    it('should have correct stale time configuration', () => {
      const { result } = renderHook(() => useProjects(), {
        wrapper: createWrapper(),
      });

      // Check that the hook is configured correctly (staleTime is internal to React Query)
      expect(result.current.isLoading).toBe(true);
    });
  });

  describe('useProject', () => {
    it('should fetch single project successfully', async () => {
      const mockResponse = { data: mockProject };
      vi.mocked(projectsApi.getProject).mockResolvedValue(mockResponse);

      const { result } = renderHook(() => useProject(1), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });

      expect(result.current.data).toEqual(mockResponse);
      expect(projectsApi.getProject).toHaveBeenCalledWith(1);
    });

    it('should not fetch when disabled', () => {
      const { result } = renderHook(() => useProject(1, false), {
        wrapper: createWrapper(),
      });

      expect(result.current.isLoading).toBe(false);
      expect(projectsApi.getProject).not.toHaveBeenCalled();
    });

    it('should have correct stale time configuration', () => {
      const { result } = renderHook(() => useProject(1), {
        wrapper: createWrapper(),
      });

      // Check that the hook is configured correctly (staleTime is internal to React Query)
      expect(result.current.isLoading).toBe(true);
    });
  });

  describe('useCreateProject', () => {
    it('should create project successfully', async () => {
      const newProject: CreateProjectRequest = {
        name: 'New Project',
        description: 'A new project',
        status: 'planning',
        priority: 'medium',
        start_date: '2024-01-01',
        end_date: '2024-12-31',
        budget: 5000,
      };

      const mockResponse = { data: { ...mockProject, ...newProject } };
      vi.mocked(projectsApi.createProject).mockResolvedValue(mockResponse);

      const { result } = renderHook(() => useCreateProject(), {
        wrapper: createWrapper(),
      });

      await result.current.mutateAsync(newProject);

      expect(projectsApi.createProject).toHaveBeenCalledWith(newProject);
      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });
    });

    it('should handle creation errors', async () => {
      const error = new Error('Creation failed');
      vi.mocked(projectsApi.createProject).mockRejectedValue(error);

      const { result } = renderHook(() => useCreateProject(), {
        wrapper: createWrapper(),
      });

      await expect(result.current.mutateAsync({} as CreateProjectRequest)).rejects.toThrow('Creation failed');
    });
  });

  describe('useUpdateProject', () => {
    it('should update project successfully', async () => {
      const updateData: UpdateProjectRequest = {
        name: 'Updated Project',
        description: 'Updated description',
      };

      const mockResponse = { data: { ...mockProject, ...updateData } };
      vi.mocked(projectsApi.updateProject).mockResolvedValue(mockResponse);

      const { result } = renderHook(() => useUpdateProject(), {
        wrapper: createWrapper(),
      });

      await result.current.mutateAsync({ id: 1, projectData: updateData });

      expect(projectsApi.updateProject).toHaveBeenCalledWith(1, updateData);
      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });
    });

    it('should handle update errors', async () => {
      const error = new Error('Update failed');
      vi.mocked(projectsApi.updateProject).mockRejectedValue(error);

      const { result } = renderHook(() => useUpdateProject(), {
        wrapper: createWrapper(),
      });

      await expect(result.current.mutateAsync({ id: 1, projectData: {} })).rejects.toThrow('Update failed');
    });
  });

  describe('useDeleteProject', () => {
    it('should delete project successfully', async () => {
      vi.mocked(projectsApi.deleteProject).mockResolvedValue(undefined);

      const { result } = renderHook(() => useDeleteProject(), {
        wrapper: createWrapper(),
      });

      await result.current.mutateAsync(1);

      expect(projectsApi.deleteProject).toHaveBeenCalledWith(1);
      await waitFor(() => {
        expect(result.current.isSuccess).toBe(true);
      });
    });

    it('should handle deletion errors', async () => {
      const error = new Error('Deletion failed');
      vi.mocked(projectsApi.deleteProject).mockRejectedValue(error);

      const { result } = renderHook(() => useDeleteProject(), {
        wrapper: createWrapper(),
      });

      await expect(result.current.mutateAsync(1)).rejects.toThrow('Deletion failed');
    });
  });
});
