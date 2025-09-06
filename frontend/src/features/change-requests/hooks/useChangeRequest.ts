
import { useQuery } from '@tanstack/react-query';
import { getChangeRequest } from '../api/changeRequestsApi';
import { ChangeRequest } from '../types/changeRequest';

/**
 * Hook để lấy chi tiết một change request
 */
export const useChangeRequest = (id: string, enabled: boolean = true) => {
  const {
    data: changeRequest,
    isLoading,
    error,
    refetch
  } = useQuery({
    queryKey: ['changeRequest', id],
    queryFn: () => getChangeRequest(id),
    enabled: enabled && !!id,
    staleTime: 5 * 60 * 1000, // 5 phút
    cacheTime: 10 * 60 * 1000, // 10 phút
    retry: (failureCount, error: any) => {
      // Không retry nếu là lỗi 404
      if (error?.status === 404) return false;
      return failureCount < 3;
    }
  });

  return {
    changeRequest: changeRequest?.data,
    isLoading,
    error,
    refetch,
    // Helper để kiểm tra trạng thái
    isDraft: changeRequest?.data?.status === 'draft',
    isAwaitingApproval: changeRequest?.data?.status === 'awaiting_approval',
    isApproved: changeRequest?.data?.status === 'approved',
    isRejected: changeRequest?.data?.status === 'rejected',
    // Helper để kiểm tra quyền
    canEdit: changeRequest?.data?.status === 'draft',
    canSubmit: changeRequest?.data?.status === 'draft',
    canDecide: changeRequest?.data?.status === 'awaiting_approval',
  };
};