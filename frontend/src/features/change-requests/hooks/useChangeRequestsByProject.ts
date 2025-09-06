import { useQuery } from '@tanstack/react-query';
import { getChangeRequestsByProject } from '../api/changeRequestsApi';
import { ChangeRequest, ChangeRequestFilters } from '../types/changeRequest';

/**
 * Hook để lấy change requests theo project
 */
export const useChangeRequestsByProject = (
  projectId: string, 
  filters?: Omit<ChangeRequestFilters, 'project_id'>,
  enabled: boolean = true
) => {
  const {
    data: changeRequestsData,
    isLoading,
    error,
    refetch
  } = useQuery({
    queryKey: ['changeRequestsByProject', projectId, filters],
    queryFn: () => getChangeRequestsByProject(projectId, filters),
    enabled: enabled && !!projectId,
    staleTime: 3 * 60 * 1000, // 3 phút
    cacheTime: 8 * 60 * 1000, // 8 phút
  });

  return {
    changeRequests: changeRequestsData?.data || [],
    pagination: changeRequestsData?.pagination,
    isLoading,
    error,
    refetch,
    // Helper để group theo status
    draftRequests: changeRequestsData?.data?.filter(cr => cr.status === 'draft') || [],
    pendingRequests: changeRequestsData?.data?.filter(cr => cr.status === 'awaiting_approval') || [],
    approvedRequests: changeRequestsData?.data?.filter(cr => cr.status === 'approved') || [],
    rejectedRequests: changeRequestsData?.data?.filter(cr => cr.status === 'rejected') || [],
  };
};