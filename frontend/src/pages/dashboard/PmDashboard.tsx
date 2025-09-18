import { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { 
  TrendingUp, 
  AlertTriangle, 
  CheckCircle, 
  Clock, 
  Users, 
  FileText,
  Calendar,
  BarChart3,
  Plus,
  Eye,
  Download
} from 'lucide-react'

interface ProjectOverview {
  total_projects: number
  active_projects: number
  completed_projects: number
  overdue_projects: number
  total_budget: number
  spent_budget: number
  average_progress: number
}

interface ProjectProgress {
  project_info: {
    id: string
    name: string
    status: string
    progress_percentage: number
    budget: number
    actual_cost: number
  }
  tasks: {
    total_tasks: number
    completed_tasks: number
    in_progress_tasks: number
    pending_tasks: number
    overdue_tasks: number
  }
  rfis: {
    total_rfis: number
    open_rfis: number
    answered_rfis: number
    recent_rfis: number
  }
  submittals: {
    total_submittals: number
    pending_submittals: number
    approved_submittals: number
    rejected_submittals: number
  }
  change_requests: {
    total_change_requests: number
    pending_crs: number
    approved_crs: number
    rejected_crs: number
    total_cr_cost: number
  }
  qc_inspections: {
    total_inspections: number
    passed_inspections: number
    failed_inspections: number
    pending_inspections: number
  }
  ncrs: {
    total_ncrs: number
    open_ncrs: number
    closed_ncrs: number
  }
}

interface RiskAssessment {
  total_risks: number
  high_risks: number
  medium_risks: number
  low_risks: number
  risks: Array<{
    type: string
    severity: string
    title: string
    description: string
    impact: string
  }>
  risk_score: number
  recommendations: string[]
}

export function PmDashboard() {
  const [overview, setOverview] = useState<ProjectOverview | null>(null)
  const [projectProgress, setProjectProgress] = useState<ProjectProgress | null>(null)
  const [riskAssessment, setRiskAssessment] = useState<RiskAssessment | null>(null)
  const [loading, setLoading] = useState(true)
  const [selectedProject, setSelectedProject] = useState<string>('')

  useEffect(() => {
    loadDashboardData()
  }, [selectedProject])

  const loadDashboardData = async () => {
    try {
      setLoading(true)
      
      // Load overview data
      const overviewResponse = await fetch('/api/pm/dashboard', {
        headers: {
          'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
          'Content-Type': 'application/json'
        }
      })
      
      if (overviewResponse.ok) {
        const overviewData = await overviewResponse.json()
        setOverview(overviewData.data)
      }

      // Load project progress if project selected
      if (selectedProject) {
        const progressResponse = await fetch(`/api/pm/progress?project_id=${selectedProject}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json'
          }
        })
        
        if (progressResponse.ok) {
          const progressData = await progressResponse.json()
          setProjectProgress(progressData.data)
        }

        // Load risk assessment
        const riskResponse = await fetch(`/api/pm/risks?project_id=${selectedProject}`, {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('auth_token')}`,
            'Content-Type': 'application/json'
          }
        })
        
        if (riskResponse.ok) {
          const riskData = await riskResponse.json()
          setRiskAssessment(riskData.data)
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
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Project Manager Dashboard</h1>
          <p className="text-gray-600 mt-1">Monitor and manage your projects effectively</p>
        </div>
        <div className="flex space-x-3">
          <button className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 flex items-center">
            <Plus className="w-4 h-4 mr-2" />
            New Task
          </button>
          <button className="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center">
            <Download className="w-4 h-4 mr-2" />
            Export Report
          </button>
        </div>
      </div>

      {/* Overview Cards */}
      {overview && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Total Projects</p>
                <p className="text-3xl font-bold text-gray-900">{overview.total_projects}</p>
              </div>
              <div className="p-3 bg-blue-100 rounded-full">
                <TrendingUp className="w-6 h-6 text-blue-600" />
              </div>
            </div>
            <div className="mt-4 flex items-center text-sm">
              <span className="text-green-600 font-medium">{overview.active_projects} active</span>
              <span className="text-gray-300 mx-2">•</span>
              <span className="text-gray-600">{overview.completed_projects} completed</span>
            </div>
          </div>

          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Average Progress</p>
                <p className="text-3xl font-bold text-gray-900">{overview.average_progress.toFixed(1)}%</p>
              </div>
              <div className="p-3 bg-green-100 rounded-full">
                <BarChart3 className="w-6 h-6 text-green-600" />
              </div>
            </div>
            <div className="mt-4">
              <div className="w-full bg-gray-200 rounded-full h-2">
                <div 
                  className="bg-green-600 h-2 rounded-full" 
                  style={{ width: `${overview.average_progress}%` }}
                ></div>
              </div>
            </div>
          </div>

          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Budget Status</p>
                <p className="text-3xl font-bold text-gray-900">
                  ${(overview.spent_budget / 1000000).toFixed(1)}M
                </p>
              </div>
              <div className="p-3 bg-yellow-100 rounded-full">
                <Calendar className="w-6 h-6 text-yellow-600" />
              </div>
            </div>
            <div className="mt-4 text-sm text-gray-600">
              of ${(overview.total_budget / 1000000).toFixed(1)}M total
            </div>
          </div>

          <div className="bg-white p-6 rounded-lg shadow-sm border">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm font-medium text-gray-600">Overdue Projects</p>
                <p className="text-3xl font-bold text-red-600">{overview.overdue_projects}</p>
              </div>
              <div className="p-3 bg-red-100 rounded-full">
                <AlertTriangle className="w-6 h-6 text-red-600" />
              </div>
            </div>
            <div className="mt-4">
              <Link to="/projects?filter=overdue" className="text-sm text-red-600 hover:text-red-700">
                View overdue projects →
              </Link>
            </div>
          </div>
        </div>
      )}

      {/* Project Progress */}
      {projectProgress && (
        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <div className="flex items-center justify-between">
              <h2 className="text-xl font-semibold text-gray-900">Project Progress</h2>
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
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Tasks */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium text-gray-900">Tasks</h3>
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Total Tasks</span>
                    <span className="font-medium">{projectProgress.tasks.total_tasks}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Completed</span>
                    <span className="font-medium text-green-600">{projectProgress.tasks.completed_tasks}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">In Progress</span>
                    <span className="font-medium text-blue-600">{projectProgress.tasks.in_progress_tasks}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Overdue</span>
                    <span className="font-medium text-red-600">{projectProgress.tasks.overdue_tasks}</span>
                  </div>
                </div>
              </div>

              {/* RFIs */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium text-gray-900">RFIs</h3>
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Total RFIs</span>
                    <span className="font-medium">{projectProgress.rfis.total_rfis}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Open</span>
                    <span className="font-medium text-orange-600">{projectProgress.rfis.open_rfis}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Answered</span>
                    <span className="font-medium text-green-600">{projectProgress.rfis.answered_rfis}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Recent (7d)</span>
                    <span className="font-medium text-blue-600">{projectProgress.rfis.recent_rfis}</span>
                  </div>
                </div>
              </div>

              {/* Quality Control */}
              <div className="space-y-4">
                <h3 className="text-lg font-medium text-gray-900">Quality Control</h3>
                <div className="space-y-3">
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Total Inspections</span>
                    <span className="font-medium">{projectProgress.qc_inspections.total_inspections}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Passed</span>
                    <span className="font-medium text-green-600">{projectProgress.qc_inspections.passed_inspections}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Failed</span>
                    <span className="font-medium text-red-600">{projectProgress.qc_inspections.failed_inspections}</span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-sm text-gray-600">Open NCRs</span>
                    <span className="font-medium text-orange-600">{projectProgress.ncrs.open_ncrs}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Risk Assessment */}
      {riskAssessment && (
        <div className="bg-white rounded-lg shadow-sm border">
          <div className="p-6 border-b">
            <h2 className="text-xl font-semibold text-gray-900">Risk Assessment</h2>
          </div>
          
          <div className="p-6">
            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6 mb-6">
              <div className="text-center">
                <div className="text-3xl font-bold text-gray-900">{riskAssessment.total_risks}</div>
                <div className="text-sm text-gray-600">Total Risks</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-red-600">{riskAssessment.high_risks}</div>
                <div className="text-sm text-gray-600">High Risk</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-yellow-600">{riskAssessment.medium_risks}</div>
                <div className="text-sm text-gray-600">Medium Risk</div>
              </div>
              <div className="text-center">
                <div className="text-3xl font-bold text-green-600">{riskAssessment.low_risks}</div>
                <div className="text-sm text-gray-600">Low Risk</div>
              </div>
            </div>

            {riskAssessment.risks.length > 0 && (
              <div className="space-y-4">
                <h3 className="text-lg font-medium text-gray-900">Active Risks</h3>
                {riskAssessment.risks.map((risk, index) => (
                  <div key={index} className="border border-gray-200 rounded-lg p-4">
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <div className="flex items-center space-x-2 mb-2">
                          <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                            risk.severity === 'high' ? 'bg-red-100 text-red-800' :
                            risk.severity === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                            'bg-green-100 text-green-800'
                          }`}>
                            {risk.severity.toUpperCase()}
                          </span>
                          <span className="text-sm font-medium text-gray-900">{risk.title}</span>
                        </div>
                        <p className="text-sm text-gray-600 mb-2">{risk.description}</p>
                        <p className="text-xs text-gray-500">{risk.impact}</p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}

            {riskAssessment.recommendations.length > 0 && (
              <div className="mt-6">
                <h3 className="text-lg font-medium text-gray-900 mb-4">Recommendations</h3>
                <ul className="space-y-2">
                  {riskAssessment.recommendations.map((recommendation, index) => (
                    <li key={index} className="flex items-start space-x-2">
                      <CheckCircle className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                      <span className="text-sm text-gray-700">{recommendation}</span>
                    </li>
                  ))}
                </ul>
              </div>
            )}
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
              to="/tasks/create" 
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <Plus className="w-5 h-5 text-blue-600 mr-3" />
              <div>
                <div className="font-medium text-gray-900">Create Task</div>
                <div className="text-sm text-gray-600">Add new task</div>
              </div>
            </Link>

            <Link 
              to="/rfi/create" 
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <FileText className="w-5 h-5 text-green-600 mr-3" />
              <div>
                <div className="font-medium text-gray-900">Create RFI</div>
                <div className="text-sm text-gray-600">Request for information</div>
              </div>
            </Link>

            <Link 
              to="/reports/weekly" 
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <Eye className="w-5 h-5 text-purple-600 mr-3" />
              <div>
                <div className="font-medium text-gray-900">Weekly Report</div>
                <div className="text-sm text-gray-600">Generate report</div>
              </div>
            </Link>

            <Link 
              to="/projects" 
              className="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <Users className="w-5 h-5 text-orange-600 mr-3" />
              <div>
                <div className="font-medium text-gray-900">Manage Projects</div>
                <div className="text-sm text-gray-600">View all projects</div>
              </div>
            </Link>
          </div>
        </div>
      </div>
    </div>
  )
}
