import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'react-hot-toast';
import {
  getDocument,
  uploadDocumentVersion,
  getDocumentVersions,
  revertDocumentVersion,
  downloadDocumentVersion,
  approveDocumentForClient
} from '../api/documentsApi';
import type {
  Document,
  DocumentVersion,
  UploadVersionData,
  ApproveForClientData
} from '../types/document';

// Hook để lấy chi tiết document
export const useDocument = (id: number) => {
  return useQuery({
    queryKey: ['documents', 'detail', id],
    queryFn: () => getDocument(id),
    enabled: !!id,
    staleTime: 5 * 60 * 1000,
  });
};

// Hook để lấy versions của document
export const useDocumentVersions = (documentId: number) => {
  return useQuery({
    queryKey: ['documents', 'versions', documentId],
    queryFn: () => getDocumentVersions(documentId),
    enabled: !!documentId,
    staleTime: 2 * 60 * 1000,
  });
};

// Hook để upload version mới
export const useUploadDocumentVersion = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ documentId, data }: { documentId: number; data: UploadVersionData }) => 
      uploadDocumentVersion(documentId, data),
    onSuccess: (newVersion, { documentId }) => {
      // Invalidate document detail và versions
      queryClient.invalidateQueries({ queryKey: ['documents', 'detail', documentId] });
      queryClient.invalidateQueries({ queryKey: ['documents', 'versions', documentId] });
      
      // Invalidate documents list
      queryClient.invalidateQueries({ queryKey: ['documents'] });
      
      toast.success('Phiên bản mới đã được upload thành công!');
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message || 'Có lỗi xảy ra khi upload phiên bản mới';
      toast.error(message);
    },
  });
};

// Hook để revert về version cũ
export const useRevertDocumentVersion = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ documentId, versionNumber }: { documentId: number; versionNumber: number }) => 
      revertDocumentVersion(documentId, versionNumber),
    onSuccess: (_, { documentId }) => {
      // Invalidate document detail và versions
      queryClient.invalidateQueries({ queryKey: ['documents', 'detail', documentId] });
      queryClient.invalidateQueries({ queryKey: ['documents', 'versions', documentId] });
      
      toast.success('Đã khôi phục về phiên bản trước đó!');
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message || 'Có lỗi xảy ra khi khôi phục phiên bản';
      toast.error(message);
    },
  });
};

// Hook để approve document cho client
export const useApproveDocumentForClient = () => {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ documentId, data }: { documentId: number; data: ApproveForClientData }) => 
      approveDocumentForClient(documentId, data),
    onSuccess: (_, { documentId }) => {
      // Invalidate document detail
      queryClient.invalidateQueries({ queryKey: ['documents', 'detail', documentId] });
      
      // Invalidate documents list
      queryClient.invalidateQueries({ queryKey: ['documents'] });
      
      toast.success('Tài liệu đã được phê duyệt cho khách hàng!');
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message || 'Có lỗi xảy ra khi phê duyệt tài liệu';
      toast.error(message);
    },
  });
};

// Hook để download version
export const useDownloadDocumentVersion = () => {
  return useMutation({
    mutationFn: ({ documentId, versionNumber }: { documentId: number; versionNumber: number }) => 
      downloadDocumentVersion(documentId, versionNumber),
    onSuccess: (blob, { documentId, versionNumber }) => {
      // Tạo URL để download
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `document-${documentId}-v${versionNumber}`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
      
      toast.success('Tải xuống thành công!');
    },
    onError: (error: any) => {
      const message = error?.response?.data?.message || 'Có lỗi xảy ra khi tải xuống';
      toast.error(message);
    },
  });
};