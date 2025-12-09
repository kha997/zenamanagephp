import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { projectsApi, templateSetsApi } from './api';
import type { Project, ProjectFilters, ApplyTemplatePayload, ProjectTask, ProjectTaskUpdatePayload } from './api';
import { invalidateFor, createInvalidationContext } from '@/shared/api/invalidateMap';
import { useAuthStore } from '@/features/auth/store';

export const useProjects = (filters?: ProjectFilters, pagination?: { page?: number; per_page?: number }) => {
  // Use reactive selector instead of non-reactive method
  // This ensures component re-renders when currentTenantPermissions changes
  const canViewProjects = useAuthStore((state) => 
    (state.currentTenantPermissions ?? []).includes('tenant.view_projects')
  );
  
  return useQuery({
    queryKey: ['projects', filters, pagination],
    queryFn: () => projectsApi.getProjects(filters, pagination),
    enabled: canViewProjects, // Only fetch if user has tenant.view_projects permission
  });
};

export const useProject = (id: string | number) => {
  console.log('[useProject] Hook called with id:', id, 'enabled:', !!id);
  return useQuery({
    queryKey: ['project', id],
    queryFn: () => {
      console.log('[useProject] Fetching project with id:', id);
      return projectsApi.getProject(id);
    },
    enabled: !!id,
  });
};

export const useCreateProject = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (data: Partial<Project>) => projectsApi.createProject(data),
    onSuccess: () => {
      invalidateFor('project.create', createInvalidationContext(queryClient));
    },
  });
};

export const useUpdateProject = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ id, data }: { id: string | number; data: Partial<Project> }) =>
      projectsApi.updateProject(id, data),
    onSuccess: (_, variables) => {
      invalidateFor('project.update', createInvalidationContext(queryClient, {
        resourceId: variables.id,
      }));
    },
  });
};

export const useDeleteProject = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string | number) => projectsApi.deleteProject(id),
    onSuccess: (_, id) => {
      invalidateFor('project.delete', createInvalidationContext(queryClient, {
        resourceId: id,
      }));
    },
  });
};

export const useProjectsKpis = (period?: string) => {
  return useQuery({
    queryKey: ['projects', 'kpis', period],
    queryFn: () => projectsApi.getKpis(period),
  });
};

export const useProjectsAlerts = () => {
  return useQuery({
    queryKey: ['projects', 'alerts'],
    queryFn: () => projectsApi.getAlerts(),
  });
};

export const useProjectsActivity = (limit?: number) => {
  return useQuery({
    queryKey: ['projects', 'activity', limit],
    queryFn: () => projectsApi.getActivity(limit),
  });
};

export const useProjectTasks = (projectId: string | number, filters?: { status?: string; search?: string }, pagination?: { page?: number; per_page?: number }) => {
  return useQuery({
    queryKey: ['projects', projectId, 'tasks', filters, pagination],
    queryFn: () => projectsApi.getProjectTasks(projectId, filters, pagination),
    enabled: !!projectId,
  });
};

/**
 * Hook to fetch ProjectTasks (checklist tasks from templates)
 * 
 * Round 203: ProjectTasks checklist view
 * These are the checklist tasks auto-generated from TaskTemplates when creating a project from a template.
 */
export const useProjectChecklistTasks = (
  projectId: string | number,
  filters?: {
    status?: string;
    is_milestone?: boolean;
    is_hidden?: boolean;
    search?: string;
  },
  pagination?: { page?: number; per_page?: number }
) => {
  return useQuery({
    queryKey: ['projects', projectId, 'checklist-tasks', filters, pagination],
    queryFn: () => projectsApi.listProjectTasks(projectId, filters, pagination),
    enabled: !!projectId,
  });
};

/**
 * Hook to fetch my tasks (tasks assigned to current user)
 * 
 * Round 213: My Tasks
 * Round 217: Added range filter support
 */
export const useMyTasks = (filters?: { 
  status?: 'open' | 'completed' | 'all';
  range?: 'today' | 'next_7_days' | 'overdue' | 'all';
}) => {
  return useQuery({
    queryKey: ['my-tasks', filters],
    queryFn: () => projectsApi.listMyTasks(filters),
    staleTime: 30 * 1000, // 30 seconds
  });
};

/**
 * Query key factory for project tasks
 * 
 * Round 207: Centralized query key for project tasks
 */
