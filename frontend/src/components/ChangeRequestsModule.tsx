import React, { useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/Input'
import { Textarea } from '@/components/ui/Textarea'
import { 
  FileText, 
  Plus, 
  Search, 
  Filter,
  Eye,
  Edit,
  Trash2,
  CheckCircle,
  XCircle,
  Clock,
  AlertTriangle,
  DollarSign,
  Calendar,
  Users,
  Flag,
  TrendingUp,
  TrendingDown,
  BarChart3
} from 'lucide-react'
import { cn } from '@/lib/utils'

interface ChangeRequest {
  id: string
  title: string
  description: string
  type: 'scope' | 'budget' | 'timeline' | 'resource' | 'quality'
  priority: 'low' | 'medium' | 'high' | 'critical'
  status: 'pending' | 'approved' | 'rejected' | 'implemented' | 'cancelled'
  project: {
    id: string
    name: string
  }
  task?: {
    id: string
    name: string
  }
  component?: {
    id: string
    name: string
  }
  requestedBy: {
    id: string
    name: string
    avatar?: string
  }
  approvedBy?: {
    id: string
    name: string
  }
  costImpact: number
  timeImpact: number // days
  impactAnalysis: {
    description: string
    affectedAreas: string[]
    risks: string[]
    benefits: string[]
  }
  riskAssessment: {
    level: 'low' | 'medium' | 'high' | 'critical'
    description: string
    mitigation: string[]
  }
  implementationPlan: {
    phases: ImplementationPhase[]
    timeline: string
    resources: string[]
  }
  approvalNotes?: string
  rejectionReason?: string
  requestedAt: Date
  approvedAt?: Date
  implementedAt?: Date
  tags: string[]
  attachments: ChangeRequestAttachment[]
  createdAt: Date
  updatedAt: Date
}

interface ImplementationPhase {
  id: string
  name: string
  description: string
  duration: number // days
  dependencies: string[]
  deliverables: string[]
}

interface ChangeRequestAttachment {
  id: string
  name: string
  type: 'document' | 'image' | 'spreadsheet'
  url: string
  uploadedBy: {
    id: string
    name: string
  }
  uploadedAt: Date
}

interface ChangeRequestsModuleProps {
  changeRequests: ChangeRequest[]
  onCreateCR?: (crData: Omit<ChangeRequest, 'id' | 'createdAt' | 'updatedAt'>) => void
  onUpdateCR?: (crId: string, updates: Partial<ChangeRequest>) => void
  onDeleteCR?: (crId: string) => void
  onApproveCR?: (crId: string, notes: string) => void
  onRejectCR?: (crId: string, reason: string) => void
  onImplementCR?: (crId: string) => void
  onUploadAttachment?: (crId: string, files: File[]) => void
}

const ChangeRequestsModule: React.FC<ChangeRequestsModuleProps> = ({
  changeRequests,
  onCreateCR,
  onUpdateCR,
  onDeleteCR,
  onApproveCR,
  onRejectCR,
  onImplementCR,
  onUploadAttachment
}) => {
  const [searchTerm, setSearchTerm] = useState('')
  const [filterType, setFilterType] = useState<string>('all')
  const [filterStatus, setFilterStatus] = useState<string>('all')
  const [filterPriority, setFilterPriority] = useState<string>('all')
  const [selectedCR, setSelectedCR] = useState<string | null>(null)
  const [showCreateForm, setShowCreateForm] = useState(false)
  const [showApprovalForm, setShowApprovalForm] = useState<string | null>(null)

  const filteredChangeRequests = changeRequests.filter(cr => {
    const matchesSearch = cr.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         cr.description.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         cr.tags.some(tag => tag.toLowerCase().includes(searchTerm.toLowerCase()))
    const matchesType = filterType === 'all' || cr.type === filterType
    const matchesStatus = filterStatus === 'all' || cr.status === filterStatus
    const matchesPriority = filterPriority === 'all' || cr.priority === filterPriority
    
    return matchesSearch && matchesType && matchesStatus && matchesPriority
  })

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'pending': return 'bg-yellow-100 text-yellow-800'
      case 'approved': return 'bg-green-100 text-green-800'
      case 'rejected': return 'bg-red-100 text-red-800'
      case 'implemented': return 'bg-blue-100 text-blue-800'
      case 'cancelled': return 'bg-gray-100 text-gray-800'
      default: return 'bg-gray-100 text-gray-800'
    }
  }

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'approved': return <CheckCircle className="h-4 w-4" />
      case 'rejected': return <XCircle className="h-4 w-4" />
      case 'implemented': return <CheckCircle className="h-4 w-4" />
      case 'pending': return <Clock className="h-4 w-4" />
      case 'cancelled': return <XCircle className="h-4 w-4" />
      default: return <FileText className="h-4 w-4" />
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

  const getRiskColor = (level: string) => {
    switch (level) {
      case 'low': return 'bg-green-100 text-green-800'
      case 'medium': return 'bg-yellow-100 text-yellow-800'
      case 'high': return 'bg-orange-100 text-orange-800'
      case 'critical': return 'bg-red-100 text-red-800'
      default: return 'bg-gray-100 text-gray-800'
    }
  }

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: 'VND'
    }).format(amount)
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

  const getCRStats = () => {
    const total = changeRequests.length
    const pending = changeRequests.filter(cr => cr.status === 'pending').length
    const approved = changeRequests.filter(cr => cr.status === 'approved').length
    const rejected = changeRequests.filter(cr => cr.status === 'rejected').length
    const implemented = changeRequests.filter(cr => cr.status === 'implemented').length
    
    const totalCostImpact = changeRequests.reduce((sum, cr) => sum + cr.costImpact, 0)
    const totalTimeImpact = changeRequests.reduce((sum, cr) => sum + cr.timeImpact, 0)
    
    return { total, pending, approved, rejected, implemented, totalCostImpact, totalTimeImpact }
  }

  const stats = getCRStats()

  return (
    <div className="space-y-4">
      {/* Header */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <CardTitle className="flex items-center gap-2">
              <FileText className="h-5 w-5" />
              Change Requests Module
            </CardTitle>
            <Button onClick={() => setShowCreateForm(true)}>
              <Plus className="h-4 w-4 mr-2" />
              New Change Request
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          {/* Stats */}
          <div className="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
            <div className="text-center">
              <div className="text-2xl font-bold text-gray-900">{stats.total}</div>
              <div className="text-sm text-gray-500">Total CRs</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-yellow-600">{stats.pending}</div>
              <div className="text-sm text-gray-500">Pending</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-green-600">{stats.approved}</div>
              <div className="text-sm text-gray-500">Approved</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-red-600">{stats.rejected}</div>
              <div className="text-sm text-gray-500">Rejected</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-blue-600">{stats.implemented}</div>
              <div className="text-sm text-gray-500">Implemented</div>
            </div>
            <div className="text-center">
              <div className="text-lg font-bold text-gray-900">{stats.totalTimeImpact}</div>
              <div className="text-sm text-gray-500">Days Impact</div>
            </div>
          </div>

          {/* Search and Filters */}
          <div className="flex items-center gap-4">
            <div className="flex-1 relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
              <Input
                placeholder="Search change requests..."
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
              <option value="scope">Scope</option>
              <option value="budget">Budget</option>
              <option value="timeline">Timeline</option>
              <option value="resource">Resource</option>
              <option value="quality">Quality</option>
            </select>

            <select
              value={filterStatus}
              onChange={(e) => setFilterStatus(e.target.value)}
              className="px-3 py-2 border rounded text-sm"
            >
              <option value="all">All Status</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
              <option value="implemented">Implemented</option>
              <option value="cancelled">Cancelled</option>
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
          </div>
        </CardContent>
      </Card>

      {/* Change Requests */}
      <div className="space-y-4">
        {filteredChangeRequests.map((cr) => (
          <Card key={cr.id} className="hover:shadow-lg transition-shadow">
            <CardHeader className="pb-3">
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  <CardTitle className="text-lg">{cr.title}</CardTitle>
                  <p className="text-sm text-gray-600 mt-1">{cr.description}</p>
                </div>
                <div className="flex items-center gap-2">
                  <Badge className={cn('text-xs', getStatusColor(cr.status))}>
                    {getStatusIcon(cr.status)}
                    <span className="ml-1 capitalize">{cr.status}</span>
                  </Badge>
                  <Badge variant="outline" className="text-xs capitalize">
                    {cr.type}
                  </Badge>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div className="space-y-4">
                {/* Basic Info */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                  <div className="flex items-center gap-2">
                    <Users className="h-4 w-4 text-gray-500" />
                    <span className="text-gray-600">Requested by:</span>
                    <span className="font-medium">{cr.requestedBy.name}</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <Calendar className="h-4 w-4 text-gray-500" />
                    <span className="text-gray-600">Requested:</span>
                    <span className="font-medium">{formatDate(cr.requestedAt)}</span>
                  </div>
                  <div className="flex items-center gap-2">
                    <DollarSign className="h-4 w-4 text-gray-500" />
                    <span className="text-gray-600">Cost Impact:</span>
                    <span className={cn('font-medium', cr.costImpact > 0 ? 'text-red-600' : 'text-green-600')}>
                      {formatCurrency(cr.costImpact)}
                    </span>
                  </div>
                  <div className="flex items-center gap-2">
                    <Clock className="h-4 w-4 text-gray-500" />
                    <span className="text-gray-600">Time Impact:</span>
                    <span className={cn('font-medium', cr.timeImpact > 0 ? 'text-red-600' : 'text-green-600')}>
                      {cr.timeImpact} days
                    </span>
                  </div>
                </div>

                {/* Priority and Risk */}
                <div className="flex items-center gap-4">
                  <div className={cn('border-l-4 pl-3 py-1', getPriorityColor(cr.priority))}>
                    <span className="text-sm font-medium capitalize">{cr.priority} Priority</span>
                  </div>
                  <Badge className={cn('text-xs', getRiskColor(cr.riskAssessment.level))}>
                    <AlertTriangle className="h-3 w-3 mr-1" />
                    {cr.riskAssessment.level} Risk
                  </Badge>
                </div>

                {/* Impact Analysis */}
                <div className="bg-gray-50 p-3 rounded">
                  <div className="text-sm font-medium text-gray-700 mb-2">Impact Analysis:</div>
                  <p className="text-sm text-gray-600">{cr.impactAnalysis.description}</p>
                  {cr.impactAnalysis.affectedAreas.length > 0 && (
                    <div className="mt-2">
                      <span className="text-xs font-medium text-gray-600">Affected Areas:</span>
                      <div className="flex flex-wrap gap-1 mt-1">
                        {cr.impactAnalysis.affectedAreas.map((area, index) => (
                          <Badge key={index} variant="outline" className="text-xs">
                            {area}
                          </Badge>
                        ))}
                      </div>
                    </div>
                  )}
                </div>

                {/* Tags */}
                {cr.tags.length > 0 && (
                  <div className="flex flex-wrap gap-1">
                    {cr.tags.slice(0, 5).map((tag, index) => (
                      <Badge key={index} variant="outline" className="text-xs">
                        {tag}
                      </Badge>
                    ))}
                    {cr.tags.length > 5 && (
                      <Badge variant="outline" className="text-xs">
                        +{cr.tags.length - 5}
                      </Badge>
                    )}
                  </div>
                )}

                {/* Actions */}
                <div className="flex items-center gap-2 pt-2 border-t">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => setSelectedCR(cr.id)}
                    className="flex-1"
                  >
                    <Eye className="h-3 w-3 mr-1" />
                    View Details
                  </Button>
                  
                  {cr.status === 'pending' && (
                    <>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setShowApprovalForm(cr.id)}
                        className="text-green-600 hover:text-green-700"
                      >
                        <CheckCircle className="h-3 w-3 mr-1" />
                        Approve
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onRejectCR?.(cr.id, '')}
                        className="text-red-600 hover:text-red-700"
                      >
                        <XCircle className="h-3 w-3 mr-1" />
                        Reject
                      </Button>
                    </>
                  )}
                  
                  {cr.status === 'approved' && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => onImplementCR?.(cr.id)}
                      className="text-blue-600 hover:text-blue-700"
                    >
                      <CheckCircle className="h-3 w-3 mr-1" />
                      Implement
                    </Button>
                  )}
                  
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {/* Edit CR */}}
                  >
                    <Edit className="h-3 w-3" />
                  </Button>
                  
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onDeleteCR?.(cr.id)}
                    className="text-red-600 hover:text-red-700"
                  >
                    <Trash2 className="h-3 w-3" />
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Create CR Form Modal */}
      {showCreateForm && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <Card className="w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <CardHeader>
              <CardTitle>Create New Change Request</CardTitle>
            </CardHeader>
            <CardContent>
              <form className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium mb-1">Title</label>
                    <Input placeholder="Change Request Title" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1">Type</label>
                    <select className="w-full px-3 py-2 border rounded">
                      <option value="scope">Scope</option>
                      <option value="budget">Budget</option>
                      <option value="timeline">Timeline</option>
                      <option value="resource">Resource</option>
                      <option value="quality">Quality</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium mb-1">Description</label>
                  <Textarea placeholder="Detailed description of the change request" rows={4} />
                </div>

                <div className="grid grid-cols-3 gap-4">
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
                    <label className="block text-sm font-medium mb-1">Cost Impact (VND)</label>
                    <Input type="number" placeholder="0" />
                  </div>
                  <div>
                    <label className="block text-sm font-medium mb-1">Time Impact (Days)</label>
                    <Input type="number" placeholder="0" />
                  </div>
                </div>

                <div>
                  <label className="block text-sm font-medium mb-1">Impact Analysis</label>
                  <Textarea placeholder="Describe the impact of this change" rows={3} />
                </div>

                <div>
                  <label className="block text-sm font-medium mb-1">Risk Assessment</label>
                  <div className="grid grid-cols-2 gap-4">
                    <select className="px-3 py-2 border rounded">
                      <option value="low">Low Risk</option>
                      <option value="medium">Medium Risk</option>
                      <option value="high">High Risk</option>
                      <option value="critical">Critical Risk</option>
                    </select>
                    <Textarea placeholder="Risk description" rows={2} />
                  </div>
                </div>

                <div className="flex justify-end gap-2">
                  <Button variant="outline" onClick={() => setShowCreateForm(false)}>
                    Cancel
                  </Button>
                  <Button onClick={() => {/* Create CR */}}>
                    Create Change Request
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      )}

      {filteredChangeRequests.length === 0 && (
        <Card>
          <CardContent className="text-center py-12">
            <FileText className="h-12 w-12 mx-auto mb-4 text-gray-400" />
            <h3 className="text-lg font-medium mb-2">No change requests found</h3>
            <p className="text-gray-500 mb-4">
              {searchTerm || filterType !== 'all' || filterStatus !== 'all'
                ? 'Try adjusting your search or filters'
                : 'Create your first change request to get started'
              }
            </p>
            <Button onClick={() => setShowCreateForm(true)}>
              <Plus className="h-4 w-4 mr-2" />
              Create Change Request
            </Button>
          </CardContent>
        </Card>
      )}
    </div>
  )
}

export default ChangeRequestsModule
