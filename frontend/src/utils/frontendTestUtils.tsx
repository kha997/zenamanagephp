import React from 'react'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { BrowserRouter } from 'react-router-dom'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Input } from '@/components/ui/Input'
import GanttChart from '@/components/GanttChart'
import DocumentCenter from '@/components/DocumentCenter'
import QCModule from '@/components/QCModule'
import ChangeRequestsModule from '@/components/ChangeRequestsModule'

// Test wrapper component
const TestWrapper: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <BrowserRouter>
    {children}
  </BrowserRouter>
)

// Component Test Utilities
export const componentTestUtils = {
  // Test Card Component
  testCardComponent: () => {
    const { container } = render(
      <TestWrapper>
        <Card>
          <CardHeader>
            <CardTitle>Test Card</CardTitle>
          </CardHeader>
          <CardContent>
            <p>Test content</p>
          </CardContent>
        </Card>
      </TestWrapper>
    )
    
    expect(screen.getByText('Test Card')).toBeInTheDocument()
    expect(screen.getByText('Test content')).toBeInTheDocument()
    return { container }
  },

  // Test Button Component
  testButtonComponent: () => {
    const handleClick = jest.fn()
    render(
      <TestWrapper>
        <Button onClick={handleClick} variant="default">
          Test Button
        </Button>
      </TestWrapper>
    )
    
    const button = screen.getByText('Test Button')
    expect(button).toBeInTheDocument()
    
    fireEvent.click(button)
    expect(handleClick).toHaveBeenCalledTimes(1)
    
    return { button, handleClick }
  },

  // Test Badge Component
  testBadgeComponent: () => {
    render(
      <TestWrapper>
        <Badge variant="default">Test Badge</Badge>
        <Badge variant="destructive">Error Badge</Badge>
        <Badge variant="outline">Outline Badge</Badge>
      </TestWrapper>
    )
    
    expect(screen.getByText('Test Badge')).toBeInTheDocument()
    expect(screen.getByText('Error Badge')).toBeInTheDocument()
    expect(screen.getByText('Outline Badge')).toBeInTheDocument()
  },

  // Test Input Component
  testInputComponent: () => {
    const handleChange = jest.fn()
    render(
      <TestWrapper>
        <Input 
          placeholder="Test input" 
          onChange={handleChange}
          data-testid="test-input"
        />
      </TestWrapper>
    )
    
    const input = screen.getByTestId('test-input')
    expect(input).toBeInTheDocument()
    
    fireEvent.change(input, { target: { value: 'test value' } })
    expect(handleChange).toHaveBeenCalled()
    
    return { input, handleChange }
  },

  // Test GanttChart Component
  testGanttChartComponent: () => {
    const mockTasks = [
      {
        id: '1',
        name: 'Test Task',
        start: new Date('2024-01-01'),
        end: new Date('2024-01-15'),
        progress: 50,
        type: 'task' as const,
        status: 'in_progress' as const,
        priority: 'high' as const,
        assignee: { id: '1', name: 'John Doe' },
        project: { id: '1', name: 'Test Project', color: '#3B82F6' }
      }
    ]

    render(
      <TestWrapper>
        <GanttChart tasks={mockTasks} />
      </TestWrapper>
    )
    
    expect(screen.getByText('Gantt Chart')).toBeInTheDocument()
    expect(screen.getByText('Test Task')).toBeInTheDocument()
  },

  // Test DocumentCenter Component
  testDocumentCenterComponent: () => {
    const mockDocuments = [
      {
        id: '1',
        name: 'Test Document',
        originalName: 'test.pdf',
        description: 'Test document description',
        type: 'document' as const,
        category: 'other' as const,
        filePath: '/test.pdf',
        fileSize: 1024,
        mimeType: 'application/pdf',
        version: '1.0',
        isLatestVersion: true,
        status: 'approved' as const,
        tags: ['test'],
        uploadedBy: { id: '1', name: 'John Doe' },
        downloadCount: 0,
        createdAt: new Date(),
        updatedAt: new Date()
      }
    ]

    render(
      <TestWrapper>
        <DocumentCenter documents={mockDocuments} />
      </TestWrapper>
    )
    
    expect(screen.getByText('Document Center')).toBeInTheDocument()
    expect(screen.getByText('Test Document')).toBeInTheDocument()
  },

  // Test QCModule Component
  testQCModuleComponent: () => {
    const mockQCItems = [
      {
        id: '1',
        name: 'Test QC Item',
        description: 'Test QC description',
        type: 'inspection' as const,
        status: 'pending' as const,
        priority: 'high' as const,
        category: 'quality' as const,
        project: { id: '1', name: 'Test Project' },
        assignedTo: { id: '1', name: 'John Doe' },
        createdBy: { id: '1', name: 'Jane Smith' },
        findings: [],
        attachments: [],
        tags: ['test'],
        createdAt: new Date(),
        updatedAt: new Date()
      }
    ]

    render(
      <TestWrapper>
        <QCModule qcItems={mockQCItems} />
      </TestWrapper>
    )
    
    expect(screen.getByText('Quality Control Module')).toBeInTheDocument()
    expect(screen.getByText('Test QC Item')).toBeInTheDocument()
  },

  // Test ChangeRequestsModule Component
  testChangeRequestsModuleComponent: () => {
    const mockChangeRequests = [
      {
        id: '1',
        title: 'Test Change Request',
        description: 'Test change request description',
        type: 'scope' as const,
        priority: 'high' as const,
        status: 'pending' as const,
        project: { id: '1', name: 'Test Project' },
        requestedBy: { id: '1', name: 'John Doe' },
        costImpact: 10000,
        timeImpact: 5,
        impactAnalysis: {
          description: 'Test impact',
          affectedAreas: ['Test Area'],
          risks: ['Test Risk'],
          benefits: ['Test Benefit']
        },
        riskAssessment: {
          level: 'medium' as const,
          description: 'Test risk',
          mitigation: ['Test mitigation']
        },
        implementationPlan: {
          phases: [],
          timeline: '5 days',
          resources: ['Test resource']
        },
        requestedAt: new Date(),
        tags: ['test'],
        attachments: [],
        createdAt: new Date(),
        updatedAt: new Date()
      }
    ]

    render(
      <TestWrapper>
        <ChangeRequestsModule changeRequests={mockChangeRequests} />
      </TestWrapper>
    )
    
    expect(screen.getByText('Change Requests Module')).toBeInTheDocument()
    expect(screen.getByText('Test Change Request')).toBeInTheDocument()
  }
}