const projectTasksKey = (projectId: string | number) => ['projects', projectId, 'checklist-tasks'];

/**
 * Update ProjectTask mutation hook
 * 
 * Round 207: Update task fields (name, description, status, due_date, sort_order, is_milestone)
 */
export const useUpdateProjectTask = (projectId: string | number) => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (params: { taskId: string; payload: ProjectTaskUpdatePayload }) =>
      projectsApi.updateProjectTask(projectId, params.taskId, params.payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: projectTasksKey(projectId) });
    },
  });
};

/**
 * Complete ProjectTask mutation hook
 * 
 * Round 207: Mark task as completed with timestamp
 */
export const useCompleteProjectTask = (projectId: string | number) => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (taskId: string) => projectsApi.completeProjectTask(projectId, taskId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: projectTasksKey(projectId) });
    },
  });
};

/**
 * Mark ProjectTask as incomplete mutation hook
 * 
 * Round 207: Mark task as incomplete, clear completion timestamp
 */
export const useIncompleteProjectTask = (projectId: string | number) => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (taskId: string) => projectsApi.incompleteProjectTask(projectId, taskId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: projectTasksKey(projectId) });
    },
  });
};

/**
 * Reorder ProjectTasks mutation hook
 * 
 * Round 210: Reorder tasks within a project by updating sort_order
 */
export const useReorderProjectTasks = (projectId: string | number) => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (payload: { orderedIds: string[] }) =>
      projectsApi.reorderProjectTasks(projectId, payload),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: projectTasksKey(projectId) });
    },
  });
};

export const useProjectDocuments = (projectId: string | number, filters?: { category?: string; status?: string; search?: string }, pagination?: { page?: number; per_page?: number }) => {
  return useQuery({
    queryKey: ['projects', projectId, 'documents', filters, pagination],
    queryFn: () => projectsApi.getProjectDocuments(projectId, filters, pagination),
    enabled: !!projectId,
  });
};

export const useProjectHistory = (projectId: string | number, filters?: { action?: string; entity_type?: string; entity_id?: string; limit?: number }) => {
  return useQuery({
    queryKey: ['projects', projectId, 'history', filters],
    queryFn: () => projectsApi.getProjectHistory(projectId, filters),
    enabled: !!projectId,
  });
};

/**
 * Round 231: Cost Workflow Timeline Hooks
 * 
 * Hooks for fetching workflow timeline for cost entities (Change Orders, Certificates, Payments)
 */

// Entity type constants matching backend
const ENTITY_CHANGE_ORDER = 'ChangeOrder';
const ENTITY_PAYMENT_CERTIFICATE = 'ContractPaymentCertificate';
const ENTITY_ACTUAL_PAYMENT = 'ContractActualPayment';

/**
 * Hook to fetch workflow timeline for a specific Change Order
 */
export const useChangeOrderWorkflowTimeline = (
  projectId: string | number,
  contractId: string | number,
  changeOrderId: string | number
) => {
  return useQuery({
    queryKey: ['projects', projectId, 'cost-workflow', 'change-order', changeOrderId],
    queryFn: async () => {
      const response = await projectsApi.getProjectHistory(projectId, {
        entity_type: ENTITY_CHANGE_ORDER,
        entity_id: String(changeOrderId),
        limit: 50,
      });
      // Extract data array from response
      const items = (response as any).data ?? (Array.isArray(response) ? response : []);
      return Array.isArray(items) ? items : [];
    },
    enabled: !!projectId && !!changeOrderId,
  });
};

/**
 * Hook to fetch workflow timeline for a specific Payment Certificate
 */
export const useCertificateWorkflowTimeline = (
  projectId: string | number,
  contractId: string | number,
  certificateId: string | number
) => {
  return useQuery({
    queryKey: ['projects', projectId, 'cost-workflow', 'certificate', certificateId],
    queryFn: async () => {
      const response = await projectsApi.getProjectHistory(projectId, {
        entity_type: ENTITY_PAYMENT_CERTIFICATE,
        entity_id: String(certificateId),
        limit: 50,
      });
      // Extract data array from response
      const items = (response as any).data ?? (Array.isArray(response) ? response : []);
      return Array.isArray(items) ? items : [];
    },
    enabled: !!projectId && !!certificateId,
  });
};

/**
 * Hook to fetch workflow timeline for a specific Payment
 */
