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
  assignee_rule_json?: {
    requires_approval?: boolean
  }
}

export type WorkInstanceRecord = {
  id: string
  project_id?: string
  status: string
  steps: WorkInstanceStep[]
}

const zenaPath = (path: string) => `/api/zena${path}`
const CACHE_KEY = 'work_instance_cache_v1'

const ensureData = <T>(response: ApiResponse<T>): T => {
  if (response.data === undefined) {
    throw new Error(response.message || 'Unexpected API response')
  }
  return response.data
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
