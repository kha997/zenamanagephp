import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { 
  HardHat, 
  AlertTriangle, 
  CheckCircle, 
  Clock, 
  Package, 
  FileText,
  Shield,
  BarChart3,
  Plus,
  Eye,
  Download,
  Calendar
} from 'lucide-react'

interface SiteEngineerOverview {
  total_projects: number
  active_projects: number
  site_tasks_assigned: number
  site_tasks_completed: number
  material_requests_created: number
  rfis_created: number
  inspections_conducted: number
}

interface SiteTasks {
  total_tasks: number
  pending_tasks: number
  in_progress_tasks: number
  completed_tasks: number
  overdue_tasks: number
  high_priority_tasks: number
  completion_rate: number
}

interface MaterialRequests {
  total_requests: number
  pending_requests: number
  approved_requests: number
  rejected_requests: number
  fulfilled_requests: number
  total_value: number
  approval_rate: number
}

interface SiteSafetyStatus {
  safety_incidents: {
    total: number
    minor: number
    major: number
    critical: number
  }
  safety_inspections: {
    total: number
    passed: number
    failed: number
  }
  safety_training: {
    completed: number
    pending: number
    overdue: number
  }
  ppe_compliance: {
    compliant: number
    non_compliant: number
    not_checked: number
  }
  safety_score: number
  recommendations: string[]
}

interface DailySiteReport {
  project_name: string
  report_date: string
  site_engineer: string
  activities: {
    tasks_completed: number
    tasks_started: number
    rfis_created: number
    material_requests: number
    inspections_conducted: number
  }
  weather_conditions: {
    temperature: string
    humidity: string
    conditions: string
    wind_speed: string
  }
  site_conditions: {
    accessibility: string
    safety_compliance: string
    equipment_status: string
    material_availability: string
  }
  summary: {
    total_activities: number
    productivity_score: number
    issues_identified: number
    overall_status: string
  }
}

