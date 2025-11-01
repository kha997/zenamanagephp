import React, { useState, useEffect, useMemo } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Calendar, Filter, Download, Upload, Settings } from 'lucide-react'
import { cn } from '@/lib/utils'

interface GanttTask {
  id: string
  name: string
  start: Date
  end: Date
  progress: number
  dependencies?: string[]
  type: 'task' | 'milestone'
  status: 'not_started' | 'in_progress' | 'completed' | 'on_hold'
  priority: 'low' | 'medium' | 'high' | 'critical'
  assignee?: {
    id: string
    name: string
    avatar?: string
  }
  project: {
    id: string
    name: string
    color: string
  }
}

interface GanttChartProps {
  tasks: GanttTask[]
  onTaskUpdate?: (taskId: string, updates: Partial<GanttTask>) => void
  onTaskCreate?: (task: Omit<GanttTask, 'id'>) => void
  onTaskDelete?: (taskId: string) => void
  viewMode?: 'day' | 'week' | 'month' | 'quarter'
  showDependencies?: boolean
  showCriticalPath?: boolean
}

const GanttChart: React.FC<GanttChartProps> = ({
  tasks,
  onTaskUpdate,
  onTaskCreate,
  onTaskDelete,
  viewMode = 'month',
  showDependencies = true,
  showCriticalPath = true
}) => {
  const [selectedTasks, setSelectedTasks] = useState<string[]>([])
  const [filterStatus, setFilterStatus] = useState<string>('all')
  const [filterPriority, setFilterPriority] = useState<string>('all')
  const [filterProject, setFilterProject] = useState<string>('all')

  // Filter tasks based on current filters
  const filteredTasks = useMemo(() => {
    return tasks.filter(task => {
      if (filterStatus !== 'all' && task.status !== filterStatus) return false
      if (filterPriority !== 'all' && task.priority !== filterPriority) return false
      if (filterProject !== 'all' && task.project.id !== filterProject) return false
      return true
    })
  }, [tasks, filterStatus, filterPriority, filterProject])

  // Calculate timeline
  const timeline = useMemo(() => {
    if (filteredTasks.length === 0) return []
    
    const startDate = new Date(Math.min(...filteredTasks.map(t => t.start.getTime())))
    const endDate = new Date(Math.max(...filteredTasks.map(t => t.end.getTime())))
    
    const dates = []
    const current = new Date(startDate)
    
    while (current <= endDate) {
      dates.push(new Date(current))
      
      switch (viewMode) {
        case 'day':
          current.setDate(current.getDate() + 1)
          break
        case 'week':
          current.setDate(current.getDate() + 7)
          break
        case 'month':
          current.setMonth(current.getMonth() + 1)
          break
        case 'quarter':
          current.setMonth(current.getMonth() + 3)
          break
      }
    }
    
    return dates
  }, [filteredTasks, viewMode])

  // Calculate critical path
  const criticalPath = useMemo(() => {
    if (!showCriticalPath) return []
    
    // Simple critical path calculation based on dependencies
    const criticalTasks = new Set<string>()
    const visited = new Set<string>()
    
    const findCriticalPath = (taskId: string) => {
      if (visited.has(taskId)) return
      visited.add(taskId)
      
      const task = filteredTasks.find(t => t.id === taskId)
      if (!task) return
      
      // Check if this task has dependencies
      if (task.dependencies && task.dependencies.length > 0) {
        criticalTasks.add(taskId)
        task.dependencies.forEach(depId => findCriticalPath(depId))
      }
    }
    
    filteredTasks.forEach(task => findCriticalPath(task.id))
    return Array.from(criticalTasks)
  }, [filteredTasks, showCriticalPath])

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'not_started': return 'bg-gray-200'
      case 'in_progress': return 'bg-blue-500'
      case 'completed': return 'bg-green-500'
      case 'on_hold': return 'bg-yellow-500'
      default: return 'bg-gray-200'
    }
  }

  const getPriorityColor = (priority: string) => {
    switch (priority) {
      case 'low': return 'border-l-green-500'
      case 'medium': return 'border-l-yellow-500'
      case 'high': return 'border-l-orange-500'
      case 'critical': return 'border-l-red-500'
      default: return 'border-l-gray-500'
    }
  }

  const formatDate = (date: Date) => {
    switch (viewMode) {
      case 'day': return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' })
      case 'week': return `Tuần ${Math.ceil(date.getDate() / 7)}`
      case 'month': return date.toLocaleDateString('vi-VN', { month: 'short', year: 'numeric' })
      case 'quarter': return `Q${Math.ceil((date.getMonth() + 1) / 3)} ${date.getFullYear()}`
      default: return date.toLocaleDateString('vi-VN')
    }
  }

  const calculateTaskPosition = (task: GanttTask) => {
    const startTime = task.start.getTime()
    const endTime = task.end.getTime()
    const timelineStart = timeline[0]?.getTime() || startTime
    const timelineEnd = timeline[timeline.length - 1]?.getTime() || endTime
    const timelineDuration = timelineEnd - timelineStart
    
    const left = ((startTime - timelineStart) / timelineDuration) * 100
    const width = ((endTime - startTime) / timelineDuration) * 100
    
    return { left: Math.max(0, left), width: Math.min(100, width) }
  }

  return (
    <div className="space-y-4">
      {/* Header Controls */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle className="flex items-center gap-2">
              <Calendar className="h-5 w-5" />
              Gantt Chart
            </CardTitle>
            <div className="flex items-center gap-2">
              <Button variant="outline" size="sm">
                <Download className="h-4 w-4 mr-2" />
                Export
              </Button>
              <Button variant="outline" size="sm">
                <Upload className="h-4 w-4 mr-2" />
                Import
              </Button>
              <Button variant="outline" size="sm">
                <Settings className="h-4 w-4" />
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {/* View Mode Selector */}
          <div className="flex items-center gap-2 mb-4">
            <span className="text-sm font-medium">View:</span>
            {['day', 'week', 'month', 'quarter'].map(mode => (
              <Button
                key={mode}
                variant={viewMode === mode ? 'default' : 'outline'}
                size="sm"
                onClick={() => {/* Handle view mode change */}}
              >
                {mode.charAt(0).toUpperCase() + mode.slice(1)}
              </Button>
            ))}
          </div>

          {/* Filters */}
          <div className="flex items-center gap-4">
            <div className="flex items-center gap-2">
              <Filter className="h-4 w-4" />
              <span className="text-sm font-medium">Filters:</span>
            </div>
            
            <select
              value={filterStatus}
              onChange={(e) => setFilterStatus(e.target.value)}
              className="px-3 py-1 border rounded text-sm"
            >
              <option value="all">All Status</option>
              <option value="not_started">Not Started</option>
              <option value="in_progress">In Progress</option>
              <option value="completed">Completed</option>
              <option value="on_hold">On Hold</option>
            </select>

            <select
              value={filterPriority}
              onChange={(e) => setFilterPriority(e.target.value)}
              className="px-3 py-1 border rounded text-sm"
            >
              <option value="all">All Priority</option>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>

            <select
              value={filterProject}
              onChange={(e) => setFilterProject(e.target.value)}
              className="px-3 py-1 border rounded text-sm"
            >
              <option value="all">All Projects</option>
              {Array.from(new Set(tasks.map(t => t.project.id))).map(projectId => {
                const project = tasks.find(t => t.project.id === projectId)?.project
                return (
                  <option key={projectId} value={projectId}>
                    {project?.name}
                  </option>
                )
              })}
            </select>
          </div>
        </CardContent>
      </Card>

      {/* Gantt Chart */}
      <Card>
        <CardContent className="p-0">
          <div className="overflow-x-auto">
            <div className="min-w-full">
              {/* Timeline Header */}
              <div className="flex border-b">
                <div className="w-80 p-4 border-r bg-gray-50">
                  <div className="font-medium">Tasks</div>
                </div>
                <div className="flex-1 flex">
                  {timeline.map((date, index) => (
                    <div
                      key={index}
                      className="flex-1 p-2 text-center text-sm border-r bg-gray-50 min-w-20"
                    >
                      {formatDate(date)}
                    </div>
                  ))}
                </div>
              </div>

              {/* Tasks */}
              <div className="space-y-1">
                {filteredTasks.map((task) => {
                  const position = calculateTaskPosition(task)
                  const isCritical = criticalPath.includes(task.id)
                  const isSelected = selectedTasks.includes(task.id)
                  
                  return (
                    <div key={task.id} className="flex border-b min-h-12">
                      {/* Task Info */}
                      <div className="w-80 p-4 border-r flex items-center gap-3">
                        <div className="flex-1">
                          <div className="font-medium text-sm">{task.name}</div>
                          <div className="text-xs text-gray-500">
                            {task.assignee?.name || 'Unassigned'}
                          </div>
                        </div>
                        <div className="flex items-center gap-1">
                          <Badge variant="outline" className="text-xs">
                            {task.priority}
                          </Badge>
                          {isCritical && (
                            <Badge variant="destructive" className="text-xs">
                              Critical
                            </Badge>
                          )}
                        </div>
                      </div>

                      {/* Task Bar */}
                      <div className="flex-1 relative p-2">
                        <div className="relative h-8">
                          {/* Task Bar */}
                          <div
                            className={cn(
                              'absolute top-1 h-6 rounded flex items-center px-2 cursor-pointer transition-all',
                              getStatusColor(task.status),
                              getPriorityColor(task.priority),
                              isSelected && 'ring-2 ring-blue-500',
                              isCritical && 'ring-2 ring-red-500'
                            )}
                            style={{
                              left: `${position.left}%`,
                              width: `${position.width}%`,
                              minWidth: '20px'
                            }}
                            onClick={() => {
                              setSelectedTasks(prev => 
                                prev.includes(task.id) 
                                  ? prev.filter(id => id !== task.id)
                                  : [...prev, task.id]
                              )
                            }}
                          >
                            <div className="text-white text-xs font-medium truncate">
                              {task.name}
                            </div>
                            {task.progress > 0 && (
                              <div className="absolute inset-0 bg-black bg-opacity-20 rounded"
                                   style={{ width: `${task.progress}%` }} />
                            )}
                          </div>

                          {/* Dependencies */}
                          {showDependencies && task.dependencies && task.dependencies.map(depId => {
                            const depTask = filteredTasks.find(t => t.id === depId)
                            if (!depTask) return null
                            
                            const depPosition = calculateTaskPosition(depTask)
                            const taskEnd = position.left + position.width
                            const depStart = depPosition.left
                            
                            return (
                              <div
                                key={depId}
                                className="absolute top-0 h-0.5 bg-gray-400"
                                style={{
                                  left: `${taskEnd}%`,
                                  width: `${depStart - taskEnd}%`
                                }}
                              />
                            )
                          })}
                        </div>
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Legend */}
      <Card>
        <CardContent>
          <div className="flex items-center gap-6">
            <div className="flex items-center gap-2">
              <span className="text-sm font-medium">Status:</span>
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 bg-gray-200 rounded"></div>
                <span className="text-xs">Not Started</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 bg-blue-500 rounded"></div>
                <span className="text-xs">In Progress</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 bg-green-500 rounded"></div>
                <span className="text-xs">Completed</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 bg-yellow-500 rounded"></div>
                <span className="text-xs">On Hold</span>
              </div>
            </div>
            
            <div className="flex items-center gap-2">
              <span className="text-sm font-medium">Priority:</span>
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 border-l-4 border-green-500"></div>
                <span className="text-xs">Low</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 border-l-4 border-yellow-500"></div>
                <span className="text-xs">Medium</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 border-l-4 border-orange-500"></div>
                <span className="text-xs">High</span>
              </div>
              <div className="flex items-center gap-2">
                <div className="w-4 h-4 border-l-4 border-red-500"></div>
                <span className="text-xs">Critical</span>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

export default GanttChart
