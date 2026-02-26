import { useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useTemplatesStore } from '../../../store/templates'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'

export const TemplateDetail = () => {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const {
    currentTemplate,
    loading,
    fetchTemplate,
    duplicateTemplate,
    deleteTemplate,
    clearCurrentTemplate
  } = useTemplatesStore()
  const [isDuplicating, setIsDuplicating] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)

  useEffect(() => {
    if (id) {
      fetchTemplate(id)
    }

    return () => {
      clearCurrentTemplate()
    }
  }, [clearCurrentTemplate, fetchTemplate, id])

  const onDuplicate = async () => {
    if (!currentTemplate || isDuplicating) {
      return
    }
    const nextName = `${currentTemplate.name} (Copy)`
    try {
      setIsDuplicating(true)
      const duplicated = await duplicateTemplate(currentTemplate.id, nextName)
      navigate(`/templates/${duplicated.id}`)
    } catch (error) {
      console.error('Duplicate template failed:', error)
    } finally {
      setIsDuplicating(false)
    }
  }

  const onDelete = async () => {
    if (!currentTemplate || isDeleting) {
      return
    }
    if (!window.confirm(`Xoa template "${currentTemplate.name}"?`)) {
      return
    }
    try {
      setIsDeleting(true)
      await deleteTemplate(currentTemplate.id)
      navigate('/templates')
    } catch (error) {
      console.error('Delete template failed:', error)
    } finally {
      setIsDeleting(false)
    }
  }

  if (loading.isLoading && !currentTemplate) {
    return <div className="py-10 text-center text-gray-600">Dang tai template...</div>
  }

  if (!currentTemplate) {
    return (
      <Card className="mx-auto mt-10 max-w-3xl p-6 text-center">
        <p className="mb-3 text-gray-700">Khong tim thay template.</p>
        <Link to="/templates">
          <Button variant="outline">Quay lai danh sach</Button>
        </Link>
      </Card>
    )
  }

  const updatedAtLabel = new Date(currentTemplate.updated_at).toLocaleDateString('vi-VN')
  const createdAtLabel = new Date(currentTemplate.created_at).toLocaleDateString('vi-VN')
  const conditionalTags = currentTemplate.template_data?.conditional_tags || []

  return (
    <div className="mx-auto max-w-5xl space-y-6 px-4 py-6">
      <Card className="p-6">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">{currentTemplate.name}</h1>
            <p className="text-sm text-gray-600">
              {currentTemplate.category_label} | v{currentTemplate.version}
            </p>
          </div>
          <div className="flex gap-2">
            <Button type="button" variant="outline" onClick={onDuplicate} disabled={isDuplicating}>
              {isDuplicating ? 'Dang nhan ban...' : 'Nhan ban'}
            </Button>
            <Button type="button" variant="outline" onClick={onDelete} disabled={isDeleting}>
              {isDeleting ? 'Dang xoa...' : 'Xoa'}
            </Button>
          </div>
        </div>

        <p className="mb-4 text-gray-700">{currentTemplate.description || 'Khong co mo ta'}</p>
        <div className="text-sm text-gray-500">
          <p>Tao: {createdAtLabel}</p>
          <p>Cap nhat: {updatedAtLabel}</p>
          <p>So task: {currentTemplate.tasks_count}</p>
        </div>
      </Card>

      {conditionalTags.length > 0 ? (
        <Card className="p-6">
          <h2 className="mb-3 text-lg font-semibold text-gray-900">Conditional Tags</h2>
          <div className="flex flex-wrap gap-2">
            {conditionalTags.map((tag) => (
              <span key={tag} className="rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-800">
                {tag}
              </span>
            ))}
          </div>
        </Card>
      ) : null}

      <Card className="p-6">
        <h2 className="mb-3 text-lg font-semibold text-gray-900">Tasks</h2>
        <div className="space-y-2">
          {currentTemplate.template_data.tasks.map((task, index) => (
            <div key={`${task.name}-${index}`} className="rounded border p-3">
              <div className="font-medium text-gray-900">{task.name}</div>
              <div className="text-sm text-gray-600">{task.description || 'Khong co mo ta'}</div>
              <div className="text-sm text-gray-500">
                {task.estimated_hours}h | {task.priority}
              </div>
            </div>
          ))}
          {currentTemplate.template_data.tasks.length === 0 ? (
            <p className="text-sm text-gray-500">Template nay chua co task.</p>
          ) : null}
        </div>
      </Card>
    </div>
  )
}
