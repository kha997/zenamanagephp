import React from 'react'
import DocumentCenter from '@/components/DocumentCenter'

const DocumentCenterPage: React.FC = () => {
  // Mock data for demonstration
  const mockDocuments = [
    {
      id: '1',
      name: 'Architectural Drawings - Floor Plan',
      originalName: 'floor_plan_v2.pdf',
      description: 'Updated floor plan with new layout changes',
      type: 'drawing' as const,
      category: 'architectural' as const,
      filePath: '/documents/floor_plan_v2.pdf',
      fileSize: 2048576,
      mimeType: 'application/pdf',
      version: '2.0',
      isLatestVersion: true,
      status: 'approved' as const,
      tags: ['floor-plan', 'architectural', 'layout'],
      uploadedBy: {
        id: '1',
        name: 'John Doe',
        avatar: undefined
      },
      approvedBy: {
        id: '2',
        name: 'Jane Smith'
      },
      project: {
        id: '1',
        name: 'Building Project A'
      },
      downloadCount: 15,
      lastAccessedAt: new Date('2024-01-15'),
      approvedAt: new Date('2024-01-10'),
      createdAt: new Date('2024-01-08'),
      updatedAt: new Date('2024-01-10')
    },
    {
      id: '2',
      name: 'Structural Analysis Report',
      originalName: 'structural_analysis.xlsx',
      description: 'Detailed structural analysis with calculations',
      type: 'report' as const,
      category: 'structural' as const,
      filePath: '/documents/structural_analysis.xlsx',
      fileSize: 1024000,
      mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      version: '1.0',
      isLatestVersion: true,
      status: 'pending_approval' as const,
      tags: ['structural', 'analysis', 'calculations'],
      uploadedBy: {
        id: '3',
        name: 'Mike Johnson',
        avatar: undefined
      },
      project: {
        id: '1',
        name: 'Building Project A'
      },
      downloadCount: 3,
      createdAt: new Date('2024-01-12'),
      updatedAt: new Date('2024-01-12')
    },
    {
      id: '3',
      name: 'Construction Photos - Week 1',
      originalName: 'construction_photos_week1.zip',
      description: 'Progress photos from first week of construction',
      type: 'photo' as const,
      category: 'other' as const,
      filePath: '/documents/construction_photos_week1.zip',
      fileSize: 15728640,
      mimeType: 'application/zip',
      version: '1.0',
      isLatestVersion: true,
      status: 'approved' as const,
      tags: ['photos', 'progress', 'construction'],
      uploadedBy: {
        id: '4',
        name: 'Sarah Wilson',
        avatar: undefined
      },
      approvedBy: {
        id: '2',
        name: 'Jane Smith'
      },
      project: {
        id: '1',
        name: 'Building Project A'
      },
      downloadCount: 8,
      lastAccessedAt: new Date('2024-01-14'),
      approvedAt: new Date('2024-01-13'),
      createdAt: new Date('2024-01-12'),
      updatedAt: new Date('2024-01-13')
    }
  ]

  const handleUpload = (files: File[]) => {
    console.log('Files uploaded:', files)
  }

  const handleDownload = (documentId: string) => {
    console.log('Download document:', documentId)
  }

  const handleView = (documentId: string) => {
    console.log('View document:', documentId)
  }

  const handleEdit = (documentId: string) => {
    console.log('Edit document:', documentId)
  }

  const handleDelete = (documentId: string) => {
    console.log('Delete document:', documentId)
  }

  const handleApprove = (documentId: string) => {
    console.log('Approve document:', documentId)
  }

  const handleReject = (documentId: string, reason: string) => {
    console.log('Reject document:', documentId, reason)
  }

  const handleTagUpdate = (documentId: string, tags: string[]) => {
    console.log('Update tags:', documentId, tags)
  }

  return (
    <div className="container mx-auto p-6">
      <div className="mb-6">
        <h1 className="text-3xl font-bold text-gray-900">Document Center</h1>
        <p className="text-gray-600 mt-2">
          Manage project documents, drawings, and files
        </p>
      </div>
      
      <DocumentCenter
        documents={mockDocuments}
        onUpload={handleUpload}
        onDownload={handleDownload}
        onView={handleView}
        onEdit={handleEdit}
        onDelete={handleDelete}
        onApprove={handleApprove}
        onReject={handleReject}
        onTagUpdate={handleTagUpdate}
      />
    </div>
  )
}

export default DocumentCenterPage
