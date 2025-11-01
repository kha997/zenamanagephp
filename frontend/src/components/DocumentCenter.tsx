import React, { useState, useCallback } from 'react'
import { useDropzone } from 'react-dropzone'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { 
  FileText, 
  Upload, 
  Download, 
  Eye, 
  Edit, 
  Trash2, 
  Search, 
  Filter,
  Folder,
  Image,
  File,
  Archive,
  CheckCircle,
  XCircle,
  Clock,
  Users,
  Tag
} from 'lucide-react'
import { cn } from '@/lib/utils'

interface Document {
  id: string
  name: string
  originalName: string
  description?: string
  type: 'drawing' | 'contract' | 'specification' | 'report' | 'photo' | 'other'
  category: 'architectural' | 'structural' | 'mep' | 'civil' | 'landscape' | 'other'
  filePath: string
  fileSize: number
  mimeType: string
  version: string
  isLatestVersion: boolean
  status: 'draft' | 'pending_approval' | 'approved' | 'rejected' | 'superseded'
  tags: string[]
  uploadedBy: {
    id: string
    name: string
    avatar?: string
  }
  approvedBy?: {
    id: string
    name: string
  }
  project?: {
    id: string
    name: string
  }
  task?: {
    id: string
    name: string
  }
  component?: {
    id: string
    name: string
  }
  downloadCount: number
  lastAccessedAt?: Date
  approvedAt?: Date
  createdAt: Date
  updatedAt: Date
}

interface DocumentCenterProps {
  documents: Document[]
  onUpload?: (files: File[]) => void
  onDownload?: (documentId: string) => void
  onView?: (documentId: string) => void
  onEdit?: (documentId: string) => void
  onDelete?: (documentId: string) => void
  onApprove?: (documentId: string) => void
  onReject?: (documentId: string, reason: string) => void
  onTagUpdate?: (documentId: string, tags: string[]) => void
}

