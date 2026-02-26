import { useEffect } from 'react'
import { Link } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { useTemplatesStore } from '../../../store/templates'
import { Button } from '../../../components/ui/Button'
import { Card } from '../../../components/ui/Card'
import { LoadingSpinner } from '../../../components/ui/loading-spinner'

export const TemplatesList = () => {
  const { templates, loading, fetchTemplates } = useTemplatesStore()

  useEffect(() => {
    fetchTemplates()
  }, [fetchTemplates])

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Templates</h1>
          <p className="text-gray-600">Danh sach mau cong viec</p>
        </div>
        <Link to="/templates/create">
          <Button>
            <Plus className="mr-2 h-4 w-4" />
            Tao Template
          </Button>
        </Link>
      </div>

      {loading.isLoading && templates.length === 0 ? (
        <div className="flex h-64 items-center justify-center">
          <LoadingSpinner size="lg" />
        </div>
      ) : null}

      {loading.error ? (
        <Card className="border-red-200 bg-red-50 p-4">
          <p className="text-red-600">{loading.error}</p>
        </Card>
      ) : null}

      {!loading.isLoading && templates.length === 0 ? (
        <Card className="p-8 text-center text-gray-600">Chua co template nao.</Card>
      ) : null}

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {templates.map((template) => (
          <Card key={template.id} className="p-4">
            <div className="mb-2 flex items-center justify-between">
              <h2 className="font-semibold text-gray-900">{template.name}</h2>
              <span className="text-xs text-gray-500">v{template.version}</span>
            </div>
            <p className="mb-3 text-sm text-gray-600">{template.description || 'Khong co mo ta'}</p>
            <div className="mb-4 text-sm text-gray-500">{template.tasks_count} tasks</div>
            <Link to={`/templates/${template.id}`}>
              <Button variant="outline" className="w-full">
                Xem chi tiet
              </Button>
            </Link>
          </Card>
        ))}
      </div>
    </div>
  )
}
