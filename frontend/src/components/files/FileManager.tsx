import { useState, useCallback } from 'react'
import { useDropzone } from 'react-dropzone'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { fileService, FileUpload } from '../../services/fileService'
import { 
  Upload, 
  Download, 
  Trash2, 
  Eye, 
  MoreVertical, 
  File, 
  Folder,
  Search,
  Filter,
  Grid,
  List
} from 'lucide-react'
import { formatDate } from '../../lib/utils'
import toast from 'react-hot-toast'

interface FileManagerProps {
  projectId?: string
  taskId?: string
  onFileSelect?: (file: FileUpload) => void
}

export default function FileManager({ projectId, taskId, onFileSelect }: FileManagerProps) {
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid')
  const [searchTerm, setSearchTerm] = useState('')
  const [filterType, setFilterType] = useState('all')
  const [selectedFiles, setSelectedFiles] = useState<string[]>([])

  const queryClient = useQueryClient()

  const { data: files, isLoading } = useQuery({
    queryKey: ['files', { project_id: projectId, task_id: taskId }],
    queryFn: () => fileService.getFiles({ 
      project_id: projectId, 
      task_id: taskId,
      per_page: 100 
    }),
  })

  const deleteFileMutation = useMutation({
    mutationFn: fileService.deleteFile,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['files'] })
      toast.success('File deleted successfully')
    },
    onError: (error: Error) => {
      toast.error(error.message)
    }
  })

  const onDrop = useCallback(async (acceptedFiles: File[]) => {
    const uploadPromises = acceptedFiles.map(async (file) => {
      try {
        await fileService.uploadFile(
          file,
          projectId,
          taskId,
          (progress) => {
            // Handle upload progress
            console.log(`Uploading ${file.name}: ${progress}%`)
          }
        )
        toast.success(`${file.name} uploaded successfully`)
      } catch (error) {
        toast.error(`Failed to upload ${file.name}`)
      }
    })

    await Promise.all(uploadPromises)
    queryClient.invalidateQueries({ queryKey: ['files'] })
  }, [projectId, taskId, queryClient])

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    accept: {
      'image/*': ['.png', '.jpg', '.jpeg', '.gif', '.svg'],
      'application/pdf': ['.pdf'],
      'text/*': ['.txt', '.md', '.csv'],
      'application/vnd.openxmlformats-officedocument.*': ['.docx', '.xlsx', '.pptx'],
      'application/zip': ['.zip', '.rar'],
    },
    maxSize: 10 * 1024 * 1024, // 10MB
  })

  const handleDownload = async (file: FileUpload) => {
    try {
      const blob = await fileService.downloadFile(file.id)
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = file.name
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(url)
    } catch (error) {
      toast.error('Failed to download file')
    }
  }

  const handleDelete = (fileId: string) => {
    if (window.confirm('Are you sure you want to delete this file?')) {
      deleteFileMutation.mutate(fileId)
    }
  }

  const handleFileSelect = (file: FileUpload) => {
    if (onFileSelect) {
      onFileSelect(file)
    }
  }

  const filteredFiles = files?.data?.filter(file => {
    const matchesSearch = file.name.toLowerCase().includes(searchTerm.toLowerCase())
    const matchesType = filterType === 'all' || file.type.startsWith(filterType)
    return matchesSearch && matchesType
  }) || []

  const fileTypes = [
    { value: 'all', label: 'All Files' },
    { value: 'image', label: 'Images' },
    { value: 'application/pdf', label: 'PDFs' },
    { value: 'text', label: 'Text Files' },
    { value: 'application/vnd.openxmlformats-officedocument', label: 'Office Files' },
    { value: 'application/zip', label: 'Archives' },
  ]

  if (isLoading) {
    return (
      <div className="space-y-4">
        <div className="skeleton h-10 w-full" />
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {Array.from({ length: 6 }).map((_, i) => (
            <div key={i} className="skeleton h-32 w-full" />
          ))}
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">File Manager</h2>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            Upload and manage your project files
          </p>
        </div>
        <div className="flex items-center space-x-2">
          <button
            onClick={() => setViewMode('grid')}
            className={`p-2 rounded-md ${viewMode === 'grid' ? 'bg-primary-100 text-primary-600' : 'text-gray-400 hover:text-gray-600'}`}
          >
            <Grid className="h-5 w-5" />
          </button>
          <button
            onClick={() => setViewMode('list')}
            className={`p-2 rounded-md ${viewMode === 'list' ? 'bg-primary-100 text-primary-600' : 'text-gray-400 hover:text-gray-600'}`}
          >
            <List className="h-5 w-5" />
          </button>
        </div>
      </div>

      {/* Upload Area */}
      <div
        {...getRootProps()}
        className={`border-2 border-dashed rounded-lg p-8 text-center cursor-pointer transition-colors ${
          isDragActive
            ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
            : 'border-gray-300 dark:border-gray-600 hover:border-primary-400'
        }`}
      >
        <input {...getInputProps()} />
        <Upload className="h-12 w-12 text-gray-400 mx-auto mb-4" />
        <p className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
          {isDragActive ? 'Drop files here' : 'Drag & drop files here'}
        </p>
        <p className="text-sm text-gray-500 dark:text-gray-400">
          or click to select files (max 10MB)
        </p>
      </div>

      {/* Search and Filter */}
      <div className="flex items-center space-x-4">
        <div className="flex-1 relative">
          <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
          <input
            type="text"
            placeholder="Search files..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="input pl-10"
          />
        </div>
        <select
          value={filterType}
          onChange={(e) => setFilterType(e.target.value)}
          className="input"
        >
          {fileTypes.map(type => (
            <option key={type.value} value={type.value}>
              {type.label}
            </option>
          ))}
        </select>
      </div>

      {/* Files Grid/List */}
      {filteredFiles.length === 0 ? (
        <div className="text-center py-12">
          <Folder className="h-12 w-12 text-gray-400 mx-auto mb-4" />
          <h3 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
            No files found
          </h3>
          <p className="text-gray-500 dark:text-gray-400">
            Upload some files to get started
          </p>
        </div>
      ) : (
        <div className={viewMode === 'grid' 
          ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4'
          : 'space-y-2'
        }>
          {filteredFiles.map((file) => (
            <div
              key={file.id}
              className={`card hover-lift cursor-pointer ${
                viewMode === 'list' ? 'flex items-center space-x-4' : ''
              }`}
              onClick={() => handleFileSelect(file)}
            >
              <div className="card-content">
                {viewMode === 'grid' ? (
                  <div className="text-center">
                    <div className="text-4xl mb-2">
                      {fileService.getFileIcon(file.type)}
                    </div>
                    <h3 className="font-medium text-gray-900 dark:text-gray-100 truncate">
                      {file.name}
                    </h3>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                      {fileService.formatFileSize(file.size)}
                    </p>
                    <p className="text-xs text-gray-400 dark:text-gray-500">
                      {formatDate(file.uploaded_at)}
                    </p>
                  </div>
                ) : (
                  <div className="flex items-center space-x-4 w-full">
                    <div className="text-2xl">
                      {fileService.getFileIcon(file.type)}
                    </div>
                    <div className="flex-1 min-w-0">
                      <h3 className="font-medium text-gray-900 dark:text-gray-100 truncate">
                        {file.name}
                      </h3>
                      <p className="text-sm text-gray-500 dark:text-gray-400">
                        {fileService.formatFileSize(file.size)} • {formatDate(file.uploaded_at)}
                      </p>
                    </div>
                    <div className="flex items-center space-x-2">
                      <button
                        onClick={(e) => {
                          e.stopPropagation()
                          handleDownload(file)
                        }}
                        className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                      >
                        <Download className="h-4 w-4" />
                      </button>
                      <button
                        onClick={(e) => {
                          e.stopPropagation()
                          window.open(fileService.getFilePreviewUrl(file), '_blank')
                        }}
                        className="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                      >
                        <Eye className="h-4 w-4" />
                      </button>
                      <button
                        onClick={(e) => {
                          e.stopPropagation()
                          handleDelete(file.id)
                        }}
                        className="p-2 text-red-400 hover:text-red-600 dark:hover:text-red-300"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Selected Files Actions */}
      {selectedFiles.length > 0 && (
        <div className="fixed bottom-4 right-4 bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center space-x-4">
            <span className="text-sm text-gray-600 dark:text-gray-400">
              {selectedFiles.length} file(s) selected
            </span>
            <button
              onClick={() => setSelectedFiles([])}
              className="btn btn-outline btn-sm"
            >
              Clear
            </button>
            <button
              onClick={() => {
                // Handle bulk download
                selectedFiles.forEach(fileId => {
                  const file = files?.data?.find(f => f.id === fileId)
                  if (file) handleDownload(file)
                })
              }}
              className="btn btn-primary btn-sm"
            >
              Download All
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
