import React, { useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/Input'
import { Textarea } from '@/components/ui/Textarea'
import { 
  CheckCircle, 
  XCircle, 
  AlertTriangle, 
  Search, 
  Filter,
  Plus,
  Eye,
  Edit,
  Trash2,
  FileText,
  Camera,
  Users,
  Calendar,
  Flag,
  Target,
  BarChart3,
  TrendingUp,
  TrendingDown
} from 'lucide-react'
import { cn } from '@/lib/utils'

interface QCItem {
  id: string
  name: string
  description: string
  type: 'inspection' | 'test' | 'review' | 'audit'
  status: 'pending' | 'in_progress' | 'passed' | 'failed' | 'needs_rework'
  priority: 'low' | 'medium' | 'high' | 'critical'
  category: 'quality' | 'safety' | 'compliance' | 'performance'
  project: {
    id: string
    name: string
  }
  component?: {
    id: string
    name: string
  }
  task?: {
    id: string
    name: string
  }
  assignedTo: {
    id: string
    name: string
    avatar?: string
  }
  createdBy: {
    id: string
    name: string
  }
  dueDate?: Date
  completedDate?: Date
  score?: number
  maxScore?: number
  findings: QCFinding[]
  attachments: QCAttachment[]
  tags: string[]
  createdAt: Date
  updatedAt: Date
}

interface QCFinding {
  id: string
  description: string
  severity: 'low' | 'medium' | 'high' | 'critical'
  status: 'open' | 'resolved' | 'verified'
  category: 'defect' | 'improvement' | 'observation'
  location?: string
  evidence?: string
  correctiveAction?: string
  assignedTo?: {
    id: string
    name: string
  }
  dueDate?: Date
  resolvedDate?: Date
}

interface QCAttachment {
  id: string
  name: string
  type: 'image' | 'document' | 'video'
  url: string
  uploadedBy: {
    id: string
    name: string
  }
  uploadedAt: Date
}

interface QCModuleProps {
  qcItems: QCItem[]
  onCreateQC?: (qcData: Omit<QCItem, 'id' | 'createdAt' | 'updatedAt'>) => void
  onUpdateQC?: (qcId: string, updates: Partial<QCItem>) => void
  onDeleteQC?: (qcId: string) => void
  onAddFinding?: (qcId: string, finding: Omit<QCFinding, 'id'>) => void
  onUpdateFinding?: (qcId: string, findingId: string, updates: Partial<QCFinding>) => void
  onUploadAttachment?: (qcId: string, files: File[]) => void
}

const QCModule: React.FC<QCModuleProps> = ({
  qcItems,
  onCreateQC,
  onUpdateQC,
  onDeleteQC,
  onAddFinding,
  onUpdateFinding,
  onUploadAttachment
}) => {
  const [searchTerm, setSearchTerm] = useState('')
  const [filterType, setFilterType] = useState<string>('all')
  const [filterStatus, setFilterStatus] = useState<string>('all')
  const [filterPriority, setFilterPriority] = useState<string>('all')
  const [filterCategory, setFilterCategory] = useState<string>('all')
  const [selectedQC, setSelectedQC] = useState<string | null>(null)
  const [showCreateForm, setShowCreateForm] = useState(false)

  const filteredQCItems = qcItems.filter(item => {
    const matchesSearch = item.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         item.description.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         item.tags.some(tag => tag.toLowerCase().includes(searchTerm.toLowerCase()))
    const matchesType = filterType === 'all' || item.type === filterType
    const matchesStatus = filterStatus === 'all' || item.status === filterStatus
    const matchesPriority = filterPriority === 'all' || item.priority === filterPriority
    const matchesCategory = filterCategory === 'all' || item.category === filterCategory
    
    return matchesSearch && matchesType && matchesStatus && matchesPriority && matchesCategory
  })

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'pending': return 'bg-gray-100 text-gray-800'
      case 'in_progress': return 'bg-blue-100 text-blue-800'
      case 'passed': return 'bg-green-100 text-green-800'
      case 'failed': return 'bg-red-100 text-red-800'
      case 'needs_rework': return 'bg-yellow-100 text-yellow-800'
      default: return 'bg-gray-100 text-gray-800'
    }
  }

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'passed': return <CheckCircle className="h-4 w-4" />
      case 'failed': return <XCircle className="h-4 w-4" />
      case 'needs_rework': return <AlertTriangle className="h-4 w-4" />
      default: return <Target className="h-4 w-4" />
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

  const getSeverityColor = (severity: string) => {
    switch (severity) {
      case 'low': return 'bg-green-100 text-green-800'
      case 'medium': return 'bg-yellow-100 text-yellow-800'
      case 'high': return 'bg-orange-100 text-orange-800'
      case 'critical': return 'bg-red-100 text-red-800'
      default: return 'bg-gray-100 text-gray-800'
    }
  }

  const formatDate = (date: Date) => {
    return new Intl.DateTimeFormat('vi-VN', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    }).format(date)
  }

  const calculateScore = (item: QCItem) => {
    if (!item.score || !item.maxScore) return null
    const percentage = (item.score / item.maxScore) * 100
    return {
      percentage: Math.round(percentage),
      color: percentage >= 80 ? 'text-green-600' : percentage >= 60 ? 'text-yellow-600' : 'text-red-600'
    }
  }

  const getQCStats = () => {
    const total = qcItems.length
    const passed = qcItems.filter(item => item.status === 'passed').length
    const failed = qcItems.filter(item => item.status === 'failed').length
    const pending = qcItems.filter(item => item.status === 'pending').length
    const inProgress = qcItems.filter(item => item.status === 'in_progress').length
    
    return { total, passed, failed, pending, inProgress }
  }

  const stats = getQCStats()

  return (
    <div className="space-y-4">
      {/* Header */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle className="flex items-center gap-2">
              <CheckCircle className="h-5 w-5" />
              Quality Control Module
            </CardTitle>
            <Button onClick={() => setShowCreateForm(true)}>
              <Plus className="h-4 w-4 mr-2" />
              New QC Item
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {/* Stats */}
          <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div className="text-center">
              <div className="text-2xl font-bold text-gray-900">{stats.total}</div>
              <div className="text-sm text-gray-500">Total Items</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-green-600">{stats.passed}</div>
              <div className="text-sm text-gray-500">Passed</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-red-600">{stats.failed}</div>
              <div className="text-sm text-gray-500">Failed</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-blue-600">{stats.inProgress}</div>
              <div className="text-sm text-gray-500">In Progress</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-gray-600">{stats.pending}</div>
              <div className="text-sm text-gray-500">Pending</div>
            </div>
          </div>

          {/* Search and Filters */}
          <div className="flex items-center gap-4">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
              <Input
                placeholder="Search QC items..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10"
              />
            </div>
            
            <select
              value={filterType}
              onChange={(e) => setFilterType(e.target.value)}
              className="px-3 py-2 border rounded text-sm"
            >
              <option value="all">All Types</option>
              <option value="inspection">Inspection</option>
              <option value="test">Test</option>
              <option value="review">Review</option>
              <option value="audit">Audit</option>
            </select>

            <select
              value={filterStatus}
              onChange={(e) => setFilterStatus(e.target.value)}
              className="px-3 py-2 border rounded text-sm"
            >
              <option value="all">All Status</option>
              <option value="pending">Pending</option>
              <option value="in_progress">In Progress</option>
              <option value="passed">Passed</option>
              <option value="failed">Failed</option>
              <option value="needs_rework">Needs Rework</option>
            </select>

            <select
              value={filterPriority}
              onChange={(e) => setFilterPriority(e.target.value)}
              className="px-3 py-2 border rounded text-sm"
            >
              <option value="all">All Priority</option>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>

            <select
              value={filterCategory}
              onChange={(e) => setFilterCategory(e.target.value)}
              className="px-3 py-2 border rounded text-sm"
            >
              <option value="all">All Categories</option>
              <option value="quality">Quality</option>
              <option value="safety">Safety</option>
              <option value="compliance">Compliance</option>
              <option value="performance">Performance</option>
            </select>
          </div>
        </CardContent>
      </Card>

      {/* QC Items */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {filteredQCItems.map((item) => {
          const score = calculateScore(item)
          
          return (
            <Card key={item.id} className="hover:shadow-lg transition-shadow">
              <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <CardTitle className="text-lg">{item.name}</CardTitle>
                    <p className="text-sm text-gray-600 mt-1">{item.description}</p>
                  </div>
                  <Badge className={cn('text-xs', getStatusColor(item.status))}>
                    {getStatusIcon(item.status)}
                    <span className="ml-1">{item.status.replace('_', ' ')}</span>
                  </Badge>
                </div>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  {/* Basic Info */}
                  <div className="flex items-center gap-4 text-sm text-gray-600">
                    <div className="flex items-center gap-1">
                      <Flag className="h-4 w-4" />
                      <span className="capitalize">{item.type}</span>
                    </div>
                    <div className="flex items-center gap-1">
                      <Users className="h-4 w-4" />
                      <span>{item.assignedTo.name}</span>
                    </div>
                    {item.dueDate && (
                      <div className="flex items-center gap-1">
                        <Calendar className="h-4 w-4" />
                        <span>{formatDate(item.dueDate)}</span>
                      </div>
                    )}
                  </div>

                  {/* Priority */}
                  <div className={cn('border-l-4 pl-3 py-1', getPriorityColor(item.priority))}>
                    <span className="text-sm font-medium capitalize">{item.priority} Priority</span>
                  </div>

                  {/* Score */}
                  {score && (
                    <div className="flex items-center gap-2">
                      <BarChart3 className="h-4 w-4 text-gray-500" />
                      <span className="text-sm text-gray-600">Score:</span>
                      <span className={cn('font-medium', score.color)}>
                        {item.score}/{item.maxScore} ({score.percentage}%)
                      </span>
                    </div>
                  )}

                  {/* Findings */}
                  {item.findings.length > 0 && (
                    <div>
                      <div className="text-sm font-medium text-gray-700 mb-2">Findings:</div>
                      <div className="space-y-1">
                        {item.findings.slice(0, 3).map((finding) => (
                          <div key={finding.id} className="flex items-center gap-2 text-sm">
                            <Badge className={cn('text-xs', getSeverityColor(finding.severity))}>
                              {finding.severity}
                            </Badge>
                            <span className="text-gray-600 truncate">{finding.description}</span>
                          </div>
                        ))}
                        {item.findings.length > 3 && (
                          <div className="text-xs text-gray-500">
                            +{item.findings.length - 3} more findings
                          </div>
                        )}
                      </div>
                    </div>
                  )}

                  {/* Tags */}
                  {item.tags.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                      {item.tags.slice(0, 3).map((tag, index) => (
                        <Badge key={index} variant="outline" className="text-xs">
                          {tag}
                        </Badge>
                      ))}
                      {item.tags.length > 3 && (
                        <Badge variant="outline" className="text-xs">
                          +{item.tags.length - 3}
                        </Badge>
                      )}
                    </div>
                  )}

                  {/* Actions */}
                  <div className="flex items-center gap-2 pt-2 border-t">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => setSelectedQC(item.id)}
                      className="flex-1"
                    >
                      <Eye className="h-3 w-3 mr-1" />
                      View Details
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => {/* Edit QC */}}
                    >
                      <Edit className="h-3 w-3" />
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => onDeleteQC?.(item.id)}
                      className="text-red-600 hover:text-red-700"
                    >
                      <Trash2 className="h-3 w-3" />
                    </Button>
                  </div>
                </div>
              </CardContent>
            </Card>
          )
        })}
      </div>

      {/* Create QC Form Modal */}
      {showCreateForm && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <Card className="w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <CardHeader>
              <CardTitle>Create New QC Item</CardTitle>
            </CardHeader>
            <CardContent>
              <form className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium mb-1">Name</label>
                    <Input placeholder="QC Item Name" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1">Type</label>
                    <select className="w-full px-3 py-2 border rounded">
                      <option value="inspection">Inspection</option>
                      <option value="test">Test</option>
                      <option value="review">Review</option>
                      <option value="audit">Audit</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium mb-1">Description</label>
                  <Textarea placeholder="QC Item Description" rows={3} />
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium mb-1">Priority</label>
                    <select className="w-full px-3 py-2 border rounded">
                      <option value="low">Low</option>
                      <option value="medium">Medium</option>
                      <option value="high">High</option>
                      <option value="critical">Critical</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1">Category</label>
                    <select className="w-full px-3 py-2 border rounded">
                      <option value="quality">Quality</option>
                      <option value="safety">Safety</option>
                      <option value="compliance">Compliance</option>
                      <option value="performance">Performance</option>
                    </select>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium mb-1">Assigned To</label>
                    <select className="w-full px-3 py-2 border rounded">
                      <option value="">Select User</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1">Due Date</label>
                    <Input type="datetime-local" />
                  </div>
                </div>

                <div className="flex justify-end gap-2">
                  <Button variant="outline" onClick={() => setShowCreateForm(false)}>
                    Cancel
                  </Button>
                  <Button onClick={() => {/* Create QC */}}>
                    Create QC Item
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      )}

      {filteredQCItems.length === 0 && (
        <Card>
          <CardContent className="text-center py-12">
            <CheckCircle className="h-12 w-12 mx-auto mb-4 text-gray-400" />
            <h3 className="text-lg font-medium mb-2">No QC items found</h3>
            <p className="text-gray-500 mb-4">
              {searchTerm || filterType !== 'all' || filterStatus !== 'all'
                ? 'Try adjusting your search or filters'
                : 'Create your first QC item to get started'
              }
            </p>
            <Button onClick={() => setShowCreateForm(true)}>
              <Plus className="h-4 w-4 mr-2" />
              Create QC Item
            </Button>
          </CardContent>
        </Card>
      )}
    </div>
  )
}

export default QCModule
