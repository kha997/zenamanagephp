import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus, Search, Filter, Grid, List } from 'lucide-react'
import { useTemplatesStore } from '../../../store/templates'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { Select } from '../../../components/ui/Select'
import { Card } from '../../../components/ui/Card'
import { Badge } from '../../../components/ui/Badge'
import { Pagination } from '../../../components/ui/Pagination'
import { LoadingSpinner } from '../../../components/ui/loading-spinner'
import { TemplateCard } from '../components/TemplateCard'
import { TemplateFilters } from '../components/TemplateFilters'

export const TemplatesList: React.FC = () => {
  const {
    templates,
    loading,
    pagination,
    filters,
    fetchTemplates,
    setFilters
  } = useTemplatesStore()

  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid')
  const [showFilters, setShowFilters] = useState(false)

  useEffect(() => {
    fetchTemplates({ ...filters, page: pagination.page })
  }, [filters, pagination.page])

  const handleSearch = (search: string) => {
    setFilters({ search })
  }

  const handlePageChange = (page: number) => {
    fetchTemplates({ ...filters, page })
  }

  if (loading.isLoading && templates.length === 0) {
    return (
      <div className="flex items-center justify-center h-64">
        <LoadingSpinner size="lg" />
      </div>
    )
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Templates</h1>
          <p className="text-gray-600">Quản lý các mẫu công việc cho dự án</p>
        </div>
        <Link to="/templates/create">
          <Button>
            <Plus className="w-4 h-4 mr-2" />
            Tạo Template
          </Button>
        </Link>
      </div>

      {/* Search and Filters */}
      <Card className="p-4">
        <div className="flex items-center gap-4">
          <div className="flex-1">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
              <Input
                placeholder="Tìm kiếm templates..."
                value={filters.search || ''}
                onChange={(e) => handleSearch(e.target.value)}
                className="pl-10"
              />
            </div>
          </div>
          
          <Button
            variant="outline"
            onClick={() => setShowFilters(!showFilters)}
          >
            <Filter className="w-4 h-4 mr-2" />
            Bộ lọc
          </Button>

          <div className="flex border rounded-lg">
            <Button
              variant={viewMode === 'grid' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => setViewMode('grid')}
              className="rounded-r-none"
            >
              <Grid className="w-4 h-4" />
            </Button>
            <Button
              variant={viewMode === 'list' ? 'default' : 'ghost'}
              size="sm"
              onClick={() => setViewMode('list')}
              className="rounded-l-none"
            >
              <List className="w-4 h-4" />
            </Button>
          </div>
        </div>

        {showFilters && (
          <div className="mt-4 pt-4 border-t">
            <TemplateFilters />
          </div>
        )}
      </Card>

      {/* Templates Grid/List */}
      {loading.error && (
        <Card className="p-4 border-red-200 bg-red-50">
          <p className="text-red-600">{loading.error}</p>
        </Card>
      )}

      {templates.length === 0 && !loading.isLoading ? (
        <Card className="p-8 text-center">
          <div className="text-gray-400 mb-4">
            <Plus className="w-12 h-12 mx-auto" />
          </div>
          <h3 className="text-lg font-medium text-gray-900 mb-2">
            Chưa có template nào
          </h3>
          <p className="text-gray-600 mb-4">
            Tạo template đầu tiên để bắt đầu sử dụng
          </p>
          <Link to="/templates/create">
            <Button>
              <Plus className="w-4 h-4 mr-2" />
              Tạo Template
            </Button>
          </Link>
        </Card>
      ) : (
        <>
          <div className={viewMode === 'grid' 
            ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6'
            : 'space-y-4'
          }>
            {templates.map((template) => (
              <TemplateCard
                key={template.id}
                template={template}
                viewMode={viewMode}
              />
            ))}
          </div>

          {pagination.totalPages > 1 && (
            <div className="flex justify-center">
              <Pagination
                currentPage={pagination.page}
                totalPages={pagination.totalPages}
                onPageChange={handlePageChange}
              />
            </div>
          )}
        </>
      )}

      {loading.isLoading && templates.length > 0 && (
        <div className="flex justify-center py-4">
          <LoadingSpinner />
        </div>
      )}
    </div>
  )
}