export const usePaymentWorkflowTimeline = (
  projectId: string | number,
  contractId: string | number,
  paymentId: string | number
) => {
  return useQuery({
    queryKey: ['projects', projectId, 'cost-workflow', 'payment', paymentId],
    queryFn: async () => {
      const response = await projectsApi.getProjectHistory(projectId, {
        entity_type: ENTITY_ACTUAL_PAYMENT,
        entity_id: String(paymentId),
        limit: 50,
      });
      // Extract data array from response
      const items = (response as any).data ?? (Array.isArray(response) ? response : []);
      return Array.isArray(items) ? items : [];
    },
    enabled: !!projectId && !!paymentId,
  });
};

export const useArchiveProject = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: (id: string | number) => projectsApi.archiveProject(id),
    onSuccess: (_, id) => {
      invalidateFor('project.archive', createInvalidationContext(queryClient, {
        resourceId: id,
      }));
    },
  });
};

export const useAddTeamMember = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, userId, roleId }: { projectId: string | number; userId: string | number; roleId?: string | number }) =>
      projectsApi.addTeamMember(projectId, userId, roleId),
    onSuccess: (_, variables) => {
      invalidateFor('project.addTeamMember', createInvalidationContext(queryClient, {
        resourceId: variables.projectId,
      }));
    },
  });
};

export const useRemoveTeamMember = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, userId }: { projectId: string | number; userId: string | number }) =>
      projectsApi.removeTeamMember(projectId, userId),
    onSuccess: (_, variables) => {
      invalidateFor('project.removeTeamMember', createInvalidationContext(queryClient, {
        resourceId: variables.projectId,
      }));
    },
  });
};

export const useTeamMembers = (projectId: string | number) => {
  return useQuery({
    queryKey: ['projects', projectId, 'team-members'],
    queryFn: () => projectsApi.getTeamMembers(projectId),
    enabled: !!projectId,
  });
};

export const useUploadProjectDocument = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, formData }: { projectId: string | number; formData: FormData }) =>
      projectsApi.uploadProjectDocument(projectId, formData),
    onSuccess: (_, variables) => {
      // Invalidate project documents list
      queryClient.invalidateQueries({ queryKey: ['projects', variables.projectId, 'documents'] });
      // Also use the invalidation map for consistency
      invalidateFor('project.uploadDocument', createInvalidationContext(queryClient, {
        resourceId: variables.projectId,
      }));
    },
  });
};

/**
 * Update project document metadata
 * 
 * Round 184: Frontend project document edit integration
 */
export const useUpdateProjectDocument = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({
      projectId,
      documentId,
      payload,
    }: {
      projectId: string | number;
      documentId: string | number;
      payload: {
        name?: string;
        description?: string;
        category?: 'general' | 'contract' | 'drawing' | 'specification' | 'report' | 'other';
        status?: 'active' | 'archived' | 'draft';
      };
    }) => projectsApi.updateProjectDocument(projectId, documentId, payload),
    onSuccess: (_, variables) => {
      // Invalidate project documents list
      queryClient.invalidateQueries({ queryKey: ['projects', variables.projectId, 'documents'] });
      // Also use the invalidation map for consistency
      invalidateFor('project.updateDocument', createInvalidationContext(queryClient, {
        resourceId: variables.projectId,
      }));
    },
  });
};

/**
 * Delete project document
 * 
 * Round 184: Frontend project document delete integration
 */
export const useDeleteProjectDocument = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({
      projectId,
      documentId,
    }: {
      projectId: string | number;
      documentId: string | number;
    }) => projectsApi.deleteProjectDocument(projectId, documentId),
    onSuccess: (_, variables) => {
      // Invalidate project documents list
      queryClient.invalidateQueries({ queryKey: ['projects', variables.projectId, 'documents'] });
      // Also use the invalidation map for consistency
      invalidateFor('project.deleteDocument', createInvalidationContext(queryClient, {
        resourceId: variables.projectId,
      }));
    },
  });
};

/**
 * Get document versions
 * 
 * Round 187: Document Versioning (View & Download Version)
 */
export const useDocumentVersions = (projectId: string | number, documentId: string | number) => {
  return useQuery({
    queryKey: ['projectDocumentVersions', projectId, documentId],
    queryFn: () => projectsApi.getDocumentVersions(projectId, documentId),
    enabled: !!projectId && !!documentId,
  });
};

/**
 * Upload new version for an existing project document
 * 
 * Round 188: Frontend Document Versioning: Upload New Version
 */
