import React, { useState, useEffect, useCallback } from 'react';
import { ULID } from '../../types/ulid';

// Types
export interface TaskAttachment {
  id: ULID;
  task_id: ULID;
  tenant_id: ULID;
  uploaded_by: ULID;
  name: string;
  original_name: string;
  file_path: string;
  disk: string;
  mime_type: string;
  extension: string;
  size: number;
  hash: string;
  category: 'document' | 'image' | 'video' | 'audio' | 'archive' | 'code' | 'other';
  description?: string;
  metadata?: Record<string, any>;
  tags?: string[];
  is_public: boolean;
  is_active: boolean;
  download_count: number;
  last_accessed_at?: string;
  current_version_id?: ULID;
  created_at: string;
  updated_at: string;
  uploader?: {
    id: ULID;
    name: string;
    email: string;
  };
  task?: {
    id: ULID;
    name: string;
  };
  current_version?: TaskAttachmentVersion;
  versions?: TaskAttachmentVersion[];
}

export interface TaskAttachmentVersion {
  id: ULID;
  task_attachment_id: ULID;
  uploaded_by: ULID;
  version_number: number;
  file_path: string;
  disk: string;
  size: number;
  hash: string;
  change_description?: string;
  metadata?: Record<string, any>;
  is_current: boolean;
  created_at: string;
  updated_at: string;
  uploader?: {
    id: ULID;
    name: string;
    email: string;
  };
}

interface TaskAttachmentManagerProps {
  taskId: ULID;
  onAttachmentUpload?: (attachment: TaskAttachment) => void;
  onAttachmentDelete?: (attachmentId: ULID) => void;
  onAttachmentUpdate?: (attachment: TaskAttachment) => void;
  className?: string;
}

// Constants
const CATEGORY_COLORS = {
  document: 'text-blue-600 bg-blue-100',
  image: 'text-green-600 bg-green-100',
  video: 'text-purple-600 bg-purple-100',
  audio: 'text-yellow-600 bg-yellow-100',
  archive: 'text-orange-600 bg-orange-100',
  code: 'text-red-600 bg-red-100',
  other: 'text-gray-600 bg-gray-100'
};

const CATEGORY_ICONS = {
  document: 'fas fa-file-alt',
  image: 'fas fa-image',
  video: 'fas fa-video',
  audio: 'fas fa-music',
  archive: 'fas fa-file-archive',
  code: 'fas fa-code',
  other: 'fas fa-file'
};

// API Headers
const API_HEADERS = {
  'Accept': 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
  'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
};

