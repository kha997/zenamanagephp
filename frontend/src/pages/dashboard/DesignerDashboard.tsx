import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { 
  Upload, 
  Eye, 
  CheckCircle, 
  Clock, 
  AlertCircle, 
  FileText,
  Palette,
  BarChart3,
  Plus,
  Download,
  MessageSquare
} from 'lucide-react'

interface DesignerOverview {
  total_projects: number
  active_projects: number
  design_tasks_assigned: number
  design_tasks_completed: number
  drawings_uploaded: number
  submittals_created: number
}

interface DesignTasks {
  total_tasks: number
  pending_tasks: number
  in_progress_tasks: number
  completed_tasks: number
  overdue_tasks: number
  high_priority_tasks: number
  completion_rate: number
}

interface DrawingsStatus {
  statistics: {
    total_drawings: number
    draft_drawings: number
    pending_review_drawings: number
    approved_drawings: number
    rejected_drawings: number
    my_drawings: number
  }
  summary: {
    total_drawings: number
    my_uploaded_drawings: number
    pending_reviews: number
    approval_rate: number
  }
}

interface DesignWorkload {
  workload: Array<{
    project_id: string
    project_name: string
    status: string
    tasks: {
      total: number
      pending: number
      in_progress: number
      completed: number
    }
    drawings: {
      total: number
      draft: number
      pending_review: number
      approved: number
    }
    rfis: {
      total: number
      open: number
      answered: number
    }
    submittals: {
      total: number
      pending: number
      approved: number
    }
    workload_score: number
    workload_level: string
  }>
  overall_workload: {
    total_projects: number
    high_workload_projects: number
    medium_workload_projects: number
    low_workload_projects: number
    average_workload_score: number
  }
}