export const useUploadDocumentVersion = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ projectId, documentId, formData }: {
      projectId: string | number;
      documentId: string | number;
      formData: FormData;
    }) => projectsApi.uploadDocumentVersion(projectId, documentId, formData),
    onSuccess: (_, variables) => {
      // Refresh versions list
      queryClient.invalidateQueries({
        queryKey: ['projectDocumentVersions', variables.projectId, variables.documentId],
      });

      // Refresh main documents list (vì document được update)
      queryClient.invalidateQueries({
        queryKey: ['projects', variables.projectId, 'documents'],
      });

      // Use invalidateFor for consistency
      invalidateFor(
        'project.uploadDocumentVersion',
        createInvalidationContext(queryClient, {
          resourceId: variables.projectId,
        })
      );
    },
  });
};

/**
 * Restore document to a specific version
 * 
 * Round 189: Restore Document Version
 */
export const useRestoreDocumentVersion = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      projectId,
      documentId,
      versionId,
    }: {
      projectId: string | number;
      documentId: string | number;
      versionId: string | number;
    }) => projectsApi.restoreDocumentVersion(projectId, documentId, versionId),
    onSuccess: (_, variables) => {
      // Refresh versions list
      queryClient.invalidateQueries({
        queryKey: ['projectDocumentVersions', variables.projectId, variables.documentId],
      });

      // Refresh documents list (current document metadata may change)
      queryClient.invalidateQueries({
        queryKey: ['projects', variables.projectId, 'documents'],
      });

      // Use invalidateFor for consistency
      invalidateFor(
        'project.restoreDocumentVersion',
        createInvalidationContext(queryClient, {
          resourceId: variables.projectId,
        })
      );
    },
  });
};

export const useProjectKpis = (projectId: string | number) => {
  return useQuery({
    queryKey: ['project', projectId, 'kpis'],
    queryFn: () => projectsApi.getProjectKpis(projectId),
    enabled: !!projectId,
  });
};

export const useProjectAlerts = (projectId: string | number) => {
  return useQuery({
    queryKey: ['project', projectId, 'alerts'],
    queryFn: () => projectsApi.getProjectAlerts(projectId),
    enabled: !!projectId,
  });
};

export const useProjectOverview = (projectId: string | number | undefined) => {
  return useQuery({
    queryKey: ['project-overview', projectId],
    queryFn: () => {
      if (!projectId) {
        throw new Error('Missing projectId');
      }
      return projectsApi.getProjectOverview(projectId);
    },
    enabled: !!projectId,
  });
};

export interface UseProjectHealthHistoryOptions {
  enabled?: boolean;
  limit?: number;
}

export const useProjectHealthHistory = (projectId: string | number | undefined, options?: UseProjectHealthHistoryOptions) => {
  const enabled = !!projectId && (options?.enabled ?? true);

  return useQuery({
    queryKey: ['project-health-history', projectId, options?.limit ?? 30],
    enabled,
    queryFn: async ({ signal }) => {
      if (!projectId) return [];
      return projectsApi.getProjectHealthHistory(projectId, { limit: options?.limit, signal });
    },
  });
};

/**
 * Template Sets Hooks
 * 
 * Round 99: Apply Template Set to Project
 */
export const useTemplateSets = (options?: { enabled?: boolean; filters?: { search?: string; is_active?: boolean } }) => {
  return useQuery({
    queryKey: ['template-sets', options?.filters],
    queryFn: () => templateSetsApi.listTemplateSets(options?.filters),
    enabled: options?.enabled ?? true,
  });
};

export const useTemplateSetDetail = (setId: string | null, options?: { enabled?: boolean }) => {
  return useQuery({
    queryKey: ['template-set-detail', setId],
    queryFn: () => {
      if (!setId) throw new Error('Template set ID is required');
      return templateSetsApi.getTemplateSetDetail(setId);
    },
    enabled: (options?.enabled ?? true) && !!setId,
  });
};

export const useApplyTemplateToProject = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({
      projectId,
      payload,
      idempotencyKey,
      signal,
    }: {
      projectId: string | number;
      payload: ApplyTemplatePayload;
      idempotencyKey: string;
      signal?: AbortSignal;
    }) => templateSetsApi.applyTemplateToProject(projectId, payload, idempotencyKey, signal),
    onSuccess: (_, variables) => {
      // Invalidate project tasks and overview to refresh the UI
      queryClient.invalidateQueries({ queryKey: ['projects', variables.projectId, 'tasks'] });
      queryClient.invalidateQueries({ queryKey: ['project-overview', variables.projectId] });
      queryClient.invalidateQueries({ queryKey: ['project', variables.projectId] });
    },
  });
};