export const TaskAttachmentManager: React.FC<TaskAttachmentManagerProps> = ({
  taskId,
  onAttachmentUpload,
  onAttachmentDelete,
  onAttachmentUpdate,
  className = ''
}) => {
  const [attachments, setAttachments] = useState<TaskAttachment[]>([]);
  const [loading, setLoading] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showUploadForm, setShowUploadForm] = useState(false);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [uploadData, setUploadData] = useState({
    description: '',
    category: 'other' as TaskAttachment['category'],
    tags: [] as string[],
    is_public: false
  });

  // Fetch attachments
  const fetchAttachments = useCallback(async () => {
    setLoading(true);
    setError(null);

    try {
      const response = await fetch(`/api/task-attachments/task/${taskId}?include_versions=true`, {
        headers: API_HEADERS
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success) {
        setAttachments(data.data || []);
      } else {
        throw new Error(data.message || 'Failed to fetch attachments');
      }
    } catch (err) {
      console.error('Error fetching attachments:', err);
      setError(err instanceof Error ? err.message : 'Failed to fetch attachments');
    } finally {
      setLoading(false);
    }
  }, [taskId]);

  // Upload attachment
  const handleUpload = useCallback(async () => {
    if (!selectedFile) return;

    setUploading(true);
    setError(null);

    try {
      const formData = new FormData();
      formData.append('task_id', taskId);
      formData.append('file', selectedFile);
      formData.append('description', uploadData.description);
      formData.append('category', uploadData.category);
      formData.append('is_public', uploadData.is_public.toString());
      
      if (uploadData.tags.length > 0) {
        uploadData.tags.forEach(tag => formData.append('tags[]', tag));
      }

      const response = await fetch('/api/task-attachments', {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: formData
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success) {
        const newAttachment = data.data;
        setAttachments(prev => [newAttachment, ...prev]);
        setSelectedFile(null);
        setUploadData({
          description: '',
          category: 'other',
          tags: [],
          is_public: false
        });
        setShowUploadForm(false);
        
        if (onAttachmentUpload) {
          onAttachmentUpload(newAttachment);
        }
      } else {
        throw new Error(data.message || 'Failed to upload attachment');
      }
    } catch (err) {
      console.error('Error uploading attachment:', err);
      setError(err instanceof Error ? err.message : 'Failed to upload attachment');
    } finally {
      setUploading(false);
    }
  }, [selectedFile, uploadData, taskId, onAttachmentUpload]);

  // Delete attachment
  const handleDelete = useCallback(async (attachmentId: ULID) => {
    if (!confirm('Are you sure you want to delete this attachment?')) return;

    try {
      const response = await fetch(`/api/task-attachments/${attachmentId}`, {
        method: 'DELETE',
        headers: API_HEADERS
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      
      if (data.success) {
        setAttachments(prev => prev.filter(att => att.id !== attachmentId));
        
        if (onAttachmentDelete) {
          onAttachmentDelete(attachmentId);
        }
      } else {
        throw new Error(data.message || 'Failed to delete attachment');
      }
    } catch (err) {
      console.error('Error deleting attachment:', err);
      setError(err instanceof Error ? err.message : 'Failed to delete attachment');
    }
  }, [onAttachmentDelete]);

  // Download attachment
  const handleDownload = useCallback((attachment: TaskAttachment) => {
    window.open(`/api/task-attachments/${attachment.id}/download`, '_blank');
  }, []);

  // Preview attachment
  const handlePreview = useCallback((attachment: TaskAttachment) => {
    window.open(`/api/task-attachments/${attachment.id}/preview`, '_blank');
  }, []);

  // Format file size
  const formatFileSize = (bytes: number): string => {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let size = bytes;
    let unitIndex = 0;
    
    while (size >= 1024 && unitIndex < units.length - 1) {
      size /= 1024;
      unitIndex++;
    }
    
    return `${size.toFixed(1)} ${units[unitIndex]}`;
  };

  // Format date
  const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  // Load attachments on mount
  useEffect(() => {
    fetchAttachments();
  }, [fetchAttachments]);

  return (
    <div className={`task-attachment-manager ${className}`}>
      {/* Header */}
      <div className="flex items-center justify-between mb-4">
        <h3 className="text-lg font-semibold text-gray-900">
          Attachments ({attachments.length})
        </h3>
        <button
          onClick={() => setShowUploadForm(!showUploadForm)}
          className="inline-flex items-center px-3 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
        >
          <i className="fas fa-plus mr-2"></i>
          Upload File
        </button>
      </div>

      {/* Upload Form */}
      {showUploadForm && (
        <div className="bg-gray-50 rounded-lg p-4 mb-4">
          <div className="space-y-4">
            {/* File Input */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Select File
              </label>
              <input
                type="file"
                onChange={(e) => setSelectedFile(e.target.files?.[0] || null)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.jpg,.jpeg,.png,.gif,.webp,.svg,.bmp,.tiff,.mp4,.avi,.mov,.wmv,.flv,.webm,.mp3,.wav,.ogg,.m4a,.aac,.zip,.rar,.7z,.gz,.tar,.html,.css,.js,.json,.xml"
              />
            </div>

            {/* Description */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Description
              </label>
              <textarea
                value={uploadData.description}
                onChange={(e) => setUploadData(prev => ({ ...prev, description: e.target.value }))}
                rows={3}
                placeholder="Optional description..."
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              />
            </div>

            {/* Category */}
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Category
              </label>
              <select
                value={uploadData.category}
                onChange={(e) => setUploadData(prev => ({ ...prev, category: e.target.value as TaskAttachment['category'] }))}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="document">Document</option>
                <option value="image">Image</option>
                <option value="video">Video</option>
                <option value="audio">Audio</option>
                <option value="archive">Archive</option>
                <option value="code">Code</option>
                <option value="other">Other</option>
              </select>
            </div>

            {/* Public Toggle */}
            <div className="flex items-center">
              <input
                type="checkbox"
                id="is_public"
                checked={uploadData.is_public}
                onChange={(e) => setUploadData(prev => ({ ...prev, is_public: e.target.checked }))}
                className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
              />
              <label htmlFor="is_public" className="ml-2 block text-sm text-gray-700">
                Make this file public
              </label>
            </div>

            {/* Actions */}
            <div className="flex justify-end space-x-2">
              <button
                onClick={() => setShowUploadForm(false)}
                className="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={handleUpload}
                disabled={!selectedFile || uploading}
                className="px-4 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                {uploading ? (
                  <>
                    <i className="fas fa-spinner fa-spin mr-2"></i>
                    Uploading...
                  </>
                ) : (
                  <>
                    <i className="fas fa-upload mr-2"></i>
                    Upload
                  </>
                )}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Error Message */}
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-md p-4 mb-4">
          <div className="flex">
            <div className="flex-shrink-0">
              <i className="fas fa-exclamation-circle text-red-400"></i>
            </div>
            <div className="ml-3">
              <p className="text-sm text-red-800">{error}</p>
            </div>
          </div>
        </div>
      )}

      {/* Loading State */}
      {loading && (
        <div className="text-center py-8">
          <i className="fas fa-spinner fa-spin text-gray-400 text-2xl mb-2"></i>
          <p className="text-gray-500">Loading attachments...</p>
        </div>
      )}

      {/* Attachments List */}
      {!loading && attachments.length === 0 && (
        <div className="text-center py-8">
          <i className="fas fa-paperclip text-gray-300 text-3xl mb-2"></i>
          <p className="text-gray-500">No attachments yet</p>
          <p className="text-sm text-gray-400">Upload files to get started</p>
        </div>
      )}

      {!loading && attachments.length > 0 && (
        <div className="space-y-3">
          {attachments.map(attachment => (
            <div key={attachment.id} className="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
              <div className="flex items-start justify-between">
                <div className="flex items-start space-x-3 flex-1">
                  {/* File Icon */}
                  <div className={`flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center ${CATEGORY_COLORS[attachment.category]}`}>
                    <i className={`${CATEGORY_ICONS[attachment.category]} text-lg`}></i>
                  </div>

                  {/* File Info */}
                  <div className="flex-1 min-w-0">
                    <h4 className="text-sm font-medium text-gray-900 truncate">
                      {attachment.original_name}
                    </h4>
                    <p className="text-xs text-gray-500 mt-1">
                      {formatFileSize(attachment.size)} • {attachment.extension.toUpperCase()} • {formatDate(attachment.created_at)}
                    </p>
                    {attachment.description && (
                      <p className="text-xs text-gray-600 mt-1 line-clamp-2">
                        {attachment.description}
                      </p>
                    )}
                    <div className="flex items-center space-x-2 mt-2">
                      <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${CATEGORY_COLORS[attachment.category]}`}>
                        {attachment.category}
                      </span>
                      {attachment.is_public && (
                        <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                          <i className="fas fa-globe mr-1"></i>
                          Public
                        </span>
                      )}
                      {attachment.download_count > 0 && (
                        <span className="text-xs text-gray-500">
                          <i className="fas fa-download mr-1"></i>
                          {attachment.download_count}
                        </span>
                      )}
                    </div>
                  </div>
                </div>

                {/* Actions */}
                <div className="flex items-center space-x-2 ml-4">
                  {attachment.can_preview && (
                    <button
                      onClick={() => handlePreview(attachment)}
                      className="text-blue-600 hover:text-blue-800 text-sm"
                      title="Preview"
                    >
                      <i className="fas fa-eye"></i>
                    </button>
                  )}
                  <button
                    onClick={() => handleDownload(attachment)}
                    className="text-green-600 hover:text-green-800 text-sm"
                    title="Download"
                  >
                    <i className="fas fa-download"></i>
                  </button>
                  <button
                    onClick={() => handleDelete(attachment.id)}
                    className="text-red-600 hover:text-red-800 text-sm"
                    title="Delete"
                  >
                    <i className="fas fa-trash"></i>
                  </button>
                </div>
              </div>

              {/* Uploader Info */}
              {attachment.uploader && (
                <div className="mt-3 pt-3 border-t border-gray-100">
                  <div className="flex items-center text-xs text-gray-500">
                    <div className="w-6 h-6 bg-gray-300 rounded-full flex items-center justify-center mr-2">
                      <span className="text-xs font-medium">
                        {attachment.uploader.name.charAt(0).toUpperCase()}
                      </span>
                    </div>
                    <span>Uploaded by {attachment.uploader.name}</span>
                  </div>
                </div>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default TaskAttachmentManager;
