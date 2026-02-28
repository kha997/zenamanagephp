import { FormEvent, useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { LoadingSpinner } from '@/components/ui/loading-spinner'
import {
  createDeliverableTemplate,
  listDeliverableTemplates,
  type DeliverableTemplateRecord,
} from '@/features/deliverable-templates/api'

export function DeliverableTemplatesListPage() {
  const navigate = useNavigate()
  const [templates, setTemplates] = useState<DeliverableTemplateRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [creating, setCreating] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [name, setName] = useState('')
  const [code, setCode] = useState('')
  const [description, setDescription] = useState('')

  useEffect(() => {
    let mounted = true

    listDeliverableTemplates({ per_page: 50 })
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

        const message = err instanceof Error ? err.message : 'Failed to load deliverable templates'
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

  const onCreate = async (event: FormEvent) => {
    event.preventDefault()

    if (!name.trim() || !code.trim()) {
      setError('Name and code are required.')
      return
    }

    try {
      setCreating(true)
      const created = await createDeliverableTemplate({
        name: name.trim(),
        code: code.trim(),
        description: description.trim() || undefined,
      })
      navigate(`/deliverable-templates/${created.id}`)
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to create deliverable template'
      setError(message)
    } finally {
      setCreating(false)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Deliverable Templates</h1>
        <p className="text-sm text-gray-600">Create templates, upload HTML drafts, then publish immutable versions.</p>
      </div>

      <Card className="p-4">
        <form className="grid gap-3 md:grid-cols-2" onSubmit={onCreate}>
          <label className="text-sm text-gray-700">
            Name
            <input
              className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
              value={name}
              onChange={(event) => setName(event.target.value)}
              placeholder="Pour Report"
            />
          </label>
          <label className="text-sm text-gray-700">
            Code
            <input
              className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
              value={code}
              onChange={(event) => setCode(event.target.value)}
              placeholder="DT_POUR_REPORT"
            />
          </label>
          <label className="text-sm text-gray-700 md:col-span-2">
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
        {loading ? (
          <div className="flex h-24 items-center justify-center">
            <LoadingSpinner size="md" />
          </div>
        ) : null}

        {error ? <p className="text-sm text-red-600">{error}</p> : null}

        {!loading && !error && templates.length === 0 ? (
          <p className="text-sm text-gray-500">No deliverable templates yet.</p>
        ) : null}

        <div className="space-y-2">
          {templates.map((template) => (
            <div key={template.id} className="rounded border border-gray-200 p-3">
              <div className="flex items-center justify-between">
                <div>
                  <p className="font-medium text-gray-900">{template.name}</p>
                  <p className="text-xs text-gray-500">
                    {template.code} | {template.status}
                  </p>
                </div>
                <Link to={`/deliverable-templates/${template.id}`} className="text-sm text-blue-600 hover:underline">
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