const DocumentCenter: React.FC<DocumentCenterProps> = ({
  documents,
  onUpload,
  onDownload,
  onView,
  onEdit,
  onDelete,
  onApprove,
  onReject,
  onTagUpdate
}) => {
  const [searchTerm, setSearchTerm] = useState('')
  const [filterType, setFilterType] = useState<string>('all')
  const [filterCategory, setFilterCategory] = useState<string>('all')
  const [filterStatus, setFilterStatus] = useState<string>('all')
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid')
  const [selectedDocuments, setSelectedDocuments] = useState<string[]>([])

  const onDrop = useCallback((acceptedFiles: File[]) => {
    if (onUpload) {
      onUpload(acceptedFiles)
    }
  }, [onUpload])

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      'image/*': ['.png', '.jpg', '.jpeg', '.gif', '.bmp', '.svg'],
      'application/pdf': ['.pdf'],
      'application/msword': ['.doc'],
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document': ['.docx'],
      'application/vnd.ms-excel': ['.xls'],
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': ['.xlsx'],
      'text/plain': ['.txt'],
      'application/zip': ['.zip'],
      'application/x-rar-compressed': ['.rar']
    },
    multiple: true
  })

  const filteredDocuments = documents.filter(doc => {
    const matchesSearch = doc.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         doc.description?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         doc.tags.some(tag => tag.toLowerCase().includes(searchTerm.toLowerCase()))
    const matchesType = filterType === 'all' || doc.type === filterType
    const matchesCategory = filterCategory === 'all' || doc.category === filterCategory
    const matchesStatus = filterStatus === 'all' || doc.status === filterStatus
    
    return matchesSearch && matchesType && matchesCategory && matchesStatus
  })

  const getFileIcon = (mimeType: string, type: string) => {
    if (mimeType.startsWith('image/')) return <Image className="h-5 w-5" />
    if (mimeType === 'application/pdf') return <FileText className="h-5 w-5" />
    if (mimeType.includes('word') || mimeType.includes('document')) return <FileText className="h-5 w-5" />
    if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return <FileText className="h-5 w-5" />
    if (mimeType.includes('zip') || mimeType.includes('rar')) return <Archive className="h-5 w-5" />
    return <File className="h-5 w-5" />
  }

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'draft': return 'bg-gray-100 text-gray-800'
      case 'pending_approval': return 'bg-yellow-100 text-yellow-800'
      case 'approved': return 'bg-green-100 text-green-800'
      case 'rejected': return 'bg-red-100 text-red-800'
      case 'superseded': return 'bg-gray-100 text-gray-800'
      default: return 'bg-gray-100 text-gray-800'
    }
  }

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'approved': return <CheckCircle className="h-4 w-4" />
      case 'rejected': return <XCircle className="h-4 w-4" />
      case 'pending_approval': return <Clock className="h-4 w-4" />
      default: return <File className="h-4 w-4" />
    }
  }

  const formatFileSize = (bytes: number) => {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
  }

  const formatDate = (date: Date) => {
    return new Intl.DateTimeFormat('vi-VN', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    }).format(date)
  }

  return (
    <div className="space-y-4">
      {/* Header */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle className="flex items-center gap-2">
              <Folder className="h-5 w-5" />
              Document Center
            </CardTitle>
            <div className="flex items-center gap-2">
              <Button
                variant={viewMode === 'grid' ? 'default' : 'outline'}
                size="sm"
                onClick={() => setViewMode('grid')}
              >
                Grid
              </Button>
              <Button
                variant={viewMode === 'list' ? 'default' : 'outline'}
                size="sm"
                onClick={() => setViewMode('list')}
              >
                List
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {/* Search and Filters */}
          <div className="flex items-center gap-4 mb-4">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
              <Input
                placeholder="Search documents..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10"
              />
            </div>
            
            <select
              value={filterType}
              onChange={(e) => setFilterType(e.target.value)}
              className="px-3 py-2 border rounded text-sm"
            >
              <option value="all">All Types</option>
              <option value="drawing">Drawing</option>
              <option value="contract">Contract</option>
              <option value="specification">Specification</option>
              <option value="report">Report</option>
              <option value="photo">Photo</option>
              <option value="other">Other</option>
            </select>

            <select
              value={filterCategory}
              onChange={(e) => setFilterCategory(e.target.value)}
              className="px-3 py-2 border rounded text-sm"
            >
              <option value="all">All Categories</option>
              <option value="architectural">Architectural</option>
              <option value="structural">Structural</option>
              <option value="mep">MEP</option>
              <option value="civil">Civil</option>
              <option value="landscape">Landscape</option>
              <option value="other">Other</option>
            </select>

            <select
              value={filterStatus}
              onChange={(e) => setFilterStatus(e.target.value)}
              className="px-3 py-2 border rounded text-sm"
            >
              <option value="all">All Status</option>
              <option value="draft">Draft</option>
              <option value="pending_approval">Pending Approval</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="superseded">Superseded</option>
            </select>
          </div>

          {/* Upload Area */}
          <div
            {...getRootProps()}
            className={cn(
              'border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-colors',
              isDragActive ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-gray-400'
            )}
          >
            <input {...getInputProps()} />
            <Upload className="h-12 w-12 mx-auto mb-4 text-gray-400" />
            <p className="text-lg font-medium mb-2">
              {isDragActive ? 'Drop files here' : 'Drag & drop files here'}
            </p>
            <p className="text-sm text-gray-500 mb-4">
              or click to select files
            </p>
            <Button variant="outline">
              <Upload className="h-4 w-4 mr-2" />
              Upload Files
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Documents */}
      {viewMode === 'grid' ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {filteredDocuments.map((doc) => (
            <Card key={doc.id} className="hover:shadow-lg transition-shadow">
              <CardContent className="p-4">
                <div className="flex items-start justify-between mb-3">
                  <div className="flex items-center gap-2">
                    {getFileIcon(doc.mimeType, doc.type)}
                    <div className="flex-1 min-w-0">
                      <h3 className="font-medium text-sm truncate">{doc.name}</h3>
                      <p className="text-xs text-gray-500">{formatFileSize(doc.fileSize)}</p>
                    </div>
                  </div>
                  <Badge className={cn('text-xs', getStatusColor(doc.status))}>
                    {doc.status.replace('_', ' ')}
                  </Badge>
                </div>

                {doc.description && (
                  <p className="text-xs text-gray-600 mb-3 line-clamp-2">{doc.description}</p>
                )}

                <div className="space-y-2 mb-4">
                  <div className="flex items-center gap-2 text-xs text-gray-500">
                    <Users className="h-3 w-3" />
                    <span>{doc.uploadedBy.name}</span>
                  </div>
                  <div className="flex items-center gap-2 text-xs text-gray-500">
                    <Clock className="h-3 w-3" />
                    <span>{formatDate(doc.createdAt)}</span>
                  </div>
                  {doc.project && (
                    <div className="flex items-center gap-2 text-xs text-gray-500">
                      <Folder className="h-3 w-3" />
                      <span>{doc.project.name}</span>
                    </div>
                  )}
                </div>

                {doc.tags.length > 0 && (
                  <div className="flex flex-wrap gap-1 mb-4">
                    {doc.tags.slice(0, 3).map((tag, index) => (
                      <Badge key={index} variant="outline" className="text-xs">
                        <Tag className="h-2 w-2 mr-1" />
                        {tag}
                      </Badge>
                    ))}
                    {doc.tags.length > 3 && (
                      <Badge variant="outline" className="text-xs">
                        +{doc.tags.length - 3}
                      </Badge>
                    )}
                  </div>
                )}

                <div className="flex items-center gap-1">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onView?.(doc.id)}
                    className="flex-1"
                  >
                    <Eye className="h-3 w-3 mr-1" />
                    View
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onDownload?.(doc.id)}
                  >
                    <Download className="h-3 w-3" />
                  </Button>
                  {doc.status === 'pending_approval' && (
                    <>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onApprove?.(doc.id)}
                        className="text-green-600 hover:text-green-700"
                      >
                        <CheckCircle className="h-3 w-3" />
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onReject?.(doc.id, '')}
                        className="text-red-600 hover:text-red-700"
                      >
                        <XCircle className="h-3 w-3" />
                      </Button>
                    </>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      ) : (
        <Card>
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Name
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Type
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Size
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Uploaded By
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Date
                    </th>
                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                  {filteredDocuments.map((doc) => (
                    <tr key={doc.id} className="hover:bg-gray-50">
                      <td className="px-4 py-4">
                        <div className="flex items-center gap-3">
                          {getFileIcon(doc.mimeType, doc.type)}
                          <div>
                            <div className="font-medium text-sm">{doc.name}</div>
                            {doc.description && (
                              <div className="text-xs text-gray-500">{doc.description}</div>
                            )}
                          </div>
                        </div>
                      </td>
                      <td className="px-4 py-4">
                        <Badge variant="outline" className="text-xs">
                          {doc.type}
                        </Badge>
                      </td>
                      <td className="px-4 py-4">
                        <Badge className={cn('text-xs', getStatusColor(doc.status))}>
                          {getStatusIcon(doc.status)}
                          <span className="ml-1">{doc.status.replace('_', ' ')}</span>
                        </Badge>
                      </td>
                      <td className="px-4 py-4 text-sm text-gray-500">
                        {formatFileSize(doc.fileSize)}
                      </td>
                      <td className="px-4 py-4 text-sm text-gray-500">
                        {doc.uploadedBy.name}
                      </td>
                      <td className="px-4 py-4 text-sm text-gray-500">
                        {formatDate(doc.createdAt)}
                      </td>
                      <td className="px-4 py-4">
                        <div className="flex items-center gap-1">
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onView?.(doc.id)}
                          >
                            <Eye className="h-3 w-3" />
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onDownload?.(doc.id)}
                          >
                            <Download className="h-3 w-3" />
                          </Button>
                          {doc.status === 'pending_approval' && (
                            <>
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => onApprove?.(doc.id)}
                                className="text-green-600 hover:text-green-700"
                              >
                                <CheckCircle className="h-3 w-3" />
                              </Button>
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => onReject?.(doc.id, '')}
                                className="text-red-600 hover:text-red-700"
                              >
                                <XCircle className="h-3 w-3" />
                              </Button>
                            </>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      )}

      {filteredDocuments.length === 0 && (
        <Card>
          <CardContent className="text-center py-12">
            <Folder className="h-12 w-12 mx-auto mb-4 text-gray-400" />
            <h3 className="text-lg font-medium mb-2">No documents found</h3>
            <p className="text-gray-500 mb-4">
              {searchTerm || filterType !== 'all' || filterCategory !== 'all' || filterStatus !== 'all'
                ? 'Try adjusting your search or filters'
                : 'Upload your first document to get started'
              }
            </p>
            <Button onClick={() => {/* Trigger upload */}}>
              <Upload className="h-4 w-4 mr-2" />
              Upload Document
            </Button>
          </CardContent>
        </Card>
      )}
    </div>
  )
}

export default DocumentCenter
