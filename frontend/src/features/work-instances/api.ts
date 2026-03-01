import type { ApiResponse } from '@/lib/types'
import { apiClient } from '@/lib/api/client'

export type WorkFieldDef = {
  field_key: string
  label: string
  type: 'string' | 'text' | 'number' | 'date' | 'enum' | 'boolean' | string
  required?: boolean
  default?: string | number | boolean | null
  enum_options?: string[]
}

export type WorkFieldValue = {
  field_key: string
  value_string?: string | null
  value_number?: number | null
  value_date?: string | null
  value_datetime?: string | null
  value_json?: unknown
}

export type WorkInstanceStep = {
  id: string
  step_key: string
  name: string
  type: string
  status: string
  snapshot_fields_json?: WorkFieldDef[]
  values?: WorkFieldValue[]
  attachments?: WorkStepAttachment[]
  assignee_rule_json?: {
    requires_approval?: boolean
  }
}

export type WorkStepAttachment = {
  id: string
  file_name: string
  mime_type: string
  file_size: number
  uploaded_by: string
  created_at?: string
}

export type WorkInstanceRecord = {
  id: string
  project_id?: string
  work_template_version_id?: string
  status: string
  steps: WorkInstanceStep[]
  steps_count?: number
  template?: {
    id: string
    name: string
    semver: string
  }
  created_at?: string | null
  updated_at?: string | null
}

export type DeliverablePdfExportOptions = {
  preset?: 'a4_clean'
  orientation?: 'portrait' | 'landscape'
  header_footer?: boolean
  margin_mm?: {
    top?: number
    right?: number
    bottom?: number
    left?: number
  }
}

type ProjectWorkInstancesList = {
  items: WorkInstanceRecord[]
  meta: {
    pagination?: {
      page: number
      per_page: number
      total: number
      last_page: number
    }
  }
}

type PaginationMeta = {
  pagination?: {
    page: number
    per_page: number
    total: number
    last_page: number
  }
}

export type WorkInstanceListParams = {
  page?: number
  per_page?: number
  project_id?: string
  work_template_version_id?: string
  status?: string
  created_by?: string
}

export type WorkInstanceStatusMetric = {
  status: string
  count: number
}

export type WorkInstanceMetrics = {
  total_instances: number
  instances_by_status: WorkInstanceStatusMetric[]
  total_steps: number
  steps_by_status: WorkInstanceStatusMetric[]
  overdue_steps: number
}

const zenaPath = (path: string) => `/api/zena${path}`
const CACHE_KEY = 'work_instance_cache_v1'

const extractFilenameFromDisposition = (disposition?: string): string | null => {
  if (typeof disposition !== 'string' || disposition.trim() === '') {
    return null
  }

  const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i)
  if (utf8Match?.[1]) {
    try {
      return decodeURIComponent(utf8Match[1].trim())
    } catch {
      return utf8Match[1].trim()
    }
  }

  const filenameMatch = disposition.match(/filename="([^"]+)"/i) || disposition.match(/filename=([^;]+)/i)
  return filenameMatch?.[1]?.trim() ?? null
}

const extractBlobErrorMessage = async (error: unknown, fallbackMessage: string): Promise<string> => {
  const responseData = (error as { response?: { data?: unknown } })?.response?.data
  if (responseData instanceof Blob) {
    try {
      const payload = JSON.parse(await responseData.text()) as { message?: string }
      if (typeof payload.message === 'string' && payload.message.trim() !== '') {
        return payload.message
      }
    } catch {
      // Ignore invalid JSON and fall back to standard error extraction.
    }
  }

  if (error instanceof Error && error.message.trim() !== '') {
    return error.message
  }

  const serverMessage = (error as { response?: { data?: { message?: string } } })?.response?.data?.message
  return serverMessage && serverMessage.trim() !== '' ? serverMessage : fallbackMessage
}

const ensureData = <T>(response: ApiResponse<T>): T => {
  if (response.data === undefined) {
    throw new Error(response.message || 'Unexpected API response')
  }
  return response.data
}

const extractPaginationMeta = (response: ApiResponse<unknown>): PaginationMeta => {
  return (response.meta || {}) as PaginationMeta
}

export function cacheWorkInstance(instance: WorkInstanceRecord) {
  const raw = localStorage.getItem(CACHE_KEY)
  const map: Record<string, WorkInstanceRecord> = raw ? JSON.parse(raw) : {}
  map[instance.id] = instance
  localStorage.setItem(CACHE_KEY, JSON.stringify(map))
}

export function getCachedWorkInstance(id: string): WorkInstanceRecord | null {
  const raw = localStorage.getItem(CACHE_KEY)
  if (!raw) {
    return null
  }

  const map: Record<string, WorkInstanceRecord> = JSON.parse(raw)
  return map[id] || null
}

