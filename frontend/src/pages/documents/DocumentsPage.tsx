import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import toast from 'react-hot-toast';
import { 
  useDocuments, 
  useDeleteDocument, 
  useDownloadDocument,
  useBulkDeleteDocuments,
  useBulkUpdateDocumentTags,
  useBulkUpdateDocumentVisibility
} from '@/entities/app/documents/hooks';
import type { DocumentsFilters } from '@/entities/app/documents/types';
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
  VideoCameraIcon
} from '@heroicons/react/24/outline';

interface Document {
  id: number;
  name: string;
  type: 'pdf' | 'doc' | 'docx' | 'xls' | 'xlsx' | 'ppt' | 'pptx' | 'jpg' | 'png' | 'mp4';
  size: string;
  uploadedBy: string;
  uploadedAt: string;
  project?: string;
  category: 'project' | 'template' | 'resource' | 'archive';
}

// Mock data - replace with actual API call
const mockDocuments: Document[] = [
  {
    id: 1,
    name: 'Project Brief.pdf',
    type: 'pdf',
    size: '2.4 MB',
    uploadedBy: 'John Doe',
    uploadedAt: '2024-01-15',
    project: 'Website Redesign',
    category: 'project'
  },
  {
    id: 2,
    name: 'Design Mockups.pdf',
    type: 'pdf',
    size: '5.8 MB',
    uploadedBy: 'Jane Smith',
    uploadedAt: '2024-01-14',
    project: 'Website Redesign',
    category: 'project'
  },
  {
    id: 3,
    name: 'Technical Specs.docx',
    type: 'docx',
    size: '1.2 MB',
    uploadedBy: 'Bob Johnson',
    uploadedAt: '2024-01-13',
    project: 'Website Redesign',
    category: 'project'
  },
  {
    id: 4,
    name: 'Company Logo.png',
    type: 'png',
    size: '856 KB',
    uploadedBy: 'Alice Brown',
    uploadedAt: '2024-01-12',
    category: 'resource'
  },
  {
    id: 5,
    name: 'Meeting Recording.mp4',
    type: 'mp4',
    size: '45.2 MB',
    uploadedBy: 'Charlie Wilson',
    uploadedAt: '2024-01-11',
    project: 'Website Redesign',
    category: 'project'
  }
];

