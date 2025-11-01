import React from 'react'
import GanttChart from '@/components/GanttChart'

const GanttChartPage: React.FC = () => {
  // Mock data for demonstration
  const mockTasks = [
    {
      id: '1',
      name: 'Foundation Work',
      start: new Date('2024-01-01'),
      end: new Date('2024-01-15'),
      progress: 100,
      type: 'task' as const,
      status: 'completed' as const,
      priority: 'high' as const,
      assignee: {
        id: '1',
        name: 'John Doe',
        avatar: undefined
      },
      project: {
        id: '1',
        name: 'Building Project A',
        color: '#3B82F6'
      }
    },
    {
      id: '2',
      name: 'Structural Work',
      start: new Date('2024-01-16'),
      end: new Date('2024-02-15'),
      progress: 75,
      type: 'task' as const,
      status: 'in_progress' as const,
      priority: 'critical' as const,
      dependencies: ['1'],
      assignee: {
        id: '2',
        name: 'Jane Smith',
        avatar: undefined
      },
      project: {
        id: '1',
        name: 'Building Project A',
        color: '#3B82F6'
      }
    },
    {
      id: '3',
      name: 'MEP Installation',
      start: new Date('2024-02-01'),
      end: new Date('2024-03-15'),
      progress: 25,
      type: 'task' as const,
      status: 'in_progress' as const,
      priority: 'medium' as const,
      dependencies: ['2'],
      assignee: {
        id: '3',
        name: 'Mike Johnson',
        avatar: undefined
      },
      project: {
        id: '1',
        name: 'Building Project A',
        color: '#3B82F6'
      }
    },
    {
      id: '4',
      name: 'Project Milestone 1',
      start: new Date('2024-01-15'),
      end: new Date('2024-01-15'),
      progress: 100,
      type: 'milestone' as const,
      status: 'completed' as const,
      priority: 'high' as const,
      assignee: {
        id: '1',
        name: 'John Doe',
        avatar: undefined
      },
      project: {
        id: '1',
        name: 'Building Project A',
        color: '#3B82F6'
      }
    }
  ]

  const handleTaskUpdate = (taskId: string, updates: any) => {
    console.log('Task updated:', taskId, updates)
  }

  const handleTaskCreate = (task: any) => {
    console.log('Task created:', task)
  }

  const handleTaskDelete = (taskId: string) => {
    console.log('Task deleted:', taskId)
  }

  return (
    <div className="container mx-auto p-6">
      <div className="mb-6">
        <h1 className="text-3xl font-bold text-gray-900">Gantt Chart</h1>
        <p className="text-gray-600 mt-2">
          Visualize project timelines, dependencies, and progress
        </p>
      </div>
      
      <GanttChart
        tasks={mockTasks}
        onTaskUpdate={handleTaskUpdate}
        onTaskCreate={handleTaskCreate}
        onTaskDelete={handleTaskDelete}
        viewMode="month"
        showDependencies={true}
        showCriticalPath={true}
      />
    </div>
  )
}

export default GanttChartPage