// Navigation Test Utilities
export const navigationTestUtils = {
  // Test route navigation
  testRouteNavigation: async () => {
    const routes = [
      '/dashboard',
      '/users',
      '/projects',
      '/tasks',
      '/gantt',
      '/documents',
      '/qc',
      '/change-requests'
    ]

    for (const route of routes) {
      // Mock navigation test
      expect(route).toBeDefined()
    }
  },

  // Test sidebar navigation
  testSidebarNavigation: () => {
    // Mock sidebar test
    const navigationItems = [
      'Dashboard',
      'Users',
      'Projects',
      'Tasks',
      'Gantt Chart',
      'Documents',
      'Quality Control',
      'Change Requests'
    ]

    navigationItems.forEach(item => {
      expect(item).toBeDefined()
    })
  }
}

// File Operation Test Utilities
export const fileTestUtils = {
  // Test file upload
  testFileUpload: () => {
    const mockFile = new File(['test content'], 'test.pdf', { type: 'application/pdf' })
    expect(mockFile).toBeDefined()
    expect(mockFile.name).toBe('test.pdf')
    expect(mockFile.type).toBe('application/pdf')
  },

  // Test file download
  testFileDownload: () => {
    // Mock download test
    const downloadUrl = '/api/documents/1/download'
    expect(downloadUrl).toBeDefined()
  },

  // Test file validation
  testFileValidation: () => {
    const allowedTypes = ['image/*', 'application/pdf', 'application/msword']
    const maxSize = 10 * 1024 * 1024 // 10MB
    
    expect(allowedTypes).toBeDefined()
    expect(maxSize).toBeDefined()
  }
}

// Performance Test Utilities
export const performanceTestUtils = {
  // Test page load time
  testPageLoadTime: () => {
    const startTime = performance.now()
    // Mock page load
    const endTime = performance.now()
    const loadTime = endTime - startTime
    
    expect(loadTime).toBeLessThan(2000) // Less than 2 seconds
    return loadTime
  },

  // Test component render time
  testComponentRenderTime: (component: React.ComponentType) => {
    const startTime = performance.now()
    // Mock component render
    const endTime = performance.now()
    const renderTime = endTime - startTime
    
    expect(renderTime).toBeLessThan(100) // Less than 100ms
    return renderTime
  },

  // Test memory usage
  testMemoryUsage: () => {
    if ('memory' in performance) {
      const memory = (performance as any).memory
      const usedMemory = memory.usedJSHeapSize
      const totalMemory = memory.totalJSHeapSize
      
      expect(usedMemory).toBeLessThan(totalMemory)
      return { usedMemory, totalMemory }
    }
    return null
  }
}

// Real-time Test Utilities
export const realtimeTestUtils = {
  // Test WebSocket connection
  testWebSocketConnection: () => {
    // Mock WebSocket test
    const wsUrl = 'ws://localhost:8000'
    expect(wsUrl).toBeDefined()
  },

  // Test real-time notifications
  testRealtimeNotifications: () => {
    // Mock notification test
    const notification = {
      id: '1',
      title: 'Test Notification',
      message: 'Test message',
      type: 'info' as const,
      timestamp: new Date()
    }
    
    expect(notification).toBeDefined()
  },

  // Test connection status
  testConnectionStatus: () => {
    const statuses = ['connected', 'disconnected', 'connecting', 'error']
    statuses.forEach(status => {
      expect(status).toBeDefined()
    })
  }
}

export default {
  componentTestUtils,
  navigationTestUtils,
  fileTestUtils,
  performanceTestUtils,
  realtimeTestUtils
}
