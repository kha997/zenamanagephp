import React, { useState } from 'react'
import { Link } from 'react-router-dom'
import { 
  MoreVertical, 
  Edit, 
  Copy, 
  Trash2, 
  Play, 
  Eye,
  Calendar,
  Clock,
  Tag
} from 'lucide-react'
import { WorkTemplate } from '../../../lib/types'
import { useTemplatesStore } from '../../../store/templates'
import { Card } from '../../../components/ui/Card'
import { Badge } from '../../../components/ui/Badge'
import { Button } from '../../../components/ui/Button'
import { DropdownMenu } from '../../../components/ui/DropdownMenu'
import { useToast } from '../../../hooks/useToast'
import { ApplyTemplateModal } from './ApplyTemplateModal'
import { DuplicateTemplateModal } from './DuplicateTemplateModal'

interface TemplateCardProps {
  template: WorkTemplate
  viewMode: 'grid' | 'list'
}

export const TemplateCard: React.FC<TemplateCardProps> = ({ template, viewMode }) => {
  const { deleteTemplate } = useTemplatesStore()
  const { toast } = useToast()
  const [showApplyModal, setShowApplyModal] = useState(false)
  const [showDuplicateModal, setShowDuplicateModal] = useState(false)

  const handleDelete = async () => {
    if (window.confirm('Bạn có chắc chắn muốn xóa template này?')) {
      try {
        await deleteTemplate(template.id)
        toast.success('Đã xóa template thành công')
      } catch (error: any) {
        toast.error(error.message || 'Không thể xóa template')
      }
    }
  }

  const getCategoryColor = (category: string) => {
    const colors = {
      design: 'bg-blue-100 text-blue-800',
      construction: 'bg-orange-100 text-orange-800',
      qc: 'bg-green-100 text-green-800',
      inspection: 'bg-purple-100 text-purple-800'
    }
    return colors[category as keyof typeof colors] || 'bg-gray-100 text-gray-800'
  }

  if (viewMode === 'list') {
    return (
      <Card className="p-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4 flex-1">
            <div className="flex-1">
              <div className="flex items-center space-x-2 mb-1">
                <Link 
                  to={`/templates/${template.id}`}
                  className="font-medium text-gray-900 hover:text-blue-600"
                >
                  {template.name}
                </Link>
                <Badge className={getCategoryColor(template.category)}>
                  {template.category_label}
                </Badge>
                {!template.is_active && (
                  <Badge variant="secondary">Không hoạt động</Badge>
                )}
              </div>
              <p className="text-sm text-gray-600 line-clamp-1">
                {template.description || 'Không có mô tả'}
              </p>
            </div>
            
            <div className="flex items-center space-x-4 text-sm text-gray-500">
              <div className="flex items-center space-x-1">
                <Clock className="w-4 h-4" />
                <span>{template.tasks_count} tasks</span>
              </div>
              <div className="flex items-center space-x-1">
                <Tag className="w-4 h-4" />
                <span>v{template.version}</span>
              </div>
              <div className="flex items-center space-x-1">
                <Calendar className="w-4 h-4" />
                <span>{new Date(template.updated_at).toLocaleDateString('vi-VN')}</span>
              </div>
            </div>
          </div>

          <div className="flex items-center space-x-2">
            <Button
              size="sm"
              onClick={() => setShowApplyModal(true)}
            >
              <Play className="w-4 h-4 mr-1" />
              Áp dụng
            </Button>
            
            <DropdownMenu
              trigger={
                <Button variant="ghost" size="sm">
                  <MoreVertical className="w-4 h-4" />
                </Button>
              }
              items={[
                {
                  label: 'Xem chi tiết',
                  icon: Eye,
                  onClick: () => window.open(`/templates/${template.id}`, '_blank')
                },
                {
                  label: 'Chỉnh sửa',
                  icon: Edit,
                  onClick: () => window.open(`/templates/${template.id}/edit`, '_blank')
                },
                {
                  label: 'Nhân bản',
                  icon: Copy,
                  onClick: () => setShowDuplicateModal(true)
                },
                { type: 'separator' },
                {
                  label: 'Xóa',
                  icon: Trash2,
                  onClick: handleDelete,
                  className: 'text-red-600'
                }
              ]}
            />
          </div>
        </div>

        <ApplyTemplateModal
          template={template}
          isOpen={showApplyModal}
          onClose={() => setShowApplyModal(false)}
        />
        
        <DuplicateTemplateModal
          template={template}
          isOpen={showDuplicateModal}
          onClose={() => setShowDuplicateModal(false)}
        />
      </Card>
    )
  }

  return (
    <Card className="p-6 hover:shadow-lg transition-shadow">
      <div className="flex items-start justify-between mb-4">
        <div className="flex-1">
          <div className="flex items-center space-x-2 mb-2">
            <Badge className={getCategoryColor(template.category)}>
              {template.category_label}
            </Badge>
            {!template.is_active && (
              <Badge variant="secondary">Không hoạt động</Badge>
            )}
          </div>
          
          <Link 
            to={`/templates/${template.id}`}
            className="block font-semibold text-gray-900 hover:text-blue-600 mb-2"
          >
            {template.name}
          </Link>
          
          <p className="text-sm text-gray-600 line-clamp-2 mb-4">
            {template.description || 'Không có mô tả'}
          </p>
        </div>
        
        <DropdownMenu
          trigger={
            <Button variant="ghost" size="sm">
              <MoreVertical className="w-4 h-4" />
            </Button>
          }
          items={[
            {
              label: 'Xem chi tiết',
              icon: Eye,
              onClick: () => window.open(`/templates/${template.id}`, '_blank')
            },
            {
              label: 'Chỉnh sửa',
              icon: Edit,
              onClick: () => window.open(`/templates/${template.id}/edit`, '_blank')
            },
            {
              label: 'Nhân bản',
              icon: Copy,
              onClick: () => setShowDuplicateModal(true)
            },
            { type: 'separator' },
            {
              label: 'Xóa',
              icon: Trash2,
              onClick: handleDelete,
              className: 'text-red-600'
            }
          ]}
        />
      </div>

      <div className="flex items-center justify-between text-sm text-gray-500 mb-4">
        <div className="flex items-center space-x-4">
          <div className="flex items-center space-x-1">
            <Clock className="w-4 h-4" />
            <span>{template.tasks_count} tasks</span>
          </div>
          <div className="flex items-center space-x-1">
            <Tag className="w-4 h-4" />
            <span>v{template.version}</span>
          </div>
        </div>
        <div className="flex items-center space-x-1">
          <Calendar className="w-4 h-4" />
          <span>{new Date(template.updated_at).toLocaleDateString('vi-VN')}</span>
        </div>
      </div>

      <div className="flex space-x-2">
        <Button
          className="flex-1"
          onClick={() => setShowApplyModal(true)}
        >
          <Play className="w-4 h-4 mr-2" />
          Áp dụng
        </Button>
        <Link to={`/templates/${template.id}`}>
          <Button variant="outline">
            <Eye className="w-4 h-4" />
          </Button>
        </Link>
      </div>

      <ApplyTemplateModal
        template={template}
        isOpen={showApplyModal}
        onClose={() => setShowApplyModal(false)}
      />
      
      <DuplicateTemplateModal
        template={template}
        isOpen={showDuplicateModal}
        onClose={() => setShowDuplicateModal(false)}
      />
    </Card>
  )
}