/**
 * Get project cost dashboard
 * 
 * Round 224: Project Cost Dashboard Frontend
 */
export const useProjectCostDashboard = (projectId: string | number | undefined) => {
  return useQuery({
    queryKey: ['projectCostDashboard', projectId],
    queryFn: () => {
      if (!projectId) {
        throw new Error('Missing projectId');
      }
      return projectsApi.getProjectCostDashboard(projectId);
    },
    enabled: !!projectId,
  });
};

/**
 * Get project cost health
 * 
 * Round 226: Project Cost Health Status + Alert Indicators
 */
export const useProjectCostHealth = (projectId: string | number | undefined) => {
  return useQuery({
    queryKey: ['projectCostHealth', projectId],
    queryFn: () => {
      if (!projectId) {
        throw new Error('Missing projectId');
      }
      return projectsApi.getProjectCostHealth(projectId);
    },
    enabled: !!projectId,
  });
};

/**
 * Get project cost alerts
 * 
 * Round 227: Cost Alerts System (Nagging & Attention Flags)
 */
export const useProjectCostAlerts = (projectId: string | number | undefined) => {
  return useQuery({
    queryKey: ['projectCostAlerts', projectId],
    queryFn: () => {
      if (!projectId) {
        throw new Error('Missing projectId');
      }
      return projectsApi.getProjectCostAlerts(projectId);
    },
    enabled: !!projectId,
  });
};

/**
 * Get project cost flow status
 * 
 * Round 232: Project Cost Flow Status
 */
export const useProjectCostFlowStatus = (projectId: string | number | undefined) => {
  return useQuery({
    queryKey: ['projectCostFlowStatus', projectId],
    queryFn: () => {
      if (!projectId) {
        throw new Error('Missing projectId');
      }
      return projectsApi.getProjectCostFlowStatus(projectId);
    },
    enabled: !!projectId,
    refetchInterval: 60000, // Refetch every 60 seconds
  });
};

/**
 * Get project contracts
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export const useProjectContracts = (projectId: string | number | undefined) => {
  return useQuery({
    queryKey: ['contracts', projectId],
    queryFn: () => {
      if (!projectId) {
        throw new Error('Missing projectId');
      }
      return projectsApi.getProjectContracts(projectId);
    },
    enabled: !!projectId,
  });
};

/**
 * Get contract detail
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export const useContractDetail = (projectId: string | number | undefined, contractId: string | number | undefined) => {
  return useQuery({
    queryKey: ['contractDetail', projectId, contractId],
    queryFn: () => {
      if (!projectId || !contractId) {
        throw new Error('Missing projectId or contractId');
      }
      return projectsApi.getContractDetail(projectId, contractId);
    },
    enabled: !!projectId && !!contractId,
  });
};

/**
 * Get contract change orders
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export const useContractChangeOrders = (projectId: string | number | undefined, contractId: string | number | undefined) => {
  return useQuery({
    queryKey: ['contractChangeOrders', projectId, contractId],
    queryFn: () => {
      if (!projectId || !contractId) {
        throw new Error('Missing projectId or contractId');
      }
      return projectsApi.getContractChangeOrders(projectId, contractId);
    },
    enabled: !!projectId && !!contractId,
  });
};

/**
 * Get change order detail
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export const useChangeOrderDetail = (projectId: string | number | undefined, contractId: string | number | undefined, coId: string | number | undefined) => {
  return useQuery({
    queryKey: ['changeOrderDetail', projectId, contractId, coId],
    queryFn: () => {
      if (!projectId || !contractId || !coId) {
        throw new Error('Missing projectId, contractId, or coId');
      }
      return projectsApi.getChangeOrderDetail(projectId, contractId, coId);
    },
    enabled: !!projectId && !!contractId && !!coId,
  });
};

/**
 * Get contract payment certificates
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export const useContractPaymentCertificates = (projectId: string | number | undefined, contractId: string | number | undefined) => {
  return useQuery({
    queryKey: ['contractPaymentCertificates', projectId, contractId],
    queryFn: () => {
      if (!projectId || !contractId) {
        throw new Error('Missing projectId or contractId');
      }
      return projectsApi.getContractPaymentCertificates(projectId, contractId);
    },
    enabled: !!projectId && !!contractId,
  });
};

/**
 * Get contract payments
 * 
 * Round 225: Contract & Change Order Drilldown
 */
