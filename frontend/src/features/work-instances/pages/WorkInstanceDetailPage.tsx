import { type ChangeEvent, useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { Button } from '@/components/ui/Button'
import { Card } from '@/components/ui/Card'
import { LoadingSpinner } from '@/components/ui/loading-spinner'
import {
  approveWorkInstanceStep,
  cacheWorkInstance,
  deleteWorkInstanceStepAttachment,
  getWorkInstance,
  listWorkInstanceStepAttachments,
  uploadWorkInstanceStepAttachment,
  type WorkFieldDef,
  type WorkInstanceRecord,
  type WorkInstanceStep,
  type WorkStepAttachment,
  updateWorkInstanceStep,
} from '@/features/work-instances/api'

const extractFieldValue = (step: WorkInstanceStep, fieldKey: string): string | number | boolean => {
  const valueRecord = (step.values || []).find((value) => value.field_key === fieldKey)
  if (!valueRecord) {
    return ''
  }

  if (valueRecord.value_number !== null && valueRecord.value_number !== undefined) {
    return valueRecord.value_number
  }
  if (valueRecord.value_date) {
    return valueRecord.value_date
  }
  if (typeof valueRecord.value_string === 'string') {
    if (valueRecord.value_string === 'true') {
      return true
    }
    if (valueRecord.value_string === 'false') {
      return false
    }
    return valueRecord.value_string
  }

  return ''
}

const shouldRequireApproval = (step: WorkInstanceStep) => {
  if (step.type.toLowerCase() === 'approval') {
    return true
  }

  if ((step.assignee_rule_json || {}).requires_approval) {
    return true
  }

  return /approve|approval/i.test(step.name)
}

const normalizeInstance = (instance: WorkInstanceRecord): WorkInstanceRecord => ({
  ...instance,
  steps: [...instance.steps].sort((a, b) => {
    const aOrder = Number((a as unknown as { step_order?: number }).step_order || 0)
    const bOrder = Number((b as unknown as { step_order?: number }).step_order || 0)
    return aOrder - bOrder
  }),
})

export function WorkInstanceDetailPage() {
  const { id } = useParams<{ id: string }>()

  const [instance, setInstance] = useState<WorkInstanceRecord | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [selectedStepId, setSelectedStepId] = useState<string>('')
  const [fieldValues, setFieldValues] = useState<Record<string, unknown>>({})
  const [status, setStatus] = useState('in_progress')
  const [submitting, setSubmitting] = useState(false)
  const [approvalComment, setApprovalComment] = useState('')
  const [attachments, setAttachments] = useState<WorkStepAttachment[]>([])
  const [attachmentsLoading, setAttachmentsLoading] = useState(false)
  const [attachmentSubmitting, setAttachmentSubmitting] = useState(false)

  useEffect(() => {
    if (!id) {
      setError('Work instance id is missing')
      setLoading(false)
      return
    }

    let mounted = true
    setLoading(true)
    getWorkInstance(id)
      .then((record) => {
        if (!mounted) {
          return
        }

        const normalized = normalizeInstance(record)
        setInstance(normalized)
        const firstStep = normalized.steps[0]
        if (firstStep) {
          setSelectedStepId(firstStep.id)
        }
        setError(null)
      })
      .catch((err: unknown) => {
        if (!mounted) {
          return
        }
        const message = err instanceof Error ? err.message : 'Failed to load work instance'
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

  const selectedStep = useMemo(
    () => instance?.steps.find((step) => step.id === selectedStepId) || null,
    [instance, selectedStepId]
  )

  useEffect(() => {
    if (!selectedStep) {
      setFieldValues({})
      setAttachments([])
      return
    }

    const values: Record<string, unknown> = {}
    ;(selectedStep.snapshot_fields_json || []).forEach((field) => {
      const existing = extractFieldValue(selectedStep, field.field_key)
      values[field.field_key] = existing === '' ? field.default ?? '' : existing
    })

    setFieldValues(values)
    setStatus(selectedStep.status || 'in_progress')
  }, [selectedStep])

  useEffect(() => {
    if (!instance || !selectedStep) {
      return
    }

    let mounted = true
    setAttachmentsLoading(true)
    listWorkInstanceStepAttachments(instance.id, selectedStep.id)
      .then((items) => {
        if (!mounted) {
          return
        }
        setAttachments(items)
      })
      .catch(() => {
        if (mounted) {
          setAttachments([])
        }
      })
      .finally(() => {
        if (mounted) {
          setAttachmentsLoading(false)
        }
      })

    return () => {
      mounted = false
    }
  }, [instance, selectedStep])

  const onSaveStep = async () => {
    if (!instance || !selectedStep) {
      return
    }

    try {
      setSubmitting(true)
      const updated = await updateWorkInstanceStep(instance.id, selectedStep.id, {
        status,
        field_values: fieldValues,
      })

      const nextInstance = {
        ...instance,
        steps: instance.steps.map((step) => (step.id === selectedStep.id ? updated.step : step)),
      }
      setInstance(nextInstance)
      cacheWorkInstance(nextInstance)
      setError(null)
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to update step'
      setError(message)
    } finally {
      setSubmitting(false)
    }
  }

  const onDecision = async (decision: 'approved' | 'rejected') => {
    if (!instance || !selectedStep) {
      return
    }

    try {
      setSubmitting(true)
      await approveWorkInstanceStep(instance.id, selectedStep.id, {
        decision,
        comment: approvalComment.trim() || undefined,
      })

      const nextInstance = {
        ...instance,
        steps: instance.steps.map((step) =>
          step.id === selectedStep.id
            ? {
                ...step,
                status: decision,
              }
            : step
        ),
      }
      setInstance(nextInstance)
      cacheWorkInstance(nextInstance)
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to submit decision'
      setError(message)
    } finally {
      setSubmitting(false)
    }
  }

  const onUploadAttachment = async (event: ChangeEvent<HTMLInputElement>) => {
    const selectedFile = event.target.files?.[0]
    event.target.value = ''

    if (!selectedFile || !instance || !selectedStep) {
      return
    }

    try {
      setAttachmentSubmitting(true)
      const uploaded = await uploadWorkInstanceStepAttachment(instance.id, selectedStep.id, selectedFile)
      setAttachments((current) => [uploaded, ...current])
      setError(null)
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to upload attachment'
      setError(message)
    } finally {
      setAttachmentSubmitting(false)
    }
  }

  const onDeleteAttachment = async (attachmentId: string) => {
    if (!instance || !selectedStep) {
      return
    }

    try {
      setAttachmentSubmitting(true)
      await deleteWorkInstanceStepAttachment(instance.id, selectedStep.id, attachmentId)
      setAttachments((current) => current.filter((attachment) => attachment.id !== attachmentId))
      setError(null)
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to delete attachment'
      setError(message)
    } finally {
      setAttachmentSubmitting(false)
    }
  }

  const renderField = (field: WorkFieldDef) => {
    const value = fieldValues[field.field_key]

    if (field.type === 'text') {
      return (
        <textarea
          rows={3}
          className="w-full rounded border border-gray-300 px-3 py-2"
          value={String(value ?? '')}
          onChange={(event) =>
            setFieldValues((current) => ({
              ...current,
              [field.field_key]: event.target.value,
            }))
          }
        />
      )
    }

    if (field.type === 'number') {
      return (
        <input
          type="number"
          className="w-full rounded border border-gray-300 px-3 py-2"
          value={String(value ?? '')}
          onChange={(event) =>
            setFieldValues((current) => ({
              ...current,
              [field.field_key]: event.target.value === '' ? '' : Number(event.target.value),
            }))
          }
        />
      )
    }

    if (field.type === 'date') {
      return (
        <input
          type="date"
          className="w-full rounded border border-gray-300 px-3 py-2"
          value={String(value ?? '')}
          onChange={(event) =>
            setFieldValues((current) => ({
              ...current,
              [field.field_key]: event.target.value,
            }))
          }
        />
      )
    }

    if (field.type === 'enum') {
      return (
        <select
          className="w-full rounded border border-gray-300 px-3 py-2"
          value={String(value ?? '')}
          onChange={(event) =>
            setFieldValues((current) => ({
              ...current,
              [field.field_key]: event.target.value,
            }))
          }
        >
          <option value="">Select</option>
          {(field.enum_options || []).map((option) => (
            <option key={option} value={option}>
              {option}
            </option>
          ))}
        </select>
      )
    }

    if (field.type === 'boolean') {
      return (
        <label className="inline-flex items-center gap-2 text-sm text-gray-700">
          <input
            type="checkbox"
            checked={Boolean(value)}
            onChange={(event) =>
              setFieldValues((current) => ({
                ...current,
                [field.field_key]: event.target.checked,
              }))
            }
          />
          Yes
        </label>
      )
    }

    return (
      <input
        type="text"
        className="w-full rounded border border-gray-300 px-3 py-2"
        value={String(value ?? '')}
        onChange={(event) =>
          setFieldValues((current) => ({
            ...current,
            [field.field_key]: event.target.value,
          }))
        }
      />
    )
  }

  if (loading) {
    return (
      <div className="flex h-40 items-center justify-center">
        <LoadingSpinner />
      </div>
    )
  }

  if (!instance) {
    return (
      <Card className="p-6">
        <p className="text-sm text-gray-700">Work instance not found.</p>
        {error ? <p className="mt-2 text-sm text-red-600">{error}</p> : null}
        <Link className="mt-3 inline-block text-sm text-blue-600 hover:underline" to="/work-instances">
          Back to Work Instances
        </Link>
      </Card>
    )
  }

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Work Instance {instance.id}</h1>
        <p className="text-sm text-gray-600">Status: {instance.status}</p>
      </div>

      {error ? <p className="text-sm text-red-600">{error}</p> : null}

      <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
        <Card className="p-3">
          <h2 className="mb-3 font-semibold text-gray-900">Steps</h2>
          <div className="space-y-2">
            {instance.steps.map((step) => (
              <button
                key={step.id}
                className={`w-full rounded border px-3 py-2 text-left text-sm ${
                  step.id === selectedStepId ? 'border-blue-500 bg-blue-50' : 'border-gray-200'
                }`}
                onClick={() => setSelectedStepId(step.id)}
                type="button"
              >
                <p className="font-medium text-gray-900">{step.name}</p>
                <p className="text-xs text-gray-500">{step.status}</p>
              </button>
            ))}
          </div>
        </Card>

        <Card className="p-4">
          {!selectedStep ? <p className="text-sm text-gray-500">Select a step.</p> : null}

          {selectedStep ? (
            <div className="space-y-4">
              <div>
                <h2 className="text-lg font-semibold text-gray-900">{selectedStep.name}</h2>
                <p className="text-xs text-gray-600">type: {selectedStep.type}</p>
              </div>

              <label className="text-sm text-gray-700">
                Status
                <select
                  className="mt-1 w-full rounded border border-gray-300 px-3 py-2"
                  value={status}
                  onChange={(event) => setStatus(event.target.value)}
                >
                  <option value="pending">pending</option>
                  <option value="in_progress">in_progress</option>
                  <option value="completed">completed</option>
                  <option value="blocked">blocked</option>
                  <option value="approved">approved</option>
                  <option value="rejected">rejected</option>
                </select>
              </label>

              <div className="space-y-3">
                {(selectedStep.snapshot_fields_json || []).map((field) => (
                  <label key={field.field_key} className="block text-sm text-gray-700">
                    {field.label}
                    {field.required ? ' *' : ''}
                    <div className="mt-1">{renderField(field)}</div>
                  </label>
                ))}

                {(selectedStep.snapshot_fields_json || []).length === 0 ? (
                  <p className="text-sm text-gray-500">No fields in this step snapshot.</p>
                ) : null}
              </div>

              <div className="flex items-center gap-2">
                <Button onClick={onSaveStep} disabled={submitting}>
                  {submitting ? 'Submitting...' : 'Submit Values'}
                </Button>
              </div>

              <div className="rounded border border-gray-200 p-3">
                <div className="mb-3 flex items-center justify-between gap-2">
                  <p className="text-sm font-medium text-gray-900">Attachments</p>
                  <label className="cursor-pointer rounded border border-gray-300 px-3 py-1 text-xs text-gray-700 hover:bg-gray-50">
                    {attachmentSubmitting ? 'Uploading...' : 'Upload file'}
                    <input
                      type="file"
                      className="hidden"
                      onChange={onUploadAttachment}
                      disabled={attachmentSubmitting}
                    />
                  </label>
                </div>

                {attachmentsLoading ? <p className="text-sm text-gray-500">Loading attachments...</p> : null}

                {!attachmentsLoading && attachments.length === 0 ? (
                  <p className="text-sm text-gray-500">No attachments for this step.</p>
                ) : null}

                <div className="space-y-2">
                  {attachments.map((attachment) => (
                    <div key={attachment.id} className="flex items-center justify-between rounded border border-gray-100 px-3 py-2">
                      <div>
                        <p className="text-sm text-gray-900">{attachment.file_name}</p>
                        <p className="text-xs text-gray-500">
                          {(attachment.file_size / 1024).toFixed(1)} KB
                          {attachment.created_at ? ` • ${new Date(attachment.created_at).toLocaleString()}` : ''}
                        </p>
                      </div>
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() => onDeleteAttachment(attachment.id)}
                        disabled={attachmentSubmitting}
                      >
                        Delete
                      </Button>
                    </div>
                  ))}
                </div>
              </div>

              {shouldRequireApproval(selectedStep) ? (
                <div className="rounded border border-gray-200 p-3">
                  <p className="mb-2 text-sm font-medium text-gray-900">Approval</p>
                  <textarea
                    rows={2}
                    className="mb-2 w-full rounded border border-gray-300 px-3 py-2"
                    value={approvalComment}
                    onChange={(event) => setApprovalComment(event.target.value)}
                    placeholder="Comment"
                  />
                  <div className="flex gap-2">
                    <Button type="button" onClick={() => onDecision('approved')} disabled={submitting}>
                      Approve
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      onClick={() => onDecision('rejected')}
                      disabled={submitting}
                    >
                      Reject
                    </Button>
                  </div>
                </div>
              ) : null}
            </div>
          ) : null}
        </Card>
      </div>
    </div>
  )
}
