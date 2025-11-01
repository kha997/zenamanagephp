import { useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Modal } from '@/components/ui/modal';
import { Textarea } from '@/components/ui/textarea';
import { 
  useDocuments, 
  useUploadDocument, 
  useDeleteDocument, 
  useDownloadDocument
} from '@/entities/app/documents/hooks';
import { useRolePermission } from '@/routes/RoleGuard';
import type { Document, DocumentsFilters } from '@/entities/app/documents/types';
import { 
  MagnifyingGlassIcon,
  PlusIcon,
  DocumentArrowDownIcon,
  EyeIcon,
  PencilIcon,
  TrashIcon,
  FolderIcon,
  DocumentTextIcon,
  PhotoIcon,
  VideoCameraIcon,
  XMarkIcon
} from '@heroicons/react/24/outline';

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
  // Check file size
  if (file.size > MAX_FILE_SIZE) {
    return { valid: false, error: `File size must be less than 10MB. Current size: ${(file.size / 1024 / 1024).toFixed(2)}MB` };
  }

  // Check MIME type
  if (!ALLOWED_MIME_TYPES.includes(file.type)) {
    return { valid: false, error: `File type not allowed. Allowed types: PDF, Word, Excel, PowerPoint, Images, and Text files` };
  }

  return { valid: true };
};

const formatFileSize = (bytes: number): string => {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(2)} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
};

