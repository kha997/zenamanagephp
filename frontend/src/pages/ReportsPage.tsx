import { useState } from 'react'
import { ArrowLeft, Download, Calendar, Filter, BarChart3, PieChart, TrendingUp } from 'lucide-react'
import { Link } from 'react-router-dom'

export default function ReportsPage() {
  const [selectedReport, setSelectedReport] = useState<string>('')
  const [dateRange, setDateRange] = useState<string>('30d')

  const reportTypes = [
    {
      id: 'project-summary',
      name: 'Project Summary Report',
      description: 'Overview of all projects with status and progress',
      icon: BarChart3,
    },
    {
      id: 'task-performance',
      name: 'Task Performance Report',
      description: 'Detailed analysis of task completion and productivity',
      icon: TrendingUp,
    },
    {
      id: 'team-productivity',
      name: 'Team Productivity Report',
      description: 'Team member performance and workload distribution',
      icon: PieChart,
    },
    {
      id: 'financial-summary',
      name: 'Financial Summary Report',
      description: 'Budget tracking and cost analysis across projects',
      icon: BarChart3,
    },
  ]

  const handleGenerateReport = () => {
    if (!selectedReport) {
      alert('Please select a report type')
      return
    }
    
    // Simulate report generation
    alert(`Generating ${reportTypes.find(r => r.id === selectedReport)?.name}...`)
  }

  return (
    <div className="space-y-6 animate-fade-in">
      {/* Header */}
      <div className="flex items-center gap-4 animate-slide-up">
        <Link
          to="/dashboard"
          className="btn btn-outline hover-lift"
        >
          <ArrowLeft className="h-4 w-4 mr-2" />
          Back to Dashboard
        </Link>
        <div>
          <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Reports</h1>
          <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Generate and download comprehensive reports for your projects and team.
          </p>
        </div>
      </div>

      {/* Report Configuration */}
      <div className="card animate-slide-up" style={{ animationDelay: '100ms' }}>
        <div className="card-header">
          <h3 className="card-title">Report Configuration</h3>
          <p className="card-description">
            Select the type of report you want to generate and configure the parameters.
          </p>
        </div>
        <div className="card-content">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {/* Report Type Selection */}
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                Report Type *
              </label>
              <div className="space-y-3">
                {reportTypes.map((report) => {
                  const Icon = report.icon
                  return (
                    <div
                      key={report.id}
                      className={`p-4 border rounded-lg cursor-pointer transition-all ${
                        selectedReport === report.id
                          ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                          : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
                      }`}
                      onClick={() => setSelectedReport(report.id)}
                    >
                      <div className="flex items-start space-x-3">
                        <Icon className="h-5 w-5 text-blue-600 mt-0.5" />
                        <div>
                          <h4 className="font-medium text-gray-900 dark:text-gray-100">
                            {report.name}
                          </h4>
                          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {report.description}
                          </p>
                        </div>
                      </div>
                    </div>
                  )
                })}
              </div>
            </div>

            {/* Report Parameters */}
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Date Range
                </label>
                <select
                  value={dateRange}
                  onChange={(e) => setDateRange(e.target.value)}
                  className="input"
                >
                  <option value="7d">Last 7 days</option>
                  <option value="30d">Last 30 days</option>
                  <option value="90d">Last 90 days</option>
                  <option value="1y">Last year</option>
                  <option value="custom">Custom range</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Format
                </label>
                <select className="input">
                  <option value="pdf">PDF Document</option>
                  <option value="excel">Excel Spreadsheet</option>
                  <option value="csv">CSV File</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Include Charts
                </label>
                <div className="flex items-center space-x-4">
                  <label className="flex items-center">
                    <input type="checkbox" defaultChecked className="mr-2" />
                    <span className="text-sm text-gray-700 dark:text-gray-300">Charts & Graphs</span>
                  </label>
                  <label className="flex items-center">
                    <input type="checkbox" defaultChecked className="mr-2" />
                    <span className="text-sm text-gray-700 dark:text-gray-300">Summary Statistics</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          {/* Generate Button */}
          <div className="flex justify-end pt-6 border-t border-gray-200 dark:border-gray-700 mt-6">
            <button
              onClick={handleGenerateReport}
              className="btn btn-primary hover-lift"
            >
              <Download className="h-4 w-4 mr-2" />
              Generate Report
            </button>
          </div>
        </div>
      </div>

      {/* Recent Reports */}
      <div className="card animate-slide-up" style={{ animationDelay: '200ms' }}>
        <div className="card-header">
          <h3 className="card-title">Recent Reports</h3>
          <p className="card-description">
            Your recently generated reports are listed below.
          </p>
        </div>
        <div className="card-content">
          <div className="text-center py-8">
            <BarChart3 className="h-12 w-12 text-gray-400 mx-auto mb-4" />
            <h4 className="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
              No reports generated yet
            </h4>
            <p className="text-gray-500 dark:text-gray-400">
              Generate your first report using the configuration above.
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}
