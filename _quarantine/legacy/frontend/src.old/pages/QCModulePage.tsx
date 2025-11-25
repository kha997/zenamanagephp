import React from 'react'
import QCModule from '@/components/QCModule'

const QCModulePage: React.FC = () => {
  // Mock data for demonstration
  const mockQCItems = [
    {
      id: '1',
      name: 'Foundation Quality Inspection',
      description: 'Comprehensive quality inspection of foundation work',
      type: 'inspection' as const,
      status: 'passed' as const,
      priority: 'high' as const,
      category: 'quality' as const,
      project: {
        id: '1',
        name: 'Building Project A'
      },
      component: {
        id: '1',
        name: 'Foundation'
      },
      assignedTo: {
        id: '1',
        name: 'John Doe',
        avatar: undefined
      },
      createdBy: {
        id: '2',
        name: 'Jane Smith'
      },
      dueDate: new Date('2024-01-20'),
      completedDate: new Date('2024-01-18'),
      score: 85,
      maxScore: 100,
      findings: [
        {
          id: '1',
          description: 'Minor crack in concrete surface',
          severity: 'low' as const,
          status: 'resolved' as const,
          category: 'defect' as const,
          location: 'North wall',
          correctiveAction: 'Applied sealant',
          assignedTo: {
            id: '3',
            name: 'Mike Johnson'
          },
          resolvedDate: new Date('2024-01-19')
        }
      ],
      attachments: [
        {
          id: '1',
          name: 'inspection_photos.jpg',
          type: 'image' as const,
          url: '/attachments/inspection_photos.jpg',
          uploadedBy: {
            id: '1',
            name: 'John Doe'
          },
          uploadedAt: new Date('2024-01-18')
        }
      ],
      tags: ['foundation', 'concrete', 'inspection'],
      createdAt: new Date('2024-01-15'),
      updatedAt: new Date('2024-01-19')
    },
    {
      id: '2',
      name: 'Electrical System Test',
      description: 'Safety and functionality test of electrical systems',
      type: 'test' as const,
      status: 'in_progress' as const,
      priority: 'critical' as const,
      category: 'safety' as const,
      project: {
        id: '1',
        name: 'Building Project A'
      },
      component: {
        id: '2',
        name: 'Electrical System'
      },
      assignedTo: {
        id: '4',
        name: 'Sarah Wilson',
        avatar: undefined
      },
      createdBy: {
        id: '2',
        name: 'Jane Smith'
      },
      dueDate: new Date('2024-01-25'),
      score: 0,
      maxScore: 100,
      findings: [
        {
          id: '2',
          description: 'Ground fault detected in main panel',
          severity: 'high' as const,
          status: 'open' as const,
          category: 'defect' as const,
          location: 'Main electrical panel',
          assignedTo: {
            id: '5',
            name: 'Tom Brown'
          },
          dueDate: new Date('2024-01-22')
        }
      ],
      attachments: [],
      tags: ['electrical', 'safety', 'testing'],
      createdAt: new Date('2024-01-20'),
      updatedAt: new Date('2024-01-20')
    },
    {
      id: '3',
      name: 'Material Compliance Review',
      description: 'Review of material specifications and compliance',
      type: 'review' as const,
      status: 'pending' as const,
      priority: 'medium' as const,
      category: 'compliance' as const,
      project: {
        id: '1',
        name: 'Building Project A'
      },
      assignedTo: {
        id: '6',
        name: 'Lisa Davis',
        avatar: undefined
      },
      createdBy: {
        id: '2',
        name: 'Jane Smith'
      },
      dueDate: new Date('2024-01-30'),
      findings: [],
      attachments: [],
      tags: ['materials', 'compliance', 'review'],
      createdAt: new Date('2024-01-22'),
      updatedAt: new Date('2024-01-22')
    }
  ]

  const handleCreateQC = (qcData: any) => {
    console.log('Create QC:', qcData)
  }

  const handleUpdateQC = (qcId: string, updates: any) => {
    console.log('Update QC:', qcId, updates)
  }

  const handleDeleteQC = (qcId: string) => {
    console.log('Delete QC:', qcId)
  }

  const handleAddFinding = (qcId: string, finding: any) => {
    console.log('Add finding:', qcId, finding)
  }

  const handleUpdateFinding = (qcId: string, findingId: string, updates: any) => {
    console.log('Update finding:', qcId, findingId, updates)
  }

  const handleUploadAttachment = (qcId: string, files: File[]) => {
    console.log('Upload attachment:', qcId, files)
  }

  return (
    <div className="container mx-auto p-6">
      <div className="mb-6">
        <h1 className="text-3xl font-bold text-gray-900">Quality Control Module</h1>
        <p className="text-gray-600 mt-2">
          Manage quality inspections, tests, and compliance reviews
        </p>
      </div>
      
      <QCModule
        qcItems={mockQCItems}
        onCreateQC={handleCreateQC}
        onUpdateQC={handleUpdateQC}
        onDeleteQC={handleDeleteQC}
        onAddFinding={handleAddFinding}
        onUpdateFinding={handleUpdateFinding}
        onUploadAttachment={handleUploadAttachment}
      />
    </div>
  )
}

export default QCModulePage
