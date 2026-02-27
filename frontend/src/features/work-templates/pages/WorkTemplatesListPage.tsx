import { FormEvent, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { LoadingSpinner } from '@/components/ui/loading-spinner'
import {
  createWorkTemplate,
  listWorkTemplates,
  type TemplateStatus,
  type WorkTemplateRecord,
} from '@/features/work-templates/api'

const STATUS_OPTIONS: TemplateStatus[] = ['draft', 'published', 'archived']

export function WorkTemplatesListPage() {
  const navigate = useNavigate()
  const [templates, setTemplates] = useState<WorkTemplateRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [statusFilter, setStatusFilter] = useState<TemplateStatus>('draft')
  const [creating, setCreating] = useState(false)

  const [name, setName] = useState('')
  const [code, setCode] = useState('')
  const [vertical, setVertical] = useState('')
  const [description, setDescription] = useState('')

  useEffect(() => {
    let mounted = true
    setLoading(true)
    listWorkTemplates({ per_page: 50 })
      .then(({ items }) => {
        if (!mounted) {
          return
        }
        setTemplates(items)
        setError(null)
      })
      .catch((err: unknown) => {
        if (!mounted) {
          return
        }
        const message = err instanceof Error ? err.message : 'Failed to load work templates'
        setError(message)
      })
      .finally(() => {
        if (mounted) {
          setLoading(false)
        }
      })

    return () => {
      mounted = false
    }
  }, [])

  const filteredTemplates = useMemo(
    () => templates.filter((template) => template.status === statusFilter),
    [statusFilter, templates]
  )

  const onCreate = async (event: FormEvent) => {
    event.preventDefault()
    if (!name.trim() || !code.trim()) {
      setError('Name and code are required.')
      return
    }

    try {
      setCreating(true)
      const created = await createWorkTemplate({
        name: name.trim(),
        code: code.trim(),
        vertical: vertical.trim(),
        description: description.trim() || undefined,
      })
      navigate(`/work-templates/${created.id}`)
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to create work template'
      setError(message)
    } finally {
      setCreating(false)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Work Templates</h1>
          <p className="text-sm text-gray-600">Create drafts, edit steps/fields, then publish.</p>
        </div>
      </div>

      <Card className="p-4">
        <form className="grid gap-3 md:grid-cols-2" onSubmit={onCreate}>
          <label className="text-sm text-gray-700">
            Name
            <input
              className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
              value={name}
              onChange={(event) => setName(event.target.value)}
              placeholder="Facade QA Checklist"
            />
          </label>
          <label className="text-sm text-gray-700">
            Code
            <input
              className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
              value={code}
              onChange={(event) => setCode(event.target.value)}
              placeholder="FACADE_QA"
            />
          </label>
          <label className="text-sm text-gray-700">
            Vertical
            <input
              className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
              value={vertical}
              onChange={(event) => setVertical(event.target.value)}
              placeholder="construction"
            />
          </label>
          <label className="text-sm text-gray-700">
            Description
            <input
              className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
              value={description}
              onChange={(event) => setDescription(event.target.value)}
              placeholder="Short summary"
            />
          </label>
          <div className="md:col-span-2">
            <Button type="submit" disabled={creating}>
              {creating ? 'Creating...' : 'Create Draft'}
            </Button>
          </div>
        </form>
      </Card>

      <Card className="p-4">
        <div className="mb-3 flex items-center gap-3">
          <label className="text-sm font-medium text-gray-700" htmlFor="wt-status-filter">
            Status
          </label>
          <select
            id="wt-status-filter"
            className="rounded border border-gray-300 px-2 py-1 text-sm"
            value={statusFilter}
            onChange={(event) => setStatusFilter(event.target.value as TemplateStatus)}
          >
            {STATUS_OPTIONS.map((status) => (
              <option key={status} value={status}>
                {status}
              </option>
            ))}
          </select>
        </div>

        {loading ? (
          <div className="flex h-24 items-center justify-center">
            <LoadingSpinner size="md" />
          </div>
        ) : null}

        {error ? <p className="text-sm text-red-600">{error}</p> : null}

        {!loading && !error && filteredTemplates.length === 0 ? (
          <p className="text-sm text-gray-500">No templates found for this status.</p>
        ) : null}

        <div className="space-y-2">
          {filteredTemplates.map((template) => (
            <div key={template.id} className="rounded border border-gray-200 p-3">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium text-gray-900">{template.name}</p>
                  <p className="text-xs text-gray-500">
                    {template.code} | {template.status}
                  </p>
                </div>
                <Link to={`/work-templates/${template.id}`} className="text-sm text-blue-600 hover:underline">
                  Open
                </Link>
              </div>
            </div>
          ))}
        </div>
      </Card>
    </div>
  )
}
