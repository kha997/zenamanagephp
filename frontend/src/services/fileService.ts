import apiClient from '../lib/api'

export interface FileUpload {
  id: string
  name: string
  size: number
  type: string
  url: string
  uploaded_at: string
  uploaded_by: string
  project_id?: string
  task_id?: string
}

export interface FileUploadProgress {
  file: File
  progress: number
  status: 'uploading' | 'completed' | 'error'
  error?: string
}

export const fileService = {
  // Upload file
  async uploadFile(
    file: File, 
    projectId?: string, 
    taskId?: string,
    onProgress?: (progress: number) => void
  ): Promise<FileUpload> {
    const formData = new FormData()
    formData.append('file', file)
    if (projectId) formData.append('project_id', projectId)
    if (taskId) formData.append('task_id', taskId)

    const response = await apiClient.post<FileUpload>('/files/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      onUploadProgress: (progressEvent) => {
        if (onProgress && progressEvent.total) {
          const progress = Math.round((progressEvent.loaded * 100) / progressEvent.total)
          onProgress(progress)
        }
      },
    })

    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to upload file')
  },

  // Get files
  async getFiles(filters: {
    project_id?: string
    task_id?: string
    type?: string
    page?: number
    per_page?: number
  } = {}): Promise<{ data: FileUpload[]; pagination: any }> {
    const response = await apiClient.get<{ data: FileUpload[]; pagination: any }>('/files', filters)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get files')
  },

  // Get file by ID
  async getFileById(id: string): Promise<FileUpload> {
    const response = await apiClient.get<FileUpload>(`/files/${id}`)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to get file')
  },

  // Download file
  async downloadFile(id: string): Promise<Blob> {
    const response = await fetch(`/api/v1/files/${id}/download`, {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
      }
    })
    
    if (!response.ok) {
      throw new Error('Failed to download file')
    }
    
    return response.blob()
  },

  // Delete file
  async deleteFile(id: string): Promise<void> {
    const response = await apiClient.delete(`/files/${id}`)
    if (response.status !== 'success') {
      throw new Error(response.message || 'Failed to delete file')
    }
  },

  // Update file metadata
  async updateFile(id: string, data: Partial<FileUpload>): Promise<FileUpload> {
    const response = await apiClient.put<FileUpload>(`/files/${id}`, data)
    if (response.status === 'success' && response.data) {
      return response.data
    }
    throw new Error(response.message || 'Failed to update file')
  },

  // Get file preview URL
  getFilePreviewUrl(file: FileUpload): string {
    return `/api/v1/files/${file.id}/preview`
  },

  // Get file download URL
  getFileDownloadUrl(file: FileUpload): string {
    return `/api/v1/files/${file.id}/download`
  },

  // Format file size
  formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes'
    
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
  },

  // Get file icon based on type
  getFileIcon(type: string): string {
    if (type.startsWith('image/')) return '🖼️'
    if (type.startsWith('video/')) return '🎥'
    if (type.startsWith('audio/')) return '🎵'
    if (type.includes('pdf')) return '📄'
    if (type.includes('word') || type.includes('document')) return '📝'
    if (type.includes('excel') || type.includes('spreadsheet')) return '📊'
    if (type.includes('powerpoint') || type.includes('presentation')) return '📽️'
    if (type.includes('zip') || type.includes('archive')) return '📦'
    if (type.includes('text')) return '📄'
    return '📁'
  },

  // Validate file type
  validateFileType(file: File, allowedTypes: string[]): boolean {
    return allowedTypes.some(type => {
      if (type.endsWith('/*')) {
        return file.type.startsWith(type.slice(0, -1))
      }
      return file.type === type
    })
  },

  // Validate file size
  validateFileSize(file: File, maxSizeInMB: number): boolean {
    const maxSizeInBytes = maxSizeInMB * 1024 * 1024
    return file.size <= maxSizeInBytes
  }
}
