import { useState, useEffect, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'react-hot-toast';
import {
  getChangeRequests,
  getChangeRequest,
  createChangeRequest,
  updateChangeRequest,
  deleteChangeRequest,
  submitChangeRequest,
  decideChangeRequest,
  getChangeRequestStats,
  getChangeRequestsByProject,
  duplicateChangeRequest
} from '../api/changeRequestsApi';
import {
  ChangeRequest,
  ChangeRequestFilters,
  CreateChangeRequestData,
  UpdateChangeRequestData,
  ChangeRequestDecision,
  ChangeRequestsResponse,
  ChangeRequestStats
} from '../types/changeRequest';

/**
 * Hook để quản lý danh sách change requests với filtering, pagination và caching
 */
export const useChangeRequests = (filters?: ChangeRequestFilters) => {
  const queryClient = useQueryClient();

  // Query để lấy danh sách change requests
  const {
    data: changeRequestsData,
    isLoading,
    error,
    refetch
  } = useQuery({
    queryKey: ['changeRequests', filters],
    queryFn: () => getChangeRequests(filters),
    staleTime: 5 * 60 * 1000, // 5 phút
    cacheTime: 10 * 60 * 1000, // 10 phút
  });

  // Mutation để tạo change request mới
  const createMutation = useMutation({
    mutationFn: createChangeRequest,
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['changeRequests'] });
      queryClient.invalidateQueries({ queryKey: ['changeRequestStats'] });
      toast.success('Tạo yêu cầu thay đổi thành công!');
    },
    onError: (error: any) => {
      toast.error(error.message || 'Có lỗi xảy ra khi tạo yêu cầu thay đổi');
    }
  });

  // Mutation để cập nhật change request
  const updateMutation = useMutation({
    mutationFn: ({ id, data }: { id: string; data: UpdateChangeRequestData }) => 
      updateChangeRequest(id, data),
    onSuccess: (data, variables) => {
      queryClient.invalidateQueries({ queryKey: ['changeRequests'] });
      queryClient.invalidateQueries({ queryKey: ['changeRequest', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['changeRequestStats'] });
      toast.success('Cập nhật yêu cầu thay đổi thành công!');
    },
    onError: (error: any) => {
      toast.error(error.message || 'Có lỗi xảy ra khi cập nhật yêu cầu thay đổi');
    }
  });

  // Mutation để xóa change request
  const deleteMutation = useMutation({
    mutationFn: deleteChangeRequest,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['changeRequests'] });
      queryClient.invalidateQueries({ queryKey: ['changeRequestStats'] });
      toast.success('Xóa yêu cầu thay đổi thành công!');
    },
    onError: (error: any) => {
      toast.error(error.message || 'Có lỗi xảy ra khi xóa yêu cầu thay đổi');
    }
  });

  // Mutation để submit change request
  const submitMutation = useMutation({
    mutationFn: submitChangeRequest,
    onSuccess: (data, variables) => {
      queryClient.invalidateQueries({ queryKey: ['changeRequests'] });
      queryClient.invalidateQueries({ queryKey: ['changeRequest', variables] });
      queryClient.invalidateQueries({ queryKey: ['changeRequestStats'] });
      toast.success('Gửi yêu cầu thay đổi thành công!');
    },
    onError: (error: any) => {
      toast.error(error.message || 'Có lỗi xảy ra khi gửi yêu cầu thay đổi');
    }
  });

  // Mutation để quyết định change request
  const decideMutation = useMutation({
    mutationFn: ({ id, decision }: { id: string; decision: ChangeRequestDecision }) => 
      decideChangeRequest(id, decision),
    onSuccess: (data, variables) => {
      queryClient.invalidateQueries({ queryKey: ['changeRequests'] });
      queryClient.invalidateQueries({ queryKey: ['changeRequest', variables.id] });
      queryClient.invalidateQueries({ queryKey: ['changeRequestStats'] });
      const action = variables.decision.approved ? 'phê duyệt' : 'từ chối';
      toast.success(`${action.charAt(0).toUpperCase() + action.slice(1)} yêu cầu thay đổi thành công!`);
    },
    onError: (error: any) => {
      toast.error(error.message || 'Có lỗi xảy ra khi quyết định yêu cầu thay đổi');
    }
  });

  // Mutation để duplicate change request
  const duplicateMutation = useMutation({
    mutationFn: duplicateChangeRequest,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['changeRequests'] });
      queryClient.invalidateQueries({ queryKey: ['changeRequestStats'] });
      toast.success('Sao chép yêu cầu thay đổi thành công!');
    },
    onError: (error: any) => {
      toast.error(error.message || 'Có lỗi xảy ra khi sao chép yêu cầu thay đổi');
    }
  });

  return {
    // Data
    changeRequests: changeRequestsData?.data || [],
    pagination: changeRequestsData?.pagination,
    
    // Loading states
    isLoading,
    isCreating: createMutation.isLoading,
    isUpdating: updateMutation.isLoading,
    isDeleting: deleteMutation.isLoading,
    isSubmitting: submitMutation.isLoading,
    isDeciding: decideMutation.isLoading,
    isDuplicating: duplicateMutation.isLoading,
    
    // Error states
    error,
    createError: createMutation.error,
    updateError: updateMutation.error,
    deleteError: deleteMutation.error,
    submitError: submitMutation.error,
    decideError: decideMutation.error,
    duplicateError: duplicateMutation.error,
    
    // Actions
    refetch,
    createChangeRequest: createMutation.mutate,
    updateChangeRequest: updateMutation.mutate,
    deleteChangeRequest: deleteMutation.mutate,
    submitChangeRequest: submitMutation.mutate,
    decideChangeRequest: decideMutation.mutate,
    duplicateChangeRequest: duplicateMutation.mutate,
    
    // Reset functions
    resetCreateError: createMutation.reset,
    resetUpdateError: updateMutation.reset,
    resetDeleteError: deleteMutation.reset,
    resetSubmitError: submitMutation.reset,
    resetDecideError: decideMutation.reset,
    resetDuplicateError: duplicateMutation.reset,
  };
};