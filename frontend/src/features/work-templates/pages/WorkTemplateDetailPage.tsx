import { FormEvent, useEffect, useMemo, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { LoadingSpinner } from '@/components/ui/loading-spinner'
import {
  applyTemplateToProject,
  getWorkTemplate,
  publishWorkTemplate,
  updateWorkTemplate,
  type WorkTemplateFieldInput,
  type WorkTemplateRecord,
  type WorkTemplateStepInput,
} from '@/features/work-templates/api'
import { cacheWorkInstance, type WorkInstanceRecord } from '@/features/work-instances/api'

const FIELD_TYPES: WorkTemplateFieldInput['type'][] = ['string', 'text', 'number', 'date', 'enum', 'boolean']

const slugify = (value: string) =>
  value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')

const readDraftState = (template: WorkTemplateRecord) => {
  const versions = template.versions || []
  const draftVersion = versions.find((version) => !version.published_at) || versions[0]
  const content = draftVersion?.content_json || {}
  const rules = (content.rules || {}) as Record<string, unknown>

  return {
    steps: (content.steps || []) as WorkTemplateStepInput[],
    vertical: typeof rules.vertical === 'string' ? rules.vertical : '',
  }
}

export function WorkTemplateDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()

  const [template, setTemplate] = useState<WorkTemplateRecord | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [applying, setApplying] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [vertical, setVertical] = useState('')
  const [steps, setSteps] = useState<WorkTemplateStepInput[]>([])

  const [projectId, setProjectId] = useState('')

  const [newStepType, setNewStepType] = useState('task')
  const [newStepName, setNewStepName] = useState('')
  const [newStepSla, setNewStepSla] = useState('')
  const [newStepAssigneeRole, setNewStepAssigneeRole] = useState('')
  const [newStepAssigneeRule, setNewStepAssigneeRule] = useState('')

  useEffect(() => {
    if (!id) {
      setError('Template id is missing')
      setLoading(false)
      return
    }

    let mounted = true
    setLoading(true)
    getWorkTemplate(id)
      .then((record) => {
        if (!mounted) {
          return
        }
        setTemplate(record)
        setName(record.name)
        setDescription(record.description || '')

        const draftState = readDraftState(record)
        setVertical(draftState.vertical)
        setSteps(draftState.steps)
        setError(null)
      })
      .catch((err: unknown) => {
        if (!mounted) {
          return
        }
        const message = err instanceof Error ? err.message : 'Failed to load template'
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
  }, [id])

  const sortedSteps = useMemo(
    () => [...steps].sort((a, b) => a.order - b.order),
    [steps]
  )

  const addStep = () => {
    const cleanName = newStepName.trim()
    if (!cleanName) {
      return
    }

    const nextOrder = steps.length + 1
    const newStep: WorkTemplateStepInput = {
      key: slugify(cleanName) || `step_${nextOrder}`,
      name: cleanName,
      type: newStepType,
      order: nextOrder,
      sla_hours: newStepSla ? Number(newStepSla) : null,
      assignee_rule: {
        role: newStepAssigneeRole.trim() || undefined,
        rule: newStepAssigneeRule.trim() || undefined,
      },
      fields: [],
    }

    setSteps((current) => [...current, newStep])
    setNewStepName('')
    setNewStepSla('')
    setNewStepAssigneeRole('')
    setNewStepAssigneeRule('')
  }

  const removeStep = (key: string) => {
    setSteps((current) =>
      current
        .filter((step) => step.key !== key)
        .map((step, index) => ({
          ...step,
          order: index + 1,
        }))
    )
  }

  const addFieldToStep = (stepKey: string) => {
    setSteps((current) =>
      current.map((step) => {
        if (step.key !== stepKey) {
          return step
        }

        const nextIndex = step.fields.length + 1
        const newField: WorkTemplateFieldInput = {
          key: `field_${nextIndex}`,
          label: `Field ${nextIndex}`,
          type: 'string',
          required: false,
          default: null,
          enum_options: [],
        }

        return {
          ...step,
          fields: [...step.fields, newField],
        }
      })
    )
  }

  const removeFieldFromStep = (stepKey: string, fieldKey: string) => {
    setSteps((current) =>
      current.map((step) => {
        if (step.key !== stepKey) {
          return step
        }

        return {
          ...step,
          fields: step.fields.filter((field) => field.key !== fieldKey),
        }
      })
    )
  }

  const updateField = (
    stepKey: string,
    fieldKey: string,
    patch: Partial<WorkTemplateFieldInput> & { enumOptionsRaw?: string }
  ) => {
    setSteps((current) =>
      current.map((step) => {
        if (step.key !== stepKey) {
          return step
        }

        return {
          ...step,
          fields: step.fields.map((field) => {
            if (field.key !== fieldKey) {
              return field
            }

            const nextField: WorkTemplateFieldInput = {
              ...field,
              ...patch,
            }

            if (patch.enumOptionsRaw !== undefined) {
              nextField.enum_options = patch.enumOptionsRaw
                .split(',')
                .map((value) => value.trim())
                .filter(Boolean)
            }

            return nextField
          }),
        }
      })
    )
  }

  const onSave = async (event: FormEvent) => {
    event.preventDefault()
    if (!template) {
      return
    }

    try {
      setSaving(true)
      const updated = await updateWorkTemplate(template.id, {
        name: name.trim(),
        description: description.trim() || undefined,
        vertical: vertical.trim() || undefined,
        status: template.status,
        steps,
      })
      setTemplate(updated)
      setError(null)
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to save template draft'
      setError(message)
    } finally {
      setSaving(false)
    }
  }

  const onPublish = async () => {
    if (!template) {
      return
    }

    if (!window.confirm('Publish this template now? This creates an immutable version.')) {
      return
    }

    try {
      setPublishing(true)
      await publishWorkTemplate(template.id)
      navigate('/work-templates')
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to publish template'
      setError(message)
    } finally {
      setPublishing(false)
    }
  }

  const onApply = async () => {
    if (!template || !projectId.trim()) {
      setError('Project ID is required to apply.')
      return
    }

    try {
      setApplying(true)
      const instance = await applyTemplateToProject(projectId.trim(), template.id)
      const cacheRecord: WorkInstanceRecord = {
        id: instance.id,
        status: instance.status,
        project_id: instance.project_id,
        steps: (instance.steps || []).map((step) => ({
          id: step.id,
          step_key: step.step_key,
          name: step.name,
          type: step.type,
          status: step.status,
          snapshot_fields_json: step.snapshot_fields_json,
        })),
      }
      cacheWorkInstance(cacheRecord)
      navigate(`/work-instances/${instance.id}`)
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to apply template'
      setError(message)
    } finally {
      setApplying(false)
    }
  }

  if (loading) {
    return (
      <div className="flex h-40 items-center justify-center">
        <LoadingSpinner />
      </div>
    )
  }

  if (!template) {
    return (
      <Card className="p-6">
        <p className="text-sm text-gray-700">Template not found.</p>
        <Link className="mt-3 inline-block text-sm text-blue-600 hover:underline" to="/work-templates">
          Back to list
        </Link>
      </Card>
    )
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Edit Work Template</h1>
          <p className="text-sm text-gray-600">
            Code: {template.code} | Status: {template.status}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={onPublish} disabled={publishing || saving}>
            {publishing ? 'Publishing...' : 'Publish'}
          </Button>
        </div>
      </div>

      {error ? <p className="text-sm text-red-600">{error}</p> : null}

      <form className="space-y-4" onSubmit={onSave}>
        <Card className="p-4">
          <div className="grid gap-3 md:grid-cols-2">
            <label className="text-sm text-gray-700">
              Name
              <input
                className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                value={name}
                onChange={(event) => setName(event.target.value)}
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
            <label className="text-sm text-gray-700 md:col-span-2">
              Description
              <textarea
                className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                rows={3}
                value={description}
                onChange={(event) => setDescription(event.target.value)}
              />
            </label>
          </div>
          <div className="mt-3">
            <Button type="submit" disabled={saving}>
              {saving ? 'Saving...' : 'Save Draft'}
            </Button>
          </div>
        </Card>

        <Card className="space-y-3 p-4">
          <h2 className="text-lg font-semibold text-gray-900">Steps</h2>
          <div className="grid gap-2 md:grid-cols-5">
            <input
              className="rounded border border-gray-300 px-2 py-2 text-sm"
              value={newStepName}
              onChange={(event) => setNewStepName(event.target.value)}
              placeholder="Step title"
            />
            <input
              className="rounded border border-gray-300 px-2 py-2 text-sm"
              value={newStepSla}
              onChange={(event) => setNewStepSla(event.target.value)}
              placeholder="SLA hours"
              type="number"
              min={0}
            />
            <input
              className="rounded border border-gray-300 px-2 py-2 text-sm"
              value={newStepAssigneeRole}
              onChange={(event) => setNewStepAssigneeRole(event.target.value)}
              placeholder="Assignee role"
            />
            <input
              className="rounded border border-gray-300 px-2 py-2 text-sm"
              value={newStepAssigneeRule}
              onChange={(event) => setNewStepAssigneeRule(event.target.value)}
              placeholder="Assignee rule"
            />
            <div className="flex gap-2">
              <select
                className="w-full rounded border border-gray-300 px-2 py-2 text-sm"
                value={newStepType}
                onChange={(event) => setNewStepType(event.target.value)}
              >
                <option value="task">task</option>
                <option value="approval">approval</option>
                <option value="review">review</option>
              </select>
              <Button type="button" onClick={addStep}>
                Add
              </Button>
            </div>
          </div>

          <div className="space-y-4">
            {sortedSteps.map((step) => (
              <div key={step.key} className="rounded border border-gray-200 p-3">
                <div className="mb-3 flex items-start justify-between">
                  <div>
                    <p className="font-medium text-gray-900">
                      {step.order}. {step.name}
                    </p>
                    <p className="text-xs text-gray-600">
                      {step.type} | key: {step.key} | sla: {step.sla_hours ?? '-'}
                    </p>
                  </div>
                  <Button type="button" variant="outline" onClick={() => removeStep(step.key)}>
                    Remove Step
                  </Button>
                </div>

                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <p className="text-sm font-medium text-gray-700">Fields</p>
                    <Button type="button" variant="outline" onClick={() => addFieldToStep(step.key)}>
                      Add Field
                    </Button>
                  </div>

                  {step.fields.length === 0 ? <p className="text-xs text-gray-500">No fields yet.</p> : null}

                  {step.fields.map((field) => (
                    <div key={field.key} className="grid gap-2 rounded border border-gray-100 p-2 md:grid-cols-7">
                      <input
                        className="rounded border border-gray-300 px-2 py-1 text-xs"
                        value={field.key}
                        onChange={(event) =>
                          updateField(step.key, field.key, {
                            key: slugify(event.target.value),
                          })
                        }
                        placeholder="key"
                      />
                      <input
                        className="rounded border border-gray-300 px-2 py-1 text-xs"
                        value={field.label}
                        onChange={(event) => updateField(step.key, field.key, { label: event.target.value })}
                        placeholder="label"
                      />
                      <select
                        className="rounded border border-gray-300 px-2 py-1 text-xs"
                        value={field.type}
                        onChange={(event) =>
                          updateField(step.key, field.key, {
                            type: event.target.value as WorkTemplateFieldInput['type'],
                          })
                        }
                      >
                        {FIELD_TYPES.map((fieldType) => (
                          <option key={fieldType} value={fieldType}>
                            {fieldType}
                          </option>
                        ))}
                      </select>
                      <label className="flex items-center gap-1 text-xs text-gray-700">
                        <input
                          type="checkbox"
                          checked={Boolean(field.required)}
                          onChange={(event) => updateField(step.key, field.key, { required: event.target.checked })}
                        />
                        Required
                      </label>
                      <input
                        className="rounded border border-gray-300 px-2 py-1 text-xs"
                        value={field.default === null || field.default === undefined ? '' : String(field.default)}
                        onChange={(event) => updateField(step.key, field.key, { default: event.target.value })}
                        placeholder="default"
                      />
                      <input
                        className="rounded border border-gray-300 px-2 py-1 text-xs"
                        value={(field.enum_options || []).join(',')}
                        onChange={(event) =>
                          updateField(step.key, field.key, { enumOptionsRaw: event.target.value })
                        }
                        placeholder="enum a,b,c"
                      />
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() => removeFieldFromStep(step.key, field.key)}
                      >
                        Remove
                      </Button>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </Card>
      </form>

      <Card className="p-4">
        <h2 className="mb-3 text-lg font-semibold text-gray-900">Apply Template</h2>
        <div className="flex flex-col gap-2 md:flex-row">
          <input
            className="w-full rounded border border-gray-300 px-3 py-2"
            value={projectId}
            onChange={(event) => setProjectId(event.target.value)}
            placeholder="Project ID"
          />
          <Button onClick={onApply} disabled={applying}>
            {applying ? 'Applying...' : 'Apply to Project'}
          </Button>
        </div>
      </Card>
    </div>
  )
}
