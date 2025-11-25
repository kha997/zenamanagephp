import React, { useState, useMemo } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import {
  ArrowLeftIcon,
  DocumentTextIcon,
  ArrowDownTrayIcon,
  ArrowUpTrayIcon,
  ClockIcon,
  ArrowUturnLeftIcon,
  PlusIcon,
  LockClosedIcon
} from '@heroicons/react/24/outline';
import { Button } from '../../components/ui/button';
import { Badge } from '../../components/ui/badge';
import { Card } from '../../components/ui/card';
import { Table } from '../../components/ui/Table';
import { Modal } from '../../components/ui/modal';
import { Textarea } from '../../components/ui/textarea';
import { 
  useDocument, 
  useDocumentVersions, 
  useDocumentActivity,
  useUploadNewVersion,
  useRevertVersion
} from '@/entities/app/documents/hooks';
import type { DocumentVersion } from '@/entities/app/documents/types';
import { useRolePermission } from '@/routes/RoleGuard';
import { documentsApi } from '@/entities/app/documents/api';
import { formatDate, formatFileSize } from '../../lib/utils/format';

// File upload constants
const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
const ALLOWED_MIME_TYPES = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'application/vnd.ms-powerpoint',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  'image/jpeg',
  'image/png',
  'image/gif',
  'text/plain',
];

// File validation helpers
const validateFile = (file: File): { valid: boolean; error?: string } => {
  if (file.size > MAX_FILE_SIZE) {
    return { valid: false, error: `File size must be less than 10MB. Current size: ${(file.size / 1024 / 1024).toFixed(2)}MB` };
  }
  if (!ALLOWED_MIME_TYPES.includes(file.type)) {
    return { valid: false, error: `File type not allowed. Allowed types: PDF, Word, Excel, PowerPoint, Images, and Text files` };
  }
  return { valid: true };
};