export default function DocumentsPage() {
  const [filters, setFilters] = useState<DocumentsFilters>({
    page: 1,
    per_page: 12
  });
  const [selectedDocuments, setSelectedDocuments] = useState<number[]>([]);
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');

  const { data: documentsResponse, isLoading, error } = useDocuments(filters);
  const deleteDocumentMutation = useDeleteDocument();
  const downloadDocumentMutation = useDownloadDocument();
  const bulkDeleteMutation = useBulkDeleteDocuments();

  const documents = documentsResponse?.data || [];
  const meta = documentsResponse?.meta;

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

  const handleDeleteDocument = async (documentId: number) => {
    if (window.confirm('Are you sure you want to delete this document?')) {
      try {
        await deleteDocumentMutation.mutateAsync(documentId);
        toast.success('Document deleted successfully');
      } catch (error) {
        toast.error('Failed to delete document');
      }
    }
  };

  const handleDownloadDocument = async (documentId: number, filename: string) => {
    try {
      const blob = await downloadDocumentMutation.mutateAsync(documentId);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(url);
      document.body.removeChild(a);
      toast.success('Document downloaded successfully');
    } catch (error) {
      toast.error('Failed to download document');
    }
  };

  const handleBulkDelete = async () => {
    if (selectedDocuments.length === 0) return;
    
    if (window.confirm(`Are you sure you want to delete ${selectedDocuments.length} documents?`)) {
      try {
        await bulkDeleteMutation.mutateAsync(selectedDocuments);
        toast.success(`${selectedDocuments.length} documents deleted successfully`);
        setSelectedDocuments([]);
      } catch (error) {
        toast.error('Failed to delete documents');
      }
    }
  };

  const handleSelectDocument = (documentId: number) => {
    setSelectedDocuments(prev => 
      prev.includes(documentId) 
        ? prev.filter(id => id !== documentId)
        : [...prev, documentId]
    );
  };

  const handleSelectAll = () => {
    if (selectedDocuments.length === documents.length) {
      setSelectedDocuments([]);
    } else {
      setSelectedDocuments(documents.map(doc => doc.id));
    }
  };

  if (isLoading) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">Document Center</h2>
            <p className="text-gray-600">Manage and organize your project documents</p>
          </div>
        </div>
        <div className="flex items-center justify-center h-64">
          <div className="text-gray-500">Loading documents...</div>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="space-y-6">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-3xl font-bold text-gray-900">Document Center</h2>
            <p className="text-gray-600">Manage and organize your project documents</p>
          </div>
        </div>
        <Card>
          <CardContent className="text-center py-12">
            <div className="text-red-500">
              <h3 className="text-lg font-medium mb-2">Failed to load documents</h3>
              <p className="text-gray-600">There was an error loading the documents. Please try again.</p>
            </div>
          </CardContent>
        </Card>
      </div>
    );
  }

  const getFileIcon = (type: string) => {
    switch (type) {
      case 'pdf':
        return <DocumentTextIcon className="h-5 w-5 text-red-500" />;
      case 'doc':
      case 'docx':
        return <DocumentTextIcon className="h-5 w-5 text-blue-500" />;
      case 'xls':
      case 'xlsx':
        return <DocumentTextIcon className="h-5 w-5 text-green-500" />;
      case 'ppt':
      case 'pptx':
        return <DocumentTextIcon className="h-5 w-5 text-orange-500" />;
      case 'jpg':
      case 'png':
        return <PhotoIcon className="h-5 w-5 text-purple-500" />;
      case 'mp4':
        return <VideoCameraIcon className="h-5 w-5 text-indigo-500" />;
      default:
        return <DocumentTextIcon className="h-5 w-5 text-gray-500" />;
    }
  };

  const getCategoryColor = (category: string) => {
    switch (category) {
      case 'project':
        return 'default';
      case 'template':
        return 'secondary';
      case 'resource':
        return 'outline';
      case 'archive':
        return 'destructive';
      default:
        return 'secondary';
    }
  };

  const projects = [...new Set(documents.map(doc => doc.project).filter(Boolean))];
  const categories = [...new Set(documents.map(doc => doc.category))];

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-3xl font-bold text-gray-900">Documents</h2>
          <p className="text-gray-600">Manage and organize your project documents</p>
        </div>
        <Button>
          <PlusIcon className="h-4 w-4 mr-2" />
          Upload Document
        </Button>
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
                />
              </div>
            </div>
            
            <div>
              <label className="text-sm font-medium text-gray-700">File Type</label>
              <select
                value={filters.mime_type || 'all'}
                onChange={(e) => handleTypeFilter(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
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
          <div className="space-y-4">
            {documents.map(doc => (
              <div key={doc.id} className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50">
                <div className="flex items-center space-x-4">
                  {getFileIcon(doc.type)}
                  <div className="flex-1">
                    <h4 className="font-medium">{doc.name}</h4>
                    <div className="flex items-center space-x-4 mt-1">
                      <span className="text-sm text-gray-500">{doc.size}</span>
                      <span className="text-sm text-gray-500">by {doc.uploadedBy}</span>
                      <span className="text-sm text-gray-500">{doc.uploadedAt}</span>
                      {doc.project && (
                        <Badge variant="outline" className="text-xs">
                          {doc.project}
                        </Badge>
                      )}
                      <Badge variant={getCategoryColor(doc.category)} className="text-xs">
                        {doc.category}
                      </Badge>
                    </div>
                  </div>
                </div>
                <div className="flex space-x-2">
                  <Button variant="ghost" size="sm">
                    <EyeIcon className="h-4 w-4" />
                  </Button>
                  <Button variant="ghost" size="sm">
                    <DocumentArrowDownIcon className="h-4 w-4" />
                  </Button>
                  <Button variant="ghost" size="sm">
                    <PencilIcon className="h-4 w-4" />
                  </Button>
                  <Button variant="ghost" size="sm" className="text-red-600">
                    <TrashIcon className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>

      {documents.length === 0 && !isLoading && (
        <Card>
          <CardContent className="text-center py-12">
            <div className="text-gray-500">
              <FolderIcon className="h-12 w-12 mx-auto mb-4 text-gray-300" />
              <h3 className="text-lg font-medium mb-2">No documents found</h3>
              <p className="text-gray-600">Try adjusting your filters or upload a new document.</p>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  );
}