export const useContractPayments = (projectId: string | number | undefined, contractId: string | number | undefined) => {
  return useQuery({
    queryKey: ['contractPayments', projectId, contractId],
    queryFn: () => {
      if (!projectId || !contractId) {
        throw new Error('Missing projectId or contractId');
      }
      return projectsApi.getContractPayments(projectId, contractId);
    },
    enabled: !!projectId && !!contractId,
  });
};

/**
 * Workflow mutation hooks - Round 230
 */

/**
 * Propose change order (draft → proposed)
 */
export const useProposeChangeOrder = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, contractId, coId }: { projectId: string | number; contractId: string | number; coId: string | number }) =>
      projectsApi.proposeChangeOrder(projectId, contractId, coId),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['changeOrderDetail', variables.projectId, variables.contractId, variables.coId] });
      queryClient.invalidateQueries({ queryKey: ['contractChangeOrders', variables.projectId, variables.contractId] });
      queryClient.invalidateQueries({ queryKey: ['contractDetail', variables.projectId, variables.contractId] });
    },
  });
};

/**
 * Approve change order (proposed → approved)
 */
export const useApproveChangeOrder = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, contractId, coId }: { projectId: string | number; contractId: string | number; coId: string | number }) =>
      projectsApi.approveChangeOrder(projectId, contractId, coId),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['changeOrderDetail', variables.projectId, variables.contractId, variables.coId] });
      queryClient.invalidateQueries({ queryKey: ['contractChangeOrders', variables.projectId, variables.contractId] });
      queryClient.invalidateQueries({ queryKey: ['contractDetail', variables.projectId, variables.contractId] });
      queryClient.invalidateQueries({ queryKey: ['projectCostDashboard', variables.projectId] });
    },
  });
};

/**
 * Reject change order (proposed → rejected)
 */
export const useRejectChangeOrder = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, contractId, coId }: { projectId: string | number; contractId: string | number; coId: string | number }) =>
      projectsApi.rejectChangeOrder(projectId, contractId, coId),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['changeOrderDetail', variables.projectId, variables.contractId, variables.coId] });
      queryClient.invalidateQueries({ queryKey: ['contractChangeOrders', variables.projectId, variables.contractId] });
      queryClient.invalidateQueries({ queryKey: ['contractDetail', variables.projectId, variables.contractId] });
    },
  });
};

/**
 * Submit payment certificate (draft → submitted)
 */
export const useSubmitPaymentCertificate = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, contractId, certificateId }: { projectId: string | number; contractId: string | number; certificateId: string | number }) =>
      projectsApi.submitPaymentCertificate(projectId, contractId, certificateId),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['contractPaymentCertificates', variables.projectId, variables.contractId] });
      queryClient.invalidateQueries({ queryKey: ['contractDetail', variables.projectId, variables.contractId] });
    },
  });
};

/**
 * Approve payment certificate (submitted → approved)
 */
export const useApprovePaymentCertificate = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, contractId, certificateId }: { projectId: string | number; contractId: string | number; certificateId: string | number }) =>
      projectsApi.approvePaymentCertificate(projectId, contractId, certificateId),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['contractPaymentCertificates', variables.projectId, variables.contractId] });
      queryClient.invalidateQueries({ queryKey: ['contractDetail', variables.projectId, variables.contractId] });
      queryClient.invalidateQueries({ queryKey: ['projectCostDashboard', variables.projectId] });
    },
  });
};

/**
 * Mark payment as paid (planned → paid)
 */
export const useMarkPaymentPaid = () => {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: ({ projectId, contractId, paymentId }: { projectId: string | number; contractId: string | number; paymentId: string | number }) =>
      projectsApi.markPaymentPaid(projectId, contractId, paymentId),
    onSuccess: (_, variables) => {
      queryClient.invalidateQueries({ queryKey: ['contractPayments', variables.projectId, variables.contractId] });
      queryClient.invalidateQueries({ queryKey: ['contractDetail', variables.projectId, variables.contractId] });
      queryClient.invalidateQueries({ queryKey: ['projectCostDashboard', variables.projectId] });
    },
  });
};

