import React from 'react'
import ChangeRequestsModule from '@/components/ChangeRequestsModule'

const ChangeRequestsPage: React.FC = () => {
  // Mock data for demonstration
  const mockChangeRequests = [
    {
      id: '1',
      title: 'Additional Floor Space Request',
      description: 'Client requests additional 200 sqm of office space on 3rd floor',
      type: 'scope' as const,
      priority: 'high' as const,
      status: 'pending' as const,
      project: {
        id: '1',
        name: 'Building Project A'
      },
      requestedBy: {
        id: '1',
        name: 'John Doe',
        avatar: undefined
      },
      costImpact: 50000000,
      timeImpact: 15,
      impactAnalysis: {
        description: 'Additional space requires structural modifications and MEP adjustments',
        affectedAreas: ['3rd Floor', 'Structural', 'MEP Systems'],
        risks: ['Schedule delay', 'Cost overrun'],
        benefits: ['Increased revenue', 'Client satisfaction']
      },
      riskAssessment: {
        level: 'medium' as const,
        description: 'Moderate risk due to structural modifications required',
        mitigation: ['Early structural analysis', 'Phased implementation']
      },
      implementationPlan: {
        phases: [
          {
            id: '1',
            name: 'Design Phase',
            description: 'Update architectural and structural designs',
            duration: 5,
            dependencies: [],
            deliverables: ['Updated drawings', 'Structural analysis']
          },
          {
            id: '2',
            name: 'Construction Phase',
            description: 'Execute structural modifications',
            duration: 10,
            dependencies: ['1'],
            deliverables: ['Completed modifications']
          }
        ],
        timeline: '15 days',
        resources: ['Architect', 'Structural Engineer', 'Construction Team']
      },
      requestedAt: new Date('2024-01-20'),
      tags: ['scope', 'floor-space', 'client-request'],
      attachments: [
        {
          id: '1',
          name: 'client_request.pdf',
          type: 'document' as const,
          url: '/attachments/client_request.pdf',
          uploadedBy: {
            id: '1',
            name: 'John Doe'
          },
          uploadedAt: new Date('2024-01-20')
        }
      ],
      createdAt: new Date('2024-01-20'),
      updatedAt: new Date('2024-01-20')
    },
    {
      id: '2',
      title: 'Budget Increase for Premium Materials',
      description: 'Upgrade to premium materials for better durability and aesthetics',
      type: 'budget' as const,
      priority: 'medium' as const,
      status: 'approved' as const,
      project: {
        id: '1',
        name: 'Building Project A'
      },
      requestedBy: {
        id: '2',
        name: 'Jane Smith',
        avatar: undefined
      },
      approvedBy: {
        id: '3',
        name: 'Mike Johnson'
      },
      costImpact: 25000000,
      timeImpact: 0,
      impactAnalysis: {
        description: 'Premium materials will improve building quality and longevity',
        affectedAreas: ['Materials', 'Budget'],
        risks: ['Cost increase'],
        benefits: ['Better quality', 'Long-term durability', 'Higher property value']
      },
      riskAssessment: {
        level: 'low' as const,
        description: 'Low risk as it only affects material costs',
        mitigation: ['Supplier negotiations', 'Bulk purchasing']
      },
      implementationPlan: {
        phases: [
          {
            id: '1',
            name: 'Supplier Selection',
            description: 'Identify and negotiate with premium material suppliers',
            duration: 3,
            dependencies: [],
            deliverables: ['Supplier contracts', 'Material specifications']
          }
        ],
        timeline: '3 days',
        resources: ['Procurement Team', 'Project Manager']
      },
      approvalNotes: 'Approved due to long-term benefits and quality improvement',
      requestedAt: new Date('2024-01-18'),
      approvedAt: new Date('2024-01-19'),
      tags: ['budget', 'materials', 'upgrade'],
      attachments: [],
      createdAt: new Date('2024-01-18'),
      updatedAt: new Date('2024-01-19')
    },
    {
      id: '3',
      title: 'Timeline Extension Request',
      description: 'Need additional 30 days due to weather delays and material shortages',
      type: 'timeline' as const,
      priority: 'critical' as const,
      status: 'rejected' as const,
      project: {
        id: '1',
        name: 'Building Project A'
      },
      requestedBy: {
        id: '4',
        name: 'Sarah Wilson',
        avatar: undefined
      },
      costImpact: 0,
      timeImpact: 30,
      impactAnalysis: {
        description: 'Weather delays and material shortages affecting construction progress',
        affectedAreas: ['Schedule', 'Resource Planning'],
        risks: ['Client dissatisfaction', 'Contract penalties'],
        benefits: ['Quality completion', 'Safety compliance']
      },
      riskAssessment: {
        level: 'high' as const,
        description: 'High risk due to potential contract penalties and client impact',
        mitigation: ['Client communication', 'Alternative scheduling', 'Resource reallocation']
      },
      implementationPlan: {
        phases: [],
        timeline: '30 days',
        resources: ['Project Team', 'Client Liaison']
      },
      rejectionReason: 'Client cannot accept timeline extension due to business requirements',
      requestedAt: new Date('2024-01-15'),
      tags: ['timeline', 'delay', 'weather'],
      attachments: [],
      createdAt: new Date('2024-01-15'),
      updatedAt: new Date('2024-01-16')
    }
  ]

  const handleCreateCR = (crData: any) => {
    console.log('Create CR:', crData)
  }

  const handleUpdateCR = (crId: string, updates: any) => {
    console.log('Update CR:', crId, updates)
  }

  const handleDeleteCR = (crId: string) => {
    console.log('Delete CR:', crId)
  }

  const handleApproveCR = (crId: string, notes: string) => {
    console.log('Approve CR:', crId, notes)
  }

  const handleRejectCR = (crId: string, reason: string) => {
    console.log('Reject CR:', crId, reason)
  }

  const handleImplementCR = (crId: string) => {
    console.log('Implement CR:', crId)
  }

  const handleUploadAttachment = (crId: string, files: File[]) => {
    console.log('Upload attachment:', crId, files)
  }

  return (
    <div className="container mx-auto p-6">
      <div className="mb-6">
        <h1 className="text-3xl font-bold text-gray-900">Change Requests Module</h1>
        <p className="text-gray-600 mt-2">
          Manage project change requests, approvals, and implementations
        </p>
      </div>
      
      <ChangeRequestsModule
        changeRequests={mockChangeRequests}
        onCreateCR={handleCreateCR}
        onUpdateCR={handleUpdateCR}
        onDeleteCR={handleDeleteCR}
        onApproveCR={handleApproveCR}
        onRejectCR={handleRejectCR}
        onImplementCR={handleImplementCR}
        onUploadAttachment={handleUploadAttachment}
      />
    </div>
  )
}

export default ChangeRequestsPage