export async function getWorkInstance(id: string): Promise<WorkInstanceRecord> {
  const response = await apiClient.get<WorkInstanceRecord>(`/api/v1/work-instances/${id}`)
  const record = ensureData(response)
  if (Array.isArray(record.steps) && record.steps.length > 0) {
    cacheWorkInstance(record)
    return record
  }

  const cached = getCachedWorkInstance(id)
  if (cached) {
    return cached
  }

  throw new Error('Cannot load work instance details. Open from Apply flow first.')
}

export async function updateWorkInstanceStep(
  workInstanceId: string,
  stepId: string,
  payload: {
    status?: string
    field_values?: Record<string, unknown>
    attachments?: Array<{ name: string }>
  }
) {
  const response = await apiClient.patch<{ step: WorkInstanceStep }>(
    zenaPath(`/work-instances/${workInstanceId}/steps/${stepId}`),
    payload
  )
  return ensureData(response)
}

export async function approveWorkInstanceStep(
  workInstanceId: string,
  stepId: string,
  payload: { decision: 'approved' | 'rejected'; comment?: string }
) {
  const response = await apiClient.post<{ id: string }>(
    zenaPath(`/work-instances/${workInstanceId}/steps/${stepId}/approve`),
    payload
  )
  return ensureData(response)
}

export async function listWorkInstanceStepAttachments(workInstanceId: string, stepId: string): Promise<WorkStepAttachment[]> {
  const response = await apiClient.get<{ attachments: WorkStepAttachment[] }>(
    zenaPath(`/work-instances/${workInstanceId}/steps/${stepId}/attachments`)
  )
  return ensureData(response).attachments || []
}

export async function uploadWorkInstanceStepAttachment(
  workInstanceId: string,
  stepId: string,
  file: File
): Promise<WorkStepAttachment> {
  const formData = new FormData()
  formData.append('file', file)

  const response = await apiClient.post<{ attachment: WorkStepAttachment }>(
    zenaPath(`/work-instances/${workInstanceId}/steps/${stepId}/attachments`),
    formData,
    {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    }
  )

  return ensureData(response).attachment
}

export async function deleteWorkInstanceStepAttachment(workInstanceId: string, stepId: string, attachmentId: string) {
  const response = await apiClient.delete<{}>(
    zenaPath(`/work-instances/${workInstanceId}/steps/${stepId}/attachments/${attachmentId}`)
  )
  return ensureData(response)
}

export async function exportWorkInstanceDeliverable(
  workInstanceId: string,
  payload: {
    deliverable_template_version_id: string
    format?: 'html' | 'pdf'
    pdf?: DeliverablePdfExportOptions
  }
): Promise<{ blob: Blob; filename: string | null }> {
  const response = await apiClient.postBlob(
    zenaPath(`/work-instances/${workInstanceId}/export`),
    payload,
    {
      headers: {
        'Content-Type': 'application/json',
        Accept: 'text/html, application/pdf',
      },
    }
  )

  return {
    blob: response.data,
    filename: extractFilenameFromDisposition(response.headers['content-disposition']),
  }
}

export async function exportWorkInstanceBundle(workInstanceId: string): Promise<Blob> {
  try {
    const response = await apiClient.postBlob(
      zenaPath(`/work-instances/${workInstanceId}/export-bundle`),
      undefined,
      {
        headers: {
          Accept: 'application/zip',
        },
      }
    )

    const filename = extractFilenameFromDisposition(response.headers['content-disposition'])
    if (filename) {
      return new File([response.data], filename, {
        type: response.data.type || 'application/zip',
      })
    }

    return response.data
  } catch (error: unknown) {
    throw new Error(await extractBlobErrorMessage(error, 'Failed to download work instance bundle'))
  }
}


// Project-scoped Work Instances
export async function listProjectWorkInstances(
  projectId: string,
  params?: { page?: number; per_page?: number }
): Promise<ProjectWorkInstancesList> {
  const response = await apiClient.get<WorkInstanceRecord[]>(zenaPath(`/projects/${projectId}/work-instances`), {
    params,
  })

  return {
    items: ensureData(response),
    meta: {
      pagination: extractPaginationMeta(response).pagination,
    },
  }
}

export async function listWorkInstances(params?: WorkInstanceListParams): Promise<ProjectWorkInstancesList> {
  const response = await apiClient.get<WorkInstanceRecord[]>(zenaPath('/work-instances'), {
    params,
  })

  return {
    items: ensureData(response),
    meta: {
      pagination: extractPaginationMeta(response).pagination,
    },
  }
}

export async function getWorkInstanceMetrics(params?: Omit<WorkInstanceListParams, 'page' | 'per_page'>): Promise<WorkInstanceMetrics> {
  const response = await apiClient.get<{ metrics: WorkInstanceMetrics }>(zenaPath('/work-instances/metrics'), {
    params,
  })

  return ensureData(response).metrics
}
