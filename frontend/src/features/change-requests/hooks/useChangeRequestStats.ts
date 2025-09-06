import { useQuery } from '@tanstack/react-query';
import { getChangeRequestStats } from '../api/changeRequestsApi';
import { ChangeRequestStats } from '../types/changeRequest';

/**
 * Hook để lấy thống kê change requests
 */
export const useChangeRequestStats = (projectId?: string) => {
  const {
    data: stats,
    isLoading,
    error,
    refetch
  } = useQuery({
    queryKey: ['changeRequestStats', projectId],
    queryFn: () => getChangeRequestStats(projectId),
    staleTime: 2 * 60 * 1000, // 2 phút
    cacheTime: 5 * 60 * 1000, // 5 phút
  });

  return {
    stats: stats?.data,
    isLoading,
    error,
    refetch,
    // Helper calculations
    totalRequests: stats?.data?.total || 0,
    pendingRequests: stats?.data?.awaiting_approval || 0,
    approvedRequests: stats?.data?.approved || 0,
    rejectedRequests: stats?.data?.rejected || 0,
    draftRequests: stats?.data?.draft || 0,
    // Percentage calculations
    approvalRate: stats?.data?.total ? 
      Math.round((stats.data.approved / stats.data.total) * 100) : 0,
    rejectionRate: stats?.data?.total ? 
      Math.round((stats.data.rejected / stats.data.total) * 100) : 0,
  };
};