export function SiteEngineerDashboard() {
  const [overview, setOverview] = useState<SiteEngineerOverview | null>(null)
  const [siteTasks, setSiteTasks] = useState<SiteTasks | null>(null)
  const [materialRequests, setMaterialRequests] = useState<MaterialRequests | null>(null)
  const [safetyStatus, setSafetyStatus] = useState<SiteSafetyStatus | null>(null)
  const [dailyReport, setDailyReport] = useState<DailySiteReport | null>(null)
  const [loading, setLoading] = useState(true)
  const [selectedProject, setSelectedProject] = useState<string>('')

  useEffect(() => {
    loadDashboardData()
  }, [selectedProject])

  const loadDashboardData = async () => {
    try {
      setLoading(true)
      
      // Load overview data
      const overviewResponse = await fetch('/api/site-engineer/dashboard', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        }
      })
      
      if (overviewResponse.ok) {
        const overviewData = await overviewResponse.json()
        setOverview(overviewData.data)
      }

      // Load site tasks
      const tasksResponse = await fetch('/api/site-engineer/tasks', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        }
      })
      
      if (tasksResponse.ok) {
        const tasksData = await tasksResponse.json()
        setSiteTasks(tasksData.data.statistics)
      }

      // Load material requests
      const materialResponse = await fetch('/api/site-engineer/material-requests', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        }
      })
      
      if (materialResponse.ok) {
        const materialData = await materialResponse.json()
        setMaterialRequests(materialData.data.statistics)
      }

      // Load safety status if project selected
      if (selectedProject) {
        const safetyResponse = await fetch(`/api/site-engineer/safety?project_id=${selectedProject}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json'
          }
        })
        
        if (safetyResponse.ok) {
          const safetyData = await safetyResponse.json()
          setSafetyStatus(safetyData.data)
        }

        // Load daily report
        const reportResponse = await fetch(`/api/site-engineer/daily-report?project_id=${selectedProject}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json'
          }
        })
        
        if (reportResponse.ok) {
          const reportData = await reportResponse.json()
          setDailyReport(reportData.data)
        }
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
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-yellow-600"></div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Site Engineer Dashboard</h1>
          <p className="text-gray-600 mt-1">Monitor site activities and safety</p>
        </div>
        <div className="flex space-x-3">
          <button className="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 flex items-center">
            <Plus className="w-4 h-4 mr-2" />
            New Material Request
          </button>
          <button className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
            <FileText className="w-4 h-4 mr-2" />
            Create RFI
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
              <div className="p-3 bg-yellow-100 rounded-full">
                <HardHat className="w-6 h-6 text-yellow-600" />
              </div>
            </div>
            <div className="mt-4 flex items-center text-sm">
              <span className="text-gray-600">{overview.total_projects} total projects</span>
            </div>
          </div>

          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Site Tasks</p>
                <p className="text-3xl font-bold text-gray-900">{overview.site_tasks_assigned}</p>
              </div>
              <div className="p-3 bg-blue-100 rounded-full">
                <CheckCircle className="w-6 h-6 text-blue-600" />
              </div>
            </div>
            <div className="mt-4 flex items-center text-sm">
              <span className="text-green-600 font-medium">{overview.site_tasks_completed} completed</span>
            </div>
          </div>

          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Material Requests</p>
                <p className="text-3xl font-bold text-gray-900">{overview.material_requests_created}</p>
              </div>
              <div className="p-3 bg-green-100 rounded-full">
                <Package className="w-6 h-6 text-green-600" />
              </div>
            </div>
            <div className="mt-4">
              <Link to="/material-requests" className="text-sm text-green-600 hover:text-green-700">
                View requests →
              </Link>
            </div>
          </div>

          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Inspections</p>
                <p className="text-3xl font-bold text-gray-900">{overview.inspections_conducted}</p>
              </div>
              <div className="p-3 bg-purple-100 rounded-full">
                <Shield className="w-6 h-6 text-purple-600" />
              </div>
            </div>
            <div className="mt-4">
              <Link to="/inspections" className="text-sm text-purple-600 hover:text-purple-700">
                View inspections →
              </Link>
            </div>
          </div>
        </div>
      )}

      {/* Site Tasks */}
      {siteTasks && (
        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <div className="flex items-center justify-between">
              <h2 className="text-xl font-semibold text-gray-900">Site Tasks</h2>
              <Link 
                to="/tasks?filter=site" 
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
                    <span className="font-medium">{siteTasks.total_tasks}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Completed</span>
                    <span className="font-medium text-green-600">{siteTasks.completed_tasks}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">In Progress</span>
                    <span className="font-medium text-blue-600">{siteTasks.in_progress_tasks}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Overdue</span>
                    <span className="font-medium text-red-600">{siteTasks.overdue_tasks}</span>
                  </div>
                </div>
              </div>

              {/* Progress */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium text-gray-900">Progress</h3>
                <div className="text-center">
                  <div className="text-4xl font-bold text-green-600 mb-2">
                    {siteTasks.completion_rate.toFixed(1)}%
                  </div>
                  <div className="text-sm text-gray-600">Completion Rate</div>
                </div>
                <div className="w-full bg-gray-200 rounded-full h-3">
                  <div 
                    className="bg-green-600 h-3 rounded-full transition-all duration-300" 
                    style={{ width: `${siteTasks.completion_rate}%` }}
                  ></div>
                </div>
              </div>

              {/* Priority Tasks */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium text-gray-900">Priority Tasks</h3>
                <div className="text-center">
                  <div className="text-3xl font-bold text-red-600 mb-2">
                    {siteTasks.high_priority_tasks}
                  </div>
                  <div className="text-sm text-gray-600">High Priority</div>
                </div>
                <div className="mt-4">
                  <Link 
                    to="/tasks?priority=high&filter=site" 
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

      {/* Material Requests */}
      {materialRequests && (
        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <div className="flex items-center justify-between">
              <h2 className="text-xl font-semibold text-gray-900">Material Requests</h2>
              <Link 
                to="/material-requests" 
                className="text-green-600 hover:text-green-700 text-sm font-medium"
              >
                View all requests →
              </Link>
            </div>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
              <div className="text-center">
                <div className="text-3xl font-bold text-gray-900">{materialRequests.total_requests}</div>
                <div className="text-sm text-gray-600">Total Requests</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-yellow-600">{materialRequests.pending_requests}</div>
                <div className="text-sm text-gray-600">Pending</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-green-600">{materialRequests.approved_requests}</div>
                <div className="text-sm text-gray-600">Approved</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-blue-600">${(materialRequests.total_value / 1000).toFixed(0)}K</div>
                <div className="text-sm text-gray-600">Total Value</div>
              </div>
            </div>

            <div className="mt-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-medium text-gray-900">Request Status</h3>
                <span className="text-sm text-gray-600">
                  Approval Rate: {materialRequests.approval_rate.toFixed(1)}%
                </span>
              </div>
              
              <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div className="bg-yellow-50 p-4 rounded-lg">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-yellow-800">Pending</span>
                    <span className="text-lg font-bold text-yellow-600">{materialRequests.pending_requests}</span>
                  </div>
                </div>
                <div className="bg-green-50 p-4 rounded-lg">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-green-800">Approved</span>
                    <span className="text-lg font-bold text-green-600">{materialRequests.approved_requests}</span>
                  </div>
                </div>
                <div className="bg-red-50 p-4 rounded-lg">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-red-800">Rejected</span>
                    <span className="text-lg font-bold text-red-600">{materialRequests.rejected_requests}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Safety Status */}
      {safetyStatus && (
        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <div className="flex items-center justify-between">
              <h2 className="text-xl font-semibold text-gray-900">Safety Status</h2>
              <div className="flex items-center space-x-2">
                <span className="text-sm text-gray-600">Safety Score:</span>
                <span className={`px-2 py-1 rounded-full text-sm font-medium ${
                  safetyStatus.safety_score >= 8 ? 'bg-green-100 text-green-800' :
                  safetyStatus.safety_score >= 6 ? 'bg-yellow-100 text-yellow-800' :
                  'bg-red-100 text-red-800'
                }`}>
                  {safetyStatus.safety_score}/10
                </span>
              </div>
            </div>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
              <div className="text-center">
                <div className="text-3xl font-bold text-gray-900">{safetyStatus.safety_inspections.total}</div>
                <div className="text-sm text-gray-600">Total Inspections</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-green-600">{safetyStatus.safety_inspections.passed}</div>
                <div className="text-sm text-gray-600">Passed</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-red-600">{safetyStatus.safety_inspections.failed}</div>
                <div className="text-sm text-gray-600">Failed</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-blue-600">{safetyStatus.safety_training.completed}</div>
                <div className="text-sm text-gray-600">Training Completed</div>
              </div>
            </div>

            {safetyStatus.recommendations.length > 0 && (
              <div className="mt-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Safety Recommendations</h3>
                <ul className="space-y-2">
                  {safetyStatus.recommendations.map((recommendation, index) => (
                    <li key={index} className="flex items-start space-x-2">
                      <Shield className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                      <span className="text-sm text-gray-700">{recommendation}</span>
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Daily Site Report */}
      {dailyReport && (
        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <div className="flex items-center justify-between">
              <h2 className="text-xl font-semibold text-gray-900">Daily Site Report</h2>
              <div className="flex items-center space-x-2">
                <Calendar className="w-5 h-5 text-gray-400" />
                <span className="text-sm text-gray-600">{dailyReport.report_date}</span>
              </div>
            </div>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Activities */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium text-gray-900">Daily Activities</h3>
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Tasks Completed</span>
                    <span className="font-medium text-green-600">{dailyReport.activities.tasks_completed}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Tasks Started</span>
                    <span className="font-medium text-blue-600">{dailyReport.activities.tasks_started}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">RFIs Created</span>
                    <span className="font-medium text-orange-600">{dailyReport.activities.rfis_created}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Material Requests</span>
                    <span className="font-medium text-purple-600">{dailyReport.activities.material_requests}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Inspections</span>
                    <span className="font-medium text-indigo-600">{dailyReport.activities.inspections_conducted}</span>
                  </div>
                </div>
              </div>

              {/* Site Conditions */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium text-gray-900">Site Conditions</h3>
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Accessibility</span>
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                      dailyReport.site_conditions.accessibility === 'Good' ? 'bg-green-100 text-green-800' :
                      dailyReport.site_conditions.accessibility === 'Fair' ? 'bg-yellow-100 text-yellow-800' :
                      'bg-red-100 text-red-800'
                    }`}>
                      {dailyReport.site_conditions.accessibility}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Safety Compliance</span>
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                      dailyReport.site_conditions.safety_compliance === 'Compliant' ? 'bg-green-100 text-green-800' :
                      'bg-red-100 text-red-800'
                    }`}>
                      {dailyReport.site_conditions.safety_compliance}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Equipment Status</span>
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                      dailyReport.site_conditions.equipment_status === 'Operational' ? 'bg-green-100 text-green-800' :
                      'bg-yellow-100 text-yellow-800'
                    }`}>
                      {dailyReport.site_conditions.equipment_status}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Material Availability</span>
                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                      dailyReport.site_conditions.material_availability === 'Available' ? 'bg-green-100 text-green-800' :
                      'bg-red-100 text-red-800'
                    }`}>
                      {dailyReport.site_conditions.material_availability}
                    </span>
                  </div>
                </div>
              </div>

              {/* Summary */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium text-gray-900">Summary</h3>
                <div className="space-y-3">
                  <div className="text-center">
                    <div className="text-3xl font-bold text-blue-600 mb-1">
                      {dailyReport.summary.productivity_score}/10
                    </div>
                    <div className="text-sm text-gray-600">Productivity Score</div>
                  </div>
                  <div className="text-center">
                    <div className="text-2xl font-bold text-gray-900 mb-1">
                      {dailyReport.summary.total_activities}
                    </div>
                    <div className="text-sm text-gray-600">Total Activities</div>
                  </div>
                  <div className="text-center">
                    <div className={`text-2xl font-bold mb-1 ${
                      dailyReport.summary.overall_status === 'Excellent' ? 'text-green-600' :
                      dailyReport.summary.overall_status === 'Good' ? 'text-blue-600' :
                      dailyReport.summary.overall_status === 'Fair' ? 'text-yellow-600' :
                      'text-red-600'
                    }`}>
                      {dailyReport.summary.overall_status}
                    </div>
                    <div className="text-sm text-gray-600">Overall Status</div>
                  </div>
                </div>
              </div>
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
              to="/material-requests/create" 
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <Package className="w-5 h-5 text-green-600 mr-3" />
              <div>
                <div className="font-medium text-gray-900">Request Materials</div>
                <div className="text-sm text-gray-600">Create material request</div>
              </div>
            </Link>

            <Link 
              to="/rfi/create" 
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <FileText className="w-5 h-5 text-blue-600 mr-3" />
              <div>
                <div className="font-medium text-gray-900">Create RFI</div>
                <div className="text-sm text-gray-600">Request for information</div>
              </div>
            </Link>

            <Link 
              to="/inspections/create" 
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <Shield className="w-5 h-5 text-purple-600 mr-3" />
              <div>
                <div className="font-medium text-gray-900">Conduct Inspection</div>
                <div className="text-sm text-gray-600">Quality control check</div>
              </div>
            </Link>

            <Link 
              to="/tasks?filter=site" 
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <CheckCircle className="w-5 h-5 text-orange-600 mr-3" />
              <div>
                <div className="font-medium text-gray-900">View Site Tasks</div>
                <div className="text-sm text-gray-600">Manage site activities</div>
              </div>
            </Link>
          </div>
        </div>
      </div>
    </div>
  )
}