export default function DocumentsPage() {
  const navigate = useNavigate();
  const { canAccess } = useRolePermission();
  const fileInputRef = useRef<HTMLInputElement>(null);
  
  const [filters, setFilters] = useState<DocumentsFilters>({
    page: 1,
    per_page: 12
  });
  
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [uploadFile, setUploadFile] = useState<File | null>(null);
  const [uploadDescription, setUploadDescription] = useState('');
  const [uploadTags, setUploadTags] = useState('');
  const [isPublic, setIsPublic] = useState(false);

  const { data: documentsResponse, isLoading, error } = useDocuments(filters);
  const uploadMutation = useUploadDocument();
  const deleteMutation = useDeleteDocument();
  const downloadMutation = useDownloadDocument();

  const documents = documentsResponse?.data || [];
  const meta = documentsResponse?.meta;

  // RBAC checks
  const canUpload = canAccess(undefined, ['document.create']);
  const canDelete = canAccess(undefined, ['document.delete']);
  const canDownload = canAccess(undefined, ['document.download']);
  const canUpdate = canAccess(undefined, ['document.update']);

  const handleSearch = (search: string) => {
    setFilters(prev => ({ ...prev, search, page: 1 }));
  };

  const handleProjectFilter = (projectId: string) => {
    setFilters(prev => ({ 
      ...prev, 
      project_id: projectId === 'all' ? undefined : parseInt(projectId),
      page: 1 
    }));
  };

  const handleTypeFilter = (mimeType: string) => {
    setFilters(prev => ({ 
      ...prev, 
      mime_type: mimeType === 'all' ? undefined : mimeType,
      page: 1 
    }));
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

  const handleUpload = async () => {
    if (!uploadFile) {
      toast.error('Please select a file');
      return;
    }

    try {
      const tags = uploadTags.split(',').map(tag => tag.trim()).filter(Boolean);
      await uploadMutation.mutateAsync({
        file: uploadFile,
        description: uploadDescription || undefined,
        tags,
        is_public: isPublic
      });
      
      toast.success('Document uploaded successfully');
      setShowUploadModal(false);
      setUploadFile(null);
      setUploadDescription('');
      setUploadTags('');
      setIsPublic(false);
    } catch (error) {
      toast.error('Failed to upload document');
    }
  };

  const handleDelete = async (documentId: number, documentName: string) => {
    if (!window.confirm(`Are you sure you want to delete "${documentName}"?`)) {
      return;
    }

    try {
      await deleteMutation.mutateAsync(documentId);
      toast.success('Document deleted successfully');
    } catch (error) {
      toast.error('Failed to delete document');
    }
  };

  const handleDownload = async (doc: Document) => {
    try {
      const blob = await downloadMutation.mutateAsync(doc.id);
      const url = window.URL.createObjectURL(blob);
      const a = window.document.createElement('a');
      a.href = url;
      a.download = doc.filename || doc.name;
      window.document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      window.document.body.removeChild(a);
      toast.success('Document downloaded successfully');
    } catch (error) {
      toast.error('Failed to download document');
    }
  };

  const handleView = (document: Document) => {
    navigate(`/app/documents/${document.id}`);
  };

  const getFileIcon = (mimeType: string) => {
    if (mimeType.includes('pdf')) {
      return <DocumentTextIcon className="h-5 w-5 text-red-500" />;
    } else if (mimeType.includes('word') || mimeType.includes('document')) {
      return <DocumentTextIcon className="h-5 w-5 text-blue-500" />;
    } else if (mimeType.includes('sheet') || mimeType.includes('excel')) {
      return <DocumentTextIcon className="h-5 w-5 text-green-500" />;
    } else if (mimeType.includes('presentation') || mimeType.includes('powerpoint')) {
      return <DocumentTextIcon className="h-5 w-5 text-orange-500" />;
    } else if (mimeType.includes('image')) {
      return <PhotoIcon className="h-5 w-5 text-purple-500" />;
    } else if (mimeType.includes('video')) {
      return <VideoCameraIcon className="h-5 w-5 text-indigo-500" />;
    } else {
      return <DocumentTextIcon className="h-5 w-5 text-gray-500" />;
    }
  };

  const projects = [...new Set(documents.map(doc => doc.project_name).filter(Boolean))];

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">Documents</h2>
            <p className="text-gray-600">Manage and organize your project documents</p>
          </div>
        </div>
        <Card>
          <CardContent className="flex items-center justify-center h-64">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
          </CardContent>
        </Card>
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">Documents</h2>
            <p className="text-gray-600">Manage and organize your project documents</p>
          </div>
        </div>
        <Card>
          <CardContent className="text-center py-12">
            <div className="text-red-500">
              <h3 className="text-lg font-medium mb-2">Failed to load documents</h3>
              <p className="text-gray-600">There was an error loading the documents. Please try again.</p>
              <Button 
                onClick={() => window.location.reload()} 
                variant="outline"
                className="mt-4"
              >
                Retry
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="space-y-6" aria-live="polite">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-3xl font-bold text-gray-900">Documents</h2>
          <p className="text-gray-600">Manage and organize your project documents</p>
        </div>
        {canUpload && (
          <Button onClick={() => setShowUploadModal(true)}>
            <PlusIcon className="h-4 w-4 mr-2" />
            Upload Document
          </Button>
        )}
      </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle>Filters</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label className="text-sm font-medium text-gray-700">Search</label>
              <div className="relative">
                <MagnifyingGlassIcon className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                <Input
                  placeholder="Search documents..."
                  value={filters.search || ''}
                  onChange={(e) => handleSearch(e.target.value)}
                  className="pl-10"
                  aria-label="Search documents"
                />
              </div>
            </div>
            
            <div>
              <label className="text-sm font-medium text-gray-700">File Type</label>
              <select
                value={filters.mime_type || 'all'}
                onChange={(e) => handleTypeFilter(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                aria-label="Filter by file type"
              >
                <option value="all">All Types</option>
                <option value="application/pdf">PDF</option>
                <option value="application/msword">Word</option>
                <option value="application/vnd.openxmlformats-officedocument.wordprocessingml.document">Word (DOCX)</option>
                <option value="application/vnd.ms-excel">Excel</option>
                <option value="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">Excel (XLSX)</option>
                <option value="image/jpeg">JPEG</option>
                <option value="image/png">PNG</option>
              </select>
            </div>
            
            <div>
              <label className="text-sm font-medium text-gray-700">Project</label>
              <select
                value={filters.project_id?.toString() || 'all'}
                onChange={(e) => handleProjectFilter(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                aria-label="Filter by project"
              >
                <option value="all">All Projects</option>
                {projects.map(project => (
                  <option key={project} value={project}>{project}</option>
                ))}
              </select>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Documents List */}
      <Card>
        <CardHeader>
          <CardTitle>Documents ({meta?.total || 0})</CardTitle>
        </CardHeader>
        <CardContent>
          {documents.length === 0 ? (
            <div className="text-center py-12">
              <FolderIcon className="h-12 w-12 mx-auto mb-4 text-gray-300" />
              <h3 className="text-lg font-medium mb-2 text-gray-500">No documents found</h3>
              <p className="text-gray-600">Try adjusting your filters or upload a new document.</p>
            </div>
          ) : (
            <div className="space-y-4">
              {documents.map(doc => (
                <div key={doc.id} className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50">
                  <div className="flex items-center space-x-4 flex-1">
                    {getFileIcon(doc.mime_type)}
                    <div className="flex-1">
                      <h4 className="font-medium">{doc.name}</h4>
                      <div className="flex items-center space-x-4 mt-1">
                        <span className="text-sm text-gray-500">{formatFileSize(doc.size)}</span>
                        <span className="text-sm text-gray-500">by {doc.uploaded_by_name}</span>
                        {doc.project_name && (
                          <Badge variant="outline" className="text-xs">
                            {doc.project_name}
                          </Badge>
                        )}
                      </div>
                    </div>
                  </div>
                  <div className="flex space-x-2">
                    {canDownload && (
                      <Button 
                        variant="ghost" 
                        size="sm"
                        onClick={() => handleView(doc)}
                        aria-label={`View ${doc.name}`}
                      >
                        <EyeIcon className="h-4 w-4" />
                      </Button>
                    )}
                    {canDownload && (
                      <Button 
                        variant="ghost" 
                        size="sm"
                        onClick={() => handleDownload(doc)}
                        disabled={downloadMutation.isPending}
                        aria-label={`Download ${doc.name}`}
                      >
                        <DocumentArrowDownIcon className="h-4 w-4" />
                      </Button>
                    )}
                    {canUpdate && (
                      <Button 
                        variant="ghost" 
                        size="sm"
                        onClick={() => navigate(`/app/documents/${doc.id}/edit`)}
                        aria-label={`Edit ${doc.name}`}
                      >
                        <PencilIcon className="h-4 w-4" />
                      </Button>
                    )}
                    {canDelete && (
                      <Button 
                        variant="ghost" 
                        size="sm" 
                        className="text-red-600"
                        onClick={() => handleDelete(doc.id, doc.name)}
                        disabled={deleteMutation.isPending}
                        aria-label={`Delete ${doc.name}`}
                      >
                        <TrashIcon className="h-4 w-4" />
                      </Button>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Upload Modal */}
      {showUploadModal && (
        <Modal 
          isOpen={showUploadModal}
          onClose={() => setShowUploadModal(false)}
          title="Upload Document"
        >
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Select File *
              </label>
              <input
                ref={fileInputRef}
                type="file"
                onChange={handleFileSelect}
                className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                accept={ALLOWED_MIME_TYPES.join(',')}
              />
              {uploadFile && (
                <div className="mt-2 p-2 bg-gray-50 rounded">
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-gray-700">{uploadFile.name}</span>
                    <button
                      onClick={() => {
                        setUploadFile(null);
                        if (fileInputRef.current) {
                          fileInputRef.current.value = '';
                        }
                      }}
                      className="text-gray-400 hover:text-gray-600"
                    >
                      <XMarkIcon className="h-4 w-4" />
                    </button>
                  </div>
                  <div className="text-xs text-gray-500 mt-1">
                    Size: {formatFileSize(uploadFile.size)} | Type: {uploadFile.type}
                  </div>
                </div>
              )}
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Description
              </label>
              <Textarea
                value={uploadDescription}
                onChange={(e) => setUploadDescription(e.target.value)}
                placeholder="Optional description for this document..."
                rows={3}
              />
            </div>
            
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">
                Tags (comma-separated)
              </label>
              <Input
                value={uploadTags}
                onChange={(e) => setUploadTags(e.target.value)}
                placeholder="e.g., project, milestone, important"
              />
            </div>
            
            <div className="flex items-center">
              <input
                type="checkbox"
                id="is-public"
                checked={isPublic}
                onChange={(e) => setIsPublic(e.target.checked)}
                className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
              />
              <label htmlFor="is-public" className="ml-2 text-sm text-gray-700">
                Make this document public
              </label>
            </div>
            
            <div className="flex justify-end space-x-3 pt-4">
              <Button
                onClick={() => setShowUploadModal(false)}
                variant="outline"
                disabled={uploadMutation.isPending}
              >
                Cancel
              </Button>
              <Button
                onClick={handleUpload}
                disabled={!uploadFile || uploadMutation.isPending}
              >
                {uploadMutation.isPending ? 'Uploading...' : 'Upload'}
              </Button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}