export const DocumentDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { canAccess } = useRolePermission();
  
  const documentId = Number(id);
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [showRevertModal, setShowRevertModal] = useState(false);
  const [selectedVersion, setSelectedVersion] = useState<DocumentVersion | null>(null);
  const [uploadFile, setUploadFile] = useState<File | null>(null);
  const [uploadComment, setUploadComment] = useState('');
  const [revertComment, setRevertComment] = useState('');

  const documentQuery = useDocument(documentId, Boolean(documentId));
  const versionsQuery = useDocumentVersions(documentId, Boolean(documentId));
  const activityQuery = useDocumentActivity(documentId, Boolean(documentId));
  const uploadVersionMutation = useUploadNewVersion();
  const revertMutation = useRevertVersion();

  const canManage = useMemo(
    () => canAccess(undefined, ['document.update', 'document.approve']),
    [canAccess]
  );

  const canDownload = useMemo(
    () => canAccess(undefined, ['document.download']),
    [canAccess]
  );

  const isLoading = documentQuery.isLoading || versionsQuery.isLoading;
  const document = documentQuery.data?.data;
  const versions = useMemo(() => versionsQuery.data?.data ?? [], [versionsQuery.data]);
  const activity = activityQuery.data?.data ?? [];

  const handleUploadNewVersion = async () => {
    if (!uploadFile || !uploadComment.trim()) {
      toast.error('Please select a file and enter a comment');
      return;
    }

    // Validate file
    const validation = validateFile(uploadFile);
    if (!validation.valid) {
      toast.error(validation.error || 'Invalid file');
      return;
    }

    try {
      await uploadVersionMutation.mutateAsync({
        id: documentId,
        file: uploadFile,
        changeDescription: uploadComment
      });
      
      toast.success('New version uploaded successfully');
      setShowUploadModal(false);
      setUploadFile(null);
      setUploadComment('');
    } catch (error) {
      toast.error('Failed to upload new version');
    }
  };

  const handleFileSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const validation = validateFile(file);
    if (!validation.valid) {
      toast.error(validation.error || 'Invalid file');
      return;
    }

    setUploadFile(file);
  };

  const handleRevertVersion = async () => {
    if (!selectedVersion || !revertComment.trim()) {
      toast.error('Please enter a comment for reverting');
      return;
    }

    try {
      await revertMutation.mutateAsync({
        id: documentId,
        versionId: Number(selectedVersion.id),
        comment: revertComment
      });
      
      toast.success(`Reverted to version ${selectedVersion.version}`);
      setShowRevertModal(false);
      setSelectedVersion(null);
      setRevertComment('');
    } catch (error) {
      toast.error('Failed to revert version');
    }
  };

  const handleDownload = async (version: DocumentVersion | null = null) => {
    try {
      const blob = version && version.id 
        ? await documentsApi.downloadVersion(documentId, Number(version.id))
        : await documentsApi.downloadDocument(documentId);
      const url = window.URL.createObjectURL(blob);
      const a = window.document.createElement('a');
      a.href = url;
      a.download = version?.filename || document?.filename || document?.name || 'document';
      window.document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      window.document.body.removeChild(a);
      toast.success('Document downloaded successfully');
    } catch (error) {
      toast.error('Failed to download document');
    }
  };

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (!document) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen">
        <DocumentTextIcon className="h-16 w-16 text-gray-400 mb-4" />
        <h2 className="text-xl font-semibold text-gray-900 mb-2">Document not found</h2>
        <Button onClick={() => navigate('/app/documents')} variant="outline">
          Back to Documents
        </Button>
      </div>
    );
  }

  const versionColumns: any[] = [
    {
      key: 'version',
      title: 'Version',
      render: (version: any) => (
        <div className="flex items-center space-x-2">
          <span className="font-mono font-medium">v{version.version}</span>
          {version.version === document.version && (
            <Badge variant="default">Current</Badge>
          )}
          {version.reverted_from_version && (
            <Badge variant="secondary">
              Revert from v{version.reverted_from_version}
            </Badge>
          )}
        </div>
      )
    },
    {
      key: 'file_info',
      title: 'File Info',
      render: (version: any) => (
        <div className="text-sm">
          <div className="font-medium text-gray-900">{version.filename}</div>
          <div className="text-gray-500">{formatFileSize(version.size)}</div>
        </div>
      )
    },
    {
      key: 'change',
      title: 'Change Description',
      render: (version: any) => (
        <div className="text-sm text-gray-700 max-w-xs truncate" title={version.change_description}>
          {version.change_description || 'No description'}
        </div>
      )
    },
    {
      key: 'created_by',
      title: 'Created By',
      render: (version: any) => (
        <div className="text-sm">
          <div className="font-medium text-gray-900">{version.uploaded_by_name}</div>
          <div className="text-gray-500">{formatDate(version.uploaded_at)}</div>
        </div>
      )
    },
    {
      key: 'actions',
      title: 'Actions',
      render: (version: any) => (
        <div className="flex items-center space-x-2">
          {canDownload && (
            <Button
              onClick={() => handleDownload(version)}
              variant="outline"
              size="sm"
            >
              <ArrowDownTrayIcon className="h-4 w-4" />
            </Button>
          )}
          {version.version !== document.version && canManage && (
            <Button
              onClick={() => {
                setSelectedVersion(version);
                setShowRevertModal(true);
              }}
              variant="outline"
              size="sm"
            >
              <ArrowUturnLeftIcon className="h-4 w-4" />
            </Button>
          )}
        </div>
      )
    }
  ];

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <Button
              onClick={() => navigate('/app/documents')}
              variant="outline"
              size="sm"
            >
              <ArrowLeftIcon className="h-4 w-4 mr-2" />
              Back
            </Button>
            <div>
              <h1 className="text-2xl font-bold text-gray-900">{document.name}</h1>
              <p className="text-sm text-gray-500 mt-1">
                {document.project_name && `Project: ${document.project_name}`}
              </p>
            </div>
          </div>
          {canManage && (
            <div className="flex items-center space-x-3">
              <Button
                onClick={() => setShowUploadModal(true)}
                variant="default"
              >
                <ArrowUpTrayIcon className="h-4 w-4 mr-2" />
                Upload New Version
              </Button>
            </div>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Current Version Info */}
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <DocumentTextIcon className="h-5 w-5 mr-2" />
                Current Version (v{document.version})
              </h3>
              <div className="bg-gray-50 p-4 rounded-lg">
                <div className="grid grid-cols-2 gap-4 mb-4">
                  <div>
                    <span className="text-sm font-medium text-gray-500">File Type:</span>
                    <p className="text-sm text-gray-900">{document.mime_type}</p>
                  </div>
                  <div>
                    <span className="text-sm font-medium text-gray-500">Size:</span>
                    <p className="text-sm text-gray-900">{formatFileSize(document.size)}</p>
                  </div>
                  <div>
                    <span className="text-sm font-medium text-gray-500">Uploaded By:</span>
                    <p className="text-sm text-gray-900">{document.uploaded_by_name}</p>
                  </div>
                  <div>
                    <span className="text-sm font-medium text-gray-500">Upload Date:</span>
                    <p className="text-sm text-gray-900">{formatDate(document.uploaded_at)}</p>
                  </div>
                </div>
                {document.description && (
                  <div className="mb-4">
                    <span className="text-sm font-medium text-gray-500">Description:</span>
                    <p className="text-sm text-gray-900 mt-1">{document.description}</p>
                  </div>
                )}
                {canDownload && (
                  <Button
                    onClick={() => handleDownload(null)}
                    variant="default"
                    size="sm"
                  >
                    <ArrowDownTrayIcon className="h-4 w-4 mr-2" />
                    Download
                  </Button>
                )}
              </div>
            </div>
          </Card>

          {/* Version History */}
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                <ClockIcon className="h-5 w-5 mr-2" />
                Version History ({versions.length})
              </h3>
              <Table
                data={versions}
                columns={versionColumns}
              />
            </div>
          </Card>

          {/* Activity Log */}
          {activity.length > 0 && (
            <Card>
              <div className="p-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4 flex items-center">
                  <ClockIcon className="h-5 w-5 mr-2" />
                  Activity Log
                </h3>
                <div className="space-y-3">
                  {activity.slice(0, 10).map((item) => (
                    <div key={item.id} className="flex items-start space-x-3 p-3 bg-gray-50 rounded">
                      <div className="flex-shrink-0">
                        {item.action === 'upload' && <PlusIcon className="h-5 w-5 text-blue-500" />}
                        {item.action === 'download' && <ArrowDownTrayIcon className="h-5 w-5 text-green-500" />}
                        {item.action === 'approve' && <LockClosedIcon className="h-5 w-5 text-purple-500" />}
                        {item.action === 'revert' && <ArrowUturnLeftIcon className="h-5 w-5 text-yellow-500" />}
                      </div>
                      <div className="flex-1">
                        <div className="text-sm font-medium text-gray-900">
                          {item.actor_name} {item.action}d this document
                        </div>
                        <div className="text-xs text-gray-500">{formatDate(item.created_at)}</div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </Card>
          )}
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          {/* Basic Info */}
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-medium text-gray-900 mb-4">
                Document Information
              </h3>
              <dl className="space-y-3">
                <div>
                  <dt className="text-sm font-medium text-gray-500">Name</dt>
                  <dd className="text-sm text-gray-900">{document.name}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Filename</dt>
                  <dd className="text-sm text-gray-900">{document.filename}</dd>
                </div>
                {document.project_name && (
                  <div>
                    <dt className="text-sm font-medium text-gray-500">Project</dt>
                    <dd className="text-sm text-gray-900">{document.project_name}</dd>
                  </div>
                )}
                <div>
                  <dt className="text-sm font-medium text-gray-500">Total Versions</dt>
                  <dd className="text-sm text-gray-900">{versions.length}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Created</dt>
                  <dd className="text-sm text-gray-900">{formatDate(document.uploaded_at)}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Updated</dt>
                  <dd className="text-sm text-gray-900">{formatDate(document.updated_at)}</dd>
                </div>
                <div>
                  <dt className="text-sm font-medium text-gray-500">Download Count</dt>
                  <dd className="text-sm text-gray-900">{document.download_count}</dd>
                </div>
              </dl>
            </div>
          </Card>
        </div>
      </div>

      {/* Upload Modal */}
      {showUploadModal && (
        <Modal
          isOpen={showUploadModal}
          onClose={() => setShowUploadModal(false)}
          title="Upload New Version"
        >
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Select File *
              </label>
              <input
                type="file"
                onChange={handleFileSelect}
                className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                accept={ALLOWED_MIME_TYPES.join(',')}
              />
              {uploadFile && (
                <div className="mt-2 p-2 bg-gray-50 rounded text-sm text-gray-700">
                  {uploadFile.name} ({formatFileSize(uploadFile.size)})
                </div>
              )}
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Change Description *
              </label>
              <Textarea
                value={uploadComment}
                onChange={(e) => setUploadComment(e.target.value)}
                placeholder="Describe the changes in this version..."
                rows={3}
              />
            </div>
            
            <div className="flex justify-end space-x-3 pt-4">
              <Button
                onClick={() => setShowUploadModal(false)}
                variant="outline"
                disabled={uploadVersionMutation.isPending}
              >
                Cancel
              </Button>
              <Button
                onClick={handleUploadNewVersion}
                disabled={!uploadFile || !uploadComment.trim() || uploadVersionMutation.isPending}
              >
                {uploadVersionMutation.isPending ? 'Uploading...' : 'Upload'}
              </Button>
            </div>
          </div>
        </Modal>
      )}

      {/* Revert Modal */}
      {showRevertModal && selectedVersion && (
        <Modal
          isOpen={showRevertModal}
          onClose={() => setShowRevertModal(false)}
          title="Revert to Previous Version"
        >
          <div className="space-y-4">
            <div className="p-4 bg-yellow-50 border border-yellow-200 rounded">
              <p className="text-sm text-yellow-800">
                You are about to revert to version {selectedVersion.version}. 
                This action will create a new version based on the selected one.
              </p>
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Reason for Revert *
              </label>
              <Textarea
                value={revertComment}
                onChange={(e) => setRevertComment(e.target.value)}
                placeholder="Explain why you are reverting to this version..."
                rows={3}
              />
            </div>
            
            <div className="flex justify-end space-x-3 pt-4">
              <Button
                onClick={() => setShowRevertModal(false)}
                variant="outline"
              >
                Cancel
              </Button>
              <Button
                onClick={handleRevertVersion}
                disabled={!revertComment.trim()}
                variant="default"
              >
                Revert
              </Button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
};
