import { FormEvent, useEffect, useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useTemplatesStore } from '../../../store/templates'
import type { CreateWorkTemplateForm, TemplateTask } from '../../../lib/types'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'

type TaskDraft = {
  name: string
  estimated_hours: number
  priority: TemplateTask['priority']
  description: string
  conditional_tag: string
}

const defaultTaskDraft: TaskDraft = {
  name: '',
  estimated_hours: 1,
  priority: 'medium',
  description: '',
  conditional_tag: ''
}

export const CreateTemplate = () => {
  const navigate = useNavigate()
  const { id } = useParams<{ id: string }>()
  const isEditMode = Boolean(id)

  const {
    currentTemplate,
    loading,
    createTemplate,
    updateTemplate,
    fetchTemplate,
    clearCurrentTemplate
  } = useTemplatesStore()

  const [formData, setFormData] = useState<CreateWorkTemplateForm>({
    name: '',
    description: '',
    category: 'design',
    tags: [],
    template_data: {
      tasks: [],
      conditional_tags: []
    }
  })
  const [taskDraft, setTaskDraft] = useState<TaskDraft>(defaultTaskDraft)
  const [errors, setErrors] = useState<Record<string, string>>({})

  useEffect(() => {
    if (isEditMode && id) {
      fetchTemplate(id)
    }
    return () => {
      clearCurrentTemplate()
    }
  }, [clearCurrentTemplate, fetchTemplate, id, isEditMode])

  useEffect(() => {
    if (!isEditMode || !currentTemplate) {
      return
    }

    setFormData({
      name: currentTemplate.name,
      description: currentTemplate.description || '',
      category: currentTemplate.category,
      tags: currentTemplate.tags || [],
      template_data: {
        tasks: currentTemplate.template_data?.tasks || [],
        conditional_tags: currentTemplate.template_data?.conditional_tags || []
      }
    })
  }, [currentTemplate, isEditMode])

  const sortedTags = useMemo(
    () => (formData.template_data.conditional_tags || []).slice().sort(),
    [formData.template_data.conditional_tags]
  )

  const validateForm = () => {
    const nextErrors: Record<string, string> = {}
    if (!formData.name.trim()) {
      nextErrors.name = 'Ten template la bat buoc'
    }
    if (formData.template_data.tasks.length === 0) {
      nextErrors.tasks = 'Template can it nhat 1 task'
    }
    setErrors(nextErrors)
    return Object.keys(nextErrors).length === 0
  }

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault()
    if (!validateForm()) {
      return
    }

    try {
      if (isEditMode && id) {
        await updateTemplate(id, formData)
      } else {
        await createTemplate(formData)
      }
      navigate('/templates')
    } catch (error) {
      console.error('Save template failed:', error)
    }
  }

  const addTask = () => {
    if (!taskDraft.name.trim()) {
      return
    }

    const newTask: TemplateTask = {
      name: taskDraft.name.trim(),
      estimated_hours: taskDraft.estimated_hours,
      priority: taskDraft.priority,
      description: taskDraft.description.trim() || undefined,
      conditional_tag: taskDraft.conditional_tag.trim() || undefined
    }

    setFormData((prev) => ({
      ...prev,
      template_data: {
        ...prev.template_data,
        tasks: [...prev.template_data.tasks, newTask]
      }
    }))
    setTaskDraft(defaultTaskDraft)
  }

  const removeTask = (index: number) => {
    setFormData((prev) => ({
      ...prev,
      template_data: {
        ...prev.template_data,
        tasks: prev.template_data.tasks.filter((_, taskIndex) => taskIndex !== index)
      }
    }))
  }

  const addConditionalTag = (tagValue: string) => {
    const normalizedTag = tagValue.trim()
    if (!normalizedTag) {
      return
    }
    setFormData((prev) => {
      const existingTags = prev.template_data.conditional_tags || []
      if (existingTags.includes(normalizedTag)) {
        return prev
      }
      return {
        ...prev,
        template_data: {
          ...prev.template_data,
          conditional_tags: [...existingTags, normalizedTag]
        }
      }
    })
  }

  const removeConditionalTag = (tagValue: string) => {
    setFormData((prev) => ({
      ...prev,
      template_data: {
        ...prev.template_data,
        conditional_tags: (prev.template_data.conditional_tags || []).filter((tag) => tag !== tagValue)
      }
    }))
  }

  const isSaving = loading.isLoading

  return (
    <form onSubmit={handleSubmit} className="mx-auto max-w-4xl space-y-6 px-4 py-6">
      <Card className="space-y-4 p-6">
        <h1 className="text-2xl font-bold text-gray-900">{isEditMode ? 'Chinh sua Template' : 'Tao Template'}</h1>

        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Ten template</label>
          <input
            type="text"
            value={formData.name}
            onChange={(event) =>
              setFormData((prev) => ({
                ...prev,
                name: event.target.value
              }))
            }
            className="w-full rounded-md border border-gray-300 px-3 py-2"
          />
          {errors.name ? <p className="mt-1 text-sm text-red-600">{errors.name}</p> : null}
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Danh muc</label>
          <select
            value={formData.category}
            onChange={(event) =>
              setFormData((prev) => ({
                ...prev,
                category: event.target.value as CreateWorkTemplateForm['category']
              }))
            }
            className="w-full rounded-md border border-gray-300 px-3 py-2"
          >
            <option value="design">Thiet ke</option>
            <option value="construction">Thi cong</option>
            <option value="qc">QC</option>
            <option value="inspection">Nghiem thu</option>
          </select>
        </div>

        <div>
          <label className="mb-1 block text-sm font-medium text-gray-700">Mo ta</label>
          <textarea
            value={formData.description || ''}
            onChange={(event) =>
              setFormData((prev) => ({
                ...prev,
                description: event.target.value
              }))
            }
            rows={3}
            className="w-full rounded-md border border-gray-300 px-3 py-2"
          />
        </div>
      </Card>

      <Card className="space-y-3 p-6">
        <h2 className="text-lg font-semibold text-gray-900">Conditional Tags</h2>
        <div className="flex flex-wrap gap-2">
          {sortedTags.map((tag) => (
            <button
              key={tag}
              type="button"
              onClick={() => removeConditionalTag(tag)}
              className="rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-800"
            >
              {tag} x
            </button>
          ))}
        </div>
        <div className="flex gap-2">
          <input
            type="text"
            placeholder="Nhap tag va bam Enter"
            className="w-full rounded-md border border-gray-300 px-3 py-2"
            onKeyDown={(event) => {
              if (event.key === 'Enter') {
                event.preventDefault()
                addConditionalTag((event.target as HTMLInputElement).value)
                ;(event.target as HTMLInputElement).value = ''
              }
            }}
          />
        </div>
      </Card>

      <Card className="space-y-3 p-6">
        <h2 className="text-lg font-semibold text-gray-900">Tasks</h2>
        <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
          <input
            type="text"
            value={taskDraft.name}
            placeholder="Ten task"
            onChange={(event) => setTaskDraft((prev) => ({ ...prev, name: event.target.value }))}
            className="rounded-md border border-gray-300 px-3 py-2"
          />
          <input
            type="number"
            min={1}
            value={taskDraft.estimated_hours}
            placeholder="So gio du kien"
            onChange={(event) =>
              setTaskDraft((prev) => ({
                ...prev,
                estimated_hours: Number(event.target.value) || 1
              }))
            }
            className="rounded-md border border-gray-300 px-3 py-2"
          />
          <select
            value={taskDraft.priority}
            onChange={(event) =>
              setTaskDraft((prev) => ({
                ...prev,
                priority: event.target.value as TemplateTask['priority']
              }))
            }
            className="rounded-md border border-gray-300 px-3 py-2"
          >
            <option value="low">low</option>
            <option value="medium">medium</option>
            <option value="high">high</option>
            <option value="critical">critical</option>
          </select>
          <input
            type="text"
            value={taskDraft.conditional_tag}
            placeholder="Conditional tag (optional)"
            onChange={(event) =>
              setTaskDraft((prev) => ({
                ...prev,
                conditional_tag: event.target.value
              }))
            }
            className="rounded-md border border-gray-300 px-3 py-2"
          />
          <textarea
            value={taskDraft.description}
            placeholder="Mo ta task"
            rows={2}
            onChange={(event) =>
              setTaskDraft((prev) => ({
                ...prev,
                description: event.target.value
              }))
            }
            className="md:col-span-2 rounded-md border border-gray-300 px-3 py-2"
          />
        </div>
        <Button type="button" onClick={addTask}>
          Them Task
        </Button>

        {errors.tasks ? <p className="text-sm text-red-600">{errors.tasks}</p> : null}

        <div className="space-y-2">
          {formData.template_data.tasks.map((task, index) => (
            <div key={`${task.name}-${index}`} className="flex items-start justify-between rounded border p-3">
              <div>
                <p className="font-medium text-gray-900">
                  {task.name} ({task.estimated_hours}h)
                </p>
                <p className="text-sm text-gray-600">{task.description || 'Khong co mo ta'}</p>
              </div>
              <Button type="button" variant="outline" onClick={() => removeTask(index)}>
                Xoa
              </Button>
            </div>
          ))}
        </div>
      </Card>

      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => navigate('/templates')}>
          Huy
        </Button>
        <Button type="submit" disabled={isSaving}>
          {isSaving ? 'Dang luu...' : 'Luu template'}
        </Button>
      </div>
    </form>
  )
}
