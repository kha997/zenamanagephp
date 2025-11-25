import React, { useState, useCallback } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { AttachmentUpload } from './AttachmentUpload';
import { AttachmentList, type TaskAttachment } from './AttachmentList';
import { tasksApi } from '../api';
import { createApiClient } from '../../../shared/api/client';
import { useAuthStore } from '../../auth/store';

const apiClient = createApiClient();

export interface TaskAttachmentsProps {
  /** Task ID */
  taskId: string | number;
  /** Current user ID */
  currentUserId?: string | number;
  /** Optional className */
  className?: string;
}

/**
 * TaskAttachments - Main component for displaying and managing task attachments
 * 
 * Integrates with backend API endpoints:
 * - GET /api/v1/app/task-attachments/task/{taskId}
 * - POST /api/v1/app/task-attachments
 * - DELETE /api/v1/app/task-attachments/{id}
 * - GET /api/v1/app/task-attachments/{id}/download
 */
export const TaskAttachments: React.FC<TaskAttachmentsProps> = ({
  taskId,
  currentUserId,
  className = '',
}) => {
  const { hasTenantPermission } = useAuthStore();
  const [attachments, setAttachments] = useState<TaskAttachment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<Error | null>(null);
  
  // Check if user can upload attachments (manage_tasks permission)
  const canUpload = hasTenantPermission('tenant.manage_tasks');

  // Load attachments
  React.useEffect(() => {
    const loadAttachments = async () => {
      try {
        setLoading(true);
        const response = await apiClient.get<{ data: TaskAttachment[] }>(
          `/app/task-attachments/task/${taskId}`
        );
        setAttachments(response.data?.data || []);
        setError(null);
      } catch (err) {
        setError(err as Error);
        console.error('Failed to load attachments:', err);
      } finally {
        setLoading(false);
      }
    };

    loadAttachments();
  }, [taskId]);

  const handleUpload = useCallback(async (file: File, metadata?: { description?: string; category?: string }) => {
    try {
      const formData = new FormData();
      formData.append('file', file);
      formData.append('task_id', String(taskId));
      if (metadata?.description) formData.append('description', metadata.description);
      if (metadata?.category) formData.append('category', metadata.category);

      const response = await apiClient.post<{ data: TaskAttachment }>(
        '/app/task-attachments',
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        }
      );

      setAttachments((prev) => [...prev, response.data.data]);
    } catch (err) {
      console.error('Failed to upload attachment:', err);
      throw err;
    }
  }, [taskId]);

  const handleDownload = useCallback(async (attachment: TaskAttachment) => {
    try {
      const response = await apiClient.get(`/app/task-attachments/${attachment.id}/download`, {
        responseType: 'blob',
      });
      
      // Create download link
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', attachment.original_name);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (err) {
      console.error('Failed to download attachment:', err);
    }
  }, []);

  const handleDelete = useCallback(async (attachmentId: string | number) => {
    if (!confirm('Are you sure you want to delete this attachment?')) {
      return;
    }

    try {
      await apiClient.delete(`/app/task-attachments/${attachmentId}`);
      setAttachments((prev) => prev.filter((a) => a.id !== attachmentId));
    } catch (err) {
      console.error('Failed to delete attachment:', err);
    }
  }, []);

  return (
    <Card className={className} data-testid="task-attachments">
      <CardHeader>
        <CardTitle>Attachments ({attachments.length})</CardTitle>
      </CardHeader>
      <CardContent>
        {error ? (
          <div className="text-center py-4" style={{ color: 'var(--color-semantic-danger-600)' }}>
            Failed to load attachments: {error.message}
          </div>
        ) : (
          <div className="space-y-4">
            {/* Upload Component */}
            {canUpload && (
              <AttachmentUpload
                taskId={taskId}
                onUpload={handleUpload}
                loading={loading}
              />
            )}

            {/* Attachments List */}
            <AttachmentList
              attachments={attachments}
              onDownload={handleDownload}
              onDelete={handleDelete}
              currentUserId={currentUserId}
              loading={loading}
            />
          </div>
        )}
      </CardContent>
    </Card>
  );
};