export function DesignerDashboard() {
  const [overview, setOverview] = useState<DesignerOverview | null>(null)
  const [designTasks, setDesignTasks] = useState<DesignTasks | null>(null)
  const [drawingsStatus, setDrawingsStatus] = useState<DrawingsStatus | null>(null)
  const [workload, setWorkload] = useState<DesignWorkload | null>(null)
  const [loading, setLoading] = useState(true)
  const [selectedProject, setSelectedProject] = useState<string>('')

  useEffect(() => {
    loadDashboardData()
  }, [selectedProject])

  const loadDashboardData = async () => {
    try {
      setLoading(true)
      
      // Load overview data
      const overviewResponse = await fetch('/api/designer/dashboard', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        }
      })
      
      if (overviewResponse.ok) {
        const overviewData = await overviewResponse.json()
        setOverview(overviewData.data)
      }

      // Load design tasks
      const tasksResponse = await fetch('/api/designer/tasks', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        }
      })
      
      if (tasksResponse.ok) {
        const tasksData = await tasksResponse.json()
        setDesignTasks(tasksData.data.statistics)
      }

      // Load drawings status if project selected
      if (selectedProject) {
        const drawingsResponse = await fetch(`/api/designer/drawings?project_id=${selectedProject}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json'
          }
        })
        
        if (drawingsResponse.ok) {
          const drawingsData = await drawingsResponse.json()
          setDrawingsStatus(drawingsData.data)
        }
      }

      // Load workload
      const workloadResponse = await fetch('/api/designer/workload', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        }
      })
      
      if (workloadResponse.ok) {
        const workloadData = await workloadResponse.json()
        setWorkload(workloadData.data)
      }
    } catch (error) {
      console.error('Error loading dashboard data:', error)
    } finally {
      setLoading(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-green-600"></div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Designer Dashboard</h1>
          <p className="text-gray-600 mt-1">Manage your design tasks and drawings</p>
        </div>
        <div className="flex space-x-3">
          <button className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center">
            <Upload className="w-4 h-4 mr-2" />
            Upload Drawing
          </button>
          <button className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
            <Plus className="w-4 h-4 mr-2" />
            New Submittal
          </button>
        </div>
      </div>

      {/* Overview Cards */}
      {overview && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Active Projects</p>
                <p className="text-3xl font-bold text-gray-900">{overview.active_projects}</p>
              </div>
              <div className="p-3 bg-green-100 rounded-full">
                <Palette className="w-6 h-6 text-green-600" />
              </div>
            </div>
            <div className="mt-4 flex items-center text-sm">
              <span className="text-gray-600">{overview.total_projects} total projects</span>
            </div>
          </div>

          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Design Tasks</p>
                <p className="text-3xl font-bold text-gray-900">{overview.design_tasks_assigned}</p>
              </div>
              <div className="p-3 bg-blue-100 rounded-full">
                <CheckCircle className="w-6 h-6 text-blue-600" />
              </div>
            </div>
            <div className="mt-4 flex items-center text-sm">
              <span className="text-green-600 font-medium">{overview.design_tasks_completed} completed</span>
            </div>
          </div>

          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Drawings Uploaded</p>
                <p className="text-3xl font-bold text-gray-900">{overview.drawings_uploaded}</p>
              </div>
              <div className="p-3 bg-purple-100 rounded-full">
                <FileText className="w-6 h-6 text-purple-600" />
              </div>
            </div>
            <div className="mt-4">
              <Link to="/drawings" className="text-sm text-purple-600 hover:text-purple-700">
                View all drawings →
              </Link>
            </div>
          </div>

          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Submittals Created</p>
                <p className="text-3xl font-bold text-gray-900">{overview.submittals_created}</p>
              </div>
              <div className="p-3 bg-orange-100 rounded-full">
                <Upload className="w-6 h-6 text-orange-600" />
              </div>
            </div>
            <div className="mt-4">
              <Link to="/submittals" className="text-sm text-orange-600 hover:text-orange-700">
                View submittals →
              </Link>
            </div>
          </div>
        </div>
      )}

      {/* Design Tasks */}
      {designTasks && (
        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <div className="flex items-center justify-between">
              <h2 className="text-xl font-semibold text-gray-900">Design Tasks</h2>
              <Link 
                to="/tasks?filter=design" 
                className="text-blue-600 hover:text-blue-700 text-sm font-medium"
              >
                View all tasks →
              </Link>
            </div>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Task Statistics */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium text-gray-900">Task Statistics</h3>
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Total Tasks</span>
                    <span className="font-medium">{designTasks.total_tasks}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Completed</span>
                    <span className="font-medium text-green-600">{designTasks.completed_tasks}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">In Progress</span>
                    <span className="font-medium text-blue-600">{designTasks.in_progress_tasks}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Pending</span>
                    <span className="font-medium text-yellow-600">{designTasks.pending_tasks}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Overdue</span>
                    <span className="font-medium text-red-600">{designTasks.overdue_tasks}</span>
                  </div>
                </div>
              </div>

              {/* Progress */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium text-gray-900">Progress</h3>
                <div className="text-center">
                  <div className="text-4xl font-bold text-green-600 mb-2">
                    {designTasks.completion_rate.toFixed(1)}%
                  </div>
                  <div className="text-sm text-gray-600">Completion Rate</div>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-3">
                  <div 
                    className="bg-green-600 h-3 rounded-full transition-all duration-300" 
                    style={{ width: `${designTasks.completion_rate}%` }}
                  ></div>
                </div>
              </div>

              {/* Priority Tasks */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium text-gray-900">Priority Tasks</h3>
                <div className="text-center">
                  <div className="text-3xl font-bold text-red-600 mb-2">
                    {designTasks.high_priority_tasks}
                  </div>
                  <div className="text-sm text-gray-600">High Priority</div>
                </div>
                <div className="mt-4">
                  <Link 
                    to="/tasks?priority=high&filter=design" 
                    className="block w-full bg-red-50 text-red-700 px-4 py-2 rounded-lg text-center hover:bg-red-100 transition-colors"
                  >
                    View High Priority Tasks
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Drawings Status */}
      {drawingsStatus && (
        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <div className="flex items-center justify-between">
              <h2 className="text-xl font-semibold text-gray-900">Drawings Status</h2>
              <div className="flex items-center space-x-2">
                <span className="text-sm text-gray-600">Project:</span>
                <select 
                  value={selectedProject}
                  onChange={(e) => setSelectedProject(e.target.value)}
                  className="border border-gray-300 rounded-md px-3 py-1 text-sm"
                >
                  <option value="">Select Project</option>
                  {/* Add project options here */}
                </select>
              </div>
            </div>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
              <div className="text-center">
                <div className="text-3xl font-bold text-gray-900">{drawingsStatus.summary.total_drawings}</div>
                <div className="text-sm text-gray-600">Total Drawings</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-blue-600">{drawingsStatus.summary.my_uploaded_drawings}</div>
                <div className="text-sm text-gray-600">My Uploads</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-orange-600">{drawingsStatus.summary.pending_reviews}</div>
                <div className="text-sm text-gray-600">Pending Review</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-green-600">{drawingsStatus.summary.approval_rate.toFixed(1)}%</div>
                <div className="text-sm text-gray-600">Approval Rate</div>
              </div>
            </div>

            <div className="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div>
                <h3 className="text-lg font-medium text-gray-900 mb-4">Drawing Status Breakdown</h3>
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Draft</span>
                    <span className="font-medium">{drawingsStatus.statistics.draft_drawings}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Pending Review</span>
                    <span className="font-medium text-orange-600">{drawingsStatus.statistics.pending_review_drawings}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Approved</span>
                    <span className="font-medium text-green-600">{drawingsStatus.statistics.approved_drawings}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Rejected</span>
                    <span className="font-medium text-red-600">{drawingsStatus.statistics.rejected_drawings}</span>
                  </div>
                </div>
              </div>

              <div>
                <h3 className="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                <div className="space-y-3">
                  <Link 
                    to="/drawings/upload" 
                    className="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                  >
                    <Upload className="w-5 h-5 text-green-600 mr-3" />
                    <div>
                      <div className="font-medium text-gray-900">Upload New Drawing</div>
                      <div className="text-sm text-gray-600">Add design files</div>
                    </div>
                  </Link>

                  <Link 
                    to="/drawings?status=pending_review" 
                    className="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
                  >
                    <Eye className="w-5 h-5 text-blue-600 mr-3" />
                    <div>
                      <div className="font-medium text-gray-900">Review Drawings</div>
                      <div className="text-sm text-gray-600">Check pending reviews</div>
                    </div>
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Workload Overview */}
      {workload && (
        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <h2 className="text-xl font-semibold text-gray-900">Workload Overview</h2>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
              <div className="text-center">
                <div className="text-3xl font-bold text-gray-900">{workload.overall_workload.total_projects}</div>
                <div className="text-sm text-gray-600">Total Projects</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-red-600">{workload.overall_workload.high_workload_projects}</div>
                <div className="text-sm text-gray-600">High Workload</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-yellow-600">{workload.overall_workload.medium_workload_projects}</div>
                <div className="text-sm text-gray-600">Medium Workload</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-green-600">{workload.overall_workload.low_workload_projects}</div>
                <div className="text-sm text-gray-600">Low Workload</div>
              </div>
            </div>

            <div className="space-y-4">
              <h3 className="text-lg font-medium text-gray-900">Project Workload Details</h3>
              {workload.workload.map((project) => (
                <div key={project.project_id} className="border border-gray-200 rounded-lg p-4">
                  <div className="flex items-center justify-between mb-4">
                    <div>
                      <h4 className="font-medium text-gray-900">{project.project_name}</h4>
                      <p className="text-sm text-gray-600">Status: {project.status}</p>
                    </div>
                    <div className="flex items-center space-x-2">
                      <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                        project.workload_level === 'High' ? 'bg-red-100 text-red-800' :
                        project.workload_level === 'Medium' ? 'bg-yellow-100 text-yellow-800' :
                        'bg-green-100 text-green-800'
                      }`}>
                        {project.workload_level} Workload
                      </span>
                      <span className="text-sm font-medium text-gray-900">
                        Score: {project.workload_score}/10
                      </span>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div className="text-center">
                      <div className="text-lg font-bold text-gray-900">{project.tasks.total}</div>
                      <div className="text-xs text-gray-600">Tasks</div>
                    </div>
                    <div className="text-center">
                      <div className="text-lg font-bold text-purple-600">{project.drawings.total}</div>
                      <div className="text-xs text-gray-600">Drawings</div>
                    </div>
                    <div className="text-center">
                      <div className="text-lg font-bold text-orange-600">{project.rfis.total}</div>
                      <div className="text-xs text-gray-600">RFIs</div>
                    </div>
                    <div className="text-center">
                      <div className="text-lg font-bold text-blue-600">{project.submittals.total}</div>
                      <div className="text-xs text-gray-600">Submittals</div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Quick Actions */}
      <div className="bg-white rounded-lg shadow-sm border">
        <div className="p-6 border-b">
          <h2 className="text-xl font-semibold text-gray-900">Quick Actions</h2>
        </div>
        
        <div className="p-6">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <Link 
              to="/drawings/upload" 
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <Upload className="w-5 h-5 text-green-600 mr-3" />
              <div>
                <div className="font-medium text-gray-900">Upload Drawing</div>
                <div className="text-sm text-gray-600">Add new design files</div>
              </div>
            </Link>

            <Link 
              to="/submittals/create" 
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <FileText className="w-5 h-5 text-blue-600 mr-3" />
              <div>
                <div className="font-medium text-gray-900">Create Submittal</div>
                <div className="text-sm text-gray-600">Submit for approval</div>
              </div>
            </Link>

            <Link 
              to="/rfi/create" 
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <MessageSquare className="w-5 h-5 text-purple-600 mr-3" />
              <div>
                <div className="font-medium text-gray-900">Answer RFI</div>
                <div className="text-sm text-gray-600">Respond to questions</div>
              </div>
            </Link>

            <Link 
              to="/tasks?filter=design" 
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <CheckCircle className="w-5 h-5 text-orange-600 mr-3" />
              <div>
                <div className="font-medium text-gray-900">View Tasks</div>
                <div className="text-sm text-gray-600">Manage design tasks</div>
              </div>
            </Link>
          </div>
        </div>
      </div>
    </div>
  )